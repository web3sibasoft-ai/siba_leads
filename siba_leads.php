<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Siba Leads
Description: Lead extensions without changing Perfex core.
Version: 1.0.0
Requires at least: 3.0.*
Author: Siba
*/

define('SIBA_LEADS_MODULE_NAME', 'siba_leads');
define('SIBA_LEADS_REPOSITORY', 'https://github.com/web3sibasoft-ai/siba-leads.git');

hooks()->add_action('modules_loaded', 'siba_leads_load_helpers');
hooks()->add_action('admin_init', 'siba_leads_init_menu');
hooks()->add_action('admin_init', 'siba_leads_register_permissions');
hooks()->add_action('admin_init', 'siba_leads_mark_csv_import_window');
hooks()->add_action('admin_init', 'siba_leads_guard_duplicate_phone', 1);
hooks()->add_action('app_admin_head', 'siba_leads_load_admin_css');
hooks()->add_action('app_admin_footer', 'siba_leads_load_admin_js');
hooks()->add_action('app_admin_footer', 'siba_leads_process_csv_import_assignments');
hooks()->add_filter('module_' . SIBA_LEADS_MODULE_NAME . '_action_links', 'siba_leads_action_links');
hooks()->add_filter('sidebar_menu_items', 'siba_leads_ensure_leads_menu_children', 1000);
hooks()->add_filter('setup_menu_items', 'siba_leads_ensure_setup_teams_menu', 1000);
hooks()->add_filter('before_lead_added', 'siba_leads_filter_website_payload', 5);
hooks()->add_filter('before_lead_added', 'siba_leads_filter_before_lead_added');
hooks()->add_filter('before_lead_added', 'siba_leads_filter_lead_location_fields', 20);
hooks()->add_filter('before_lead_added', 'siba_leads_filter_profile_fields', 25);
hooks()->add_action('lead_created', 'siba_leads_action_lead_created');
hooks()->add_action('lead_created', 'siba_leads_action_website_lead_created', 30);
hooks()->add_filter('before_insert_lead_from_email_integration', 'siba_leads_filter_before_email_lead');
hooks()->add_filter('before_insert_lead_from_email_integration', 'siba_leads_filter_lead_location_fields', 20);
hooks()->add_filter('before_insert_lead_from_email_integration', 'siba_leads_filter_profile_fields', 25);
hooks()->add_action('pre_admin_init', 'siba_leads_intercept_lead_location_post', 1);
hooks()->add_action('after_lead_updated', 'siba_leads_persist_location_on_save');
hooks()->add_action('after_lead_updated', 'siba_leads_persist_profile_on_save');
hooks()->add_action('after_lead_updated', 'siba_leads_action_website_lead_updated', 30);
hooks()->add_action('lead_modal_profile_bottom', 'siba_leads_lead_modal_location_seed');
hooks()->add_action('lead_modal_profile_bottom', 'siba_leads_lead_modal_profile_seed');
hooks()->add_action('lead_modal_profile_bottom', 'siba_leads_lead_modal_form_meta_seed');
hooks()->add_action('web_to_lead_form_submitted', 'siba_leads_action_web_to_lead_submitted');
hooks()->add_action('lead_created_from_email_integration', 'siba_leads_action_email_lead_created');
hooks()->add_filter('not_importable_leads_fields', 'siba_leads_not_importable_leads_fields');


register_activation_hook(SIBA_LEADS_MODULE_NAME, 'siba_leads_module_activation_hook');
register_language_files(SIBA_LEADS_MODULE_NAME, [SIBA_LEADS_MODULE_NAME]);

function siba_leads_module_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

function siba_leads_load_helpers()
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    get_instance()->load->helper(SIBA_LEADS_MODULE_NAME . '/siba_leads');

    // Ensure team-role option exists without requiring module reactivation.
    if (get_option('siba_leads_team_roles') === '' || get_option('siba_leads_team_roles') === false) {
        add_option('siba_leads_team_roles', json_encode([
            'sales'   => 0,
            'support' => 0,
            'tech'    => 0,
        ]));
    }

    if (get_option('siba_leads_team_org_units') === '' || get_option('siba_leads_team_org_units') === false) {
        add_option('siba_leads_team_org_units', json_encode([
            'sales'   => 0,
            'support' => 0,
            'tech'    => 0,
        ]));
    }

    if (get_option(SIBA_LEADS_AUTO_ASSIGN_OPTION) === false || get_option(SIBA_LEADS_AUTO_ASSIGN_OPTION) === '') {
        add_option(SIBA_LEADS_AUTO_ASSIGN_OPTION, '1');
    }

    if (get_option(SIBA_LEADS_RR_OPTION) === false || get_option(SIBA_LEADS_RR_OPTION) === '') {
        add_option(SIBA_LEADS_RR_OPTION, '0');
    }

    // Create Backlog column and sync legacy unassigned leads once.
    if (function_exists('siba_leads_ensure_backlog_status')) {
        siba_leads_ensure_backlog_status();
        if (get_option('siba_leads_backlog_synced') !== '1') {
            siba_leads_sync_unassigned_into_backlog();
            add_option('siba_leads_backlog_synced', '1');
            update_option('siba_leads_backlog_synced', '1');
        }
    }

    if (function_exists('siba_leads_ensure_lead_columns')) {
        siba_leads_ensure_lead_columns();
    }
}

function siba_leads_register_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view'     => _l('permission_view') . ' (' . _l('siba_leads_permission_view_own') . ')',
        'view_all' => _l('permission_view') . ' (' . _l('permission_global') . ')',
        'create'   => _l('permission_create'),
        'edit'     => _l('permission_edit'),
        'delete'   => _l('permission_delete'),
    ];

    register_staff_capabilities(
        SIBA_LEADS_MODULE_NAME,
        $capabilities,
        _l('siba_leads')
    );
}

function siba_leads_init_menu()
{
    $CI = &get_instance();

    if (!is_admin() && !staff_can('view', SIBA_LEADS_MODULE_NAME)) {
        return;
    }

    // Registered early; siba_leads_ensure_leads_menu_children re-injects after menu_setup.
    $CI->app_menu->add_sidebar_children_item('leads', [
        'slug'     => 'siba-leads-workspace',
        'name'     => _l('siba_leads_workspace'),
        'href'     => admin_url('siba_leads'),
        'position' => 99,
    ]);

    if (is_admin()) {
        $CI->app_menu->add_sidebar_children_item('leads', [
            'slug'     => 'siba-leads-teams',
            'name'     => _l('siba_leads_teams'),
            'href'     => admin_url('siba_leads/teams'),
            'position' => 100,
        ]);

        $CI->app_menu->add_setup_menu_item('siba-leads-teams', [
            'name'     => _l('siba_leads_teams'),
            'href'     => admin_url('siba_leads/teams'),
            'position' => 36,
        ]);
    }
}

/**
 * Menu Setup rebuilds Leads children from saved JSON and drops unknown items
 * because the Leads parent has no collapse=true. Re-attach our children last.
 */
function siba_leads_ensure_leads_menu_children($items)
{
    if (!is_array($items)) {
        return $items;
    }

    $canView = is_admin() || staff_can('view', SIBA_LEADS_MODULE_NAME);
    if (!$canView) {
        return $items;
    }

    foreach ($items as $key => $item) {
        if (($item['slug'] ?? '') !== 'leads') {
            continue;
        }

        if (!isset($items[$key]['children']) || !is_array($items[$key]['children'])) {
            $items[$key]['children'] = [];
        }

        $children = $items[$key]['children'];
        $slugs    = array_column($children, 'slug');

        if (!in_array('siba-leads-workspace', $slugs, true)) {
            $children[] = [
                'parent_slug' => 'leads',
                'slug'        => 'siba-leads-workspace',
                'name'        => _l('siba_leads_workspace'),
                'href'        => admin_url('siba_leads'),
                'position'    => 99,
                'icon'        => '',
                'badge'       => [],
                'href_attrs'  => [],
            ];
        }

        if (is_admin() && !in_array('siba-leads-teams', $slugs, true)) {
            $children[] = [
                'parent_slug' => 'leads',
                'slug'        => 'siba-leads-teams',
                'name'        => _l('siba_leads_teams'),
                'href'        => admin_url('siba_leads/teams'),
                'position'    => 100,
                'icon'        => '',
                'badge'       => [],
                'href_attrs'  => [],
            ];
        }

        $items[$key]['children'] = $children;
        break;
    }

    return $items;
}

function siba_leads_ensure_setup_teams_menu($items)
{
    if (!is_admin() || !is_array($items)) {
        return $items;
    }

    foreach ($items as $item) {
        if (($item['slug'] ?? '') === 'siba-leads-teams') {
            return $items;
        }
    }

    $items['siba-leads-teams'] = [
        'slug'       => 'siba-leads-teams',
        'name'       => _l('siba_leads_teams'),
        'href'       => admin_url('siba_leads/teams'),
        'position'   => 36,
        'icon'       => 'fa fa-users',
        'badge'      => [],
        'href_attrs' => [],
        'children'   => [],
    ];

    return $items;
}

function siba_leads_load_admin_css()
{
    echo '<link href="' . module_dir_url(SIBA_LEADS_MODULE_NAME, 'assets/css/style.css?v=20260908f') . '" rel="stylesheet" type="text/css">';
}

function siba_leads_load_admin_js()
{
    $hideFields = [
        'lead_value'       => _l('lead_value'),
        'default_language' => _l('localization_default_language'),
        'lead_public'      => _l('lead_public'),
        'whatsapp_enable'  => 'Whatsapp Enable',
    ];

    $CI = &get_instance();
    $provinces = function_exists('siba_leads_get_active_provinces') ? siba_leads_get_active_provinces() : [];
    $citiesByProvince = function_exists('siba_leads_get_active_cities_by_province')
        ? siba_leads_get_active_cities_by_province()
        : [];
    $citiesUrl = admin_url('siba_leads/get_cities');
    $jobGroups = function_exists('siba_leads_get_active_job_groups') ? siba_leads_get_active_job_groups() : [];
    $positions = function_exists('siba_leads_get_active_positions') ? siba_leads_get_active_positions() : [];

    echo '<script>window.sibaLeadsHideLeadFields = ' . json_encode($hideFields, JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>window.sibaLeadsMarkPaidConfirm = ' . json_encode(_l('siba_leads_card_mark_paid_confirm'), JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>window.sibaLeadsDuplicatePhone = ' . json_encode(_l('siba_leads_duplicate_phone'), JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>window.sibaLeadsDuplicatePhoneWarning = ' . json_encode(_l('siba_leads_duplicate_phone_warning'), JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>window.sibaLeadsProvinces = ' . json_encode($provinces, JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>window.sibaLeadsCitiesByProvince = ' . json_encode(
        (object) $citiesByProvince,
        JSON_UNESCAPED_UNICODE
    ) . ';</script>';
    echo '<script>window.sibaLeadsCitiesUrl = ' . json_encode($citiesUrl) . ';</script>';
    echo '<script>window.sibaLeadsLocationLabels = ' . json_encode([
        'province' => _l('siba_leads_province'),
        'city'     => _l('siba_leads_city'),
        'select'   => _l('dropdown_non_selected_tex'),
        'loading'  => _l('siba_leads_city_loading'),
        'pickProvinceFirst' => _l('siba_leads_pick_province_first'),
    ], JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>window.sibaLeadsProfileLabels = ' . json_encode([
        'nationalCode' => _l('siba_leads_national_code'),
        'position'     => _l('siba_leads_position'),
        'jobGroup'     => _l('siba_leads_job_group'),
        'select'       => _l('dropdown_non_selected_tex'),
    ], JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>window.sibaLeadsFormMetaLabels = ' . json_encode([
        'title'               => _l('siba_leads_website_form_info'),
        'formIdentifier'      => _l('form_identifier'),
        'formSourceLink'      => _l('form_source_link'),
        'formSourcePageTitle' => _l('form_source_page_title'),
        'smsVerification'     => _l('sms_verification'),
        'smsVerified'         => _l('siba_leads_sms_verified'),
        'smsUnverified'       => _l('siba_leads_sms_unverified'),
        'trackingId'          => _l('demo_request_tracking_id'),
        'packageNumber'       => _l('user_package_number'),
        'requestType'         => _l('request_type'),
        'descriptionMap'      => _l('siba_leads_form_description_map'),
        'extraMeta'           => _l('siba_leads_form_extra_meta'),
    ], JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>window.sibaLeadJobGroups = ' . json_encode($jobGroups, JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>window.sibaLeadPositions = ' . json_encode($positions, JSON_UNESCAPED_UNICODE) . ';</script>';
    $defaultCountryId = function_exists('siba_leads_default_country_id') ? siba_leads_default_country_id() : 0;
    echo '<script>window.sibaLeadsDefaultCountryId = ' . (int) $defaultCountryId . ';</script>';
    echo '<script src="' . module_dir_url(SIBA_LEADS_MODULE_NAME, 'assets/js/siba_leads.js?v=20260908g') . '"></script>';
}

function siba_leads_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('siba_leads') . '">' . _l('siba_leads_workspace') . '</a>';
    return $actions;
}

/**
 * Keep custom local columns out of core CSV import pipeline.
 * This avoids import failures on environments that added NOT NULL lead columns.
 */
function siba_leads_not_importable_leads_fields($fields)
{
    if (!is_array($fields)) {
        $fields = [];
    }

    $fields[] = 'converted_by_lead_manager';

    return array_values(array_unique($fields));
}
