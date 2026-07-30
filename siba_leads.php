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

hooks()->add_action('admin_init', 'siba_leads_init_menu');
hooks()->add_action('admin_init', 'siba_leads_register_permissions');
hooks()->add_action('app_admin_head', 'siba_leads_load_admin_css');
hooks()->add_action('app_admin_footer', 'siba_leads_load_admin_js');
hooks()->add_filter('module_' . SIBA_LEADS_MODULE_NAME . '_action_links', 'siba_leads_action_links');

register_activation_hook(SIBA_LEADS_MODULE_NAME, 'siba_leads_module_activation_hook');
register_language_files(SIBA_LEADS_MODULE_NAME, [SIBA_LEADS_MODULE_NAME]);

function siba_leads_module_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

function siba_leads_register_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view' => _l('permission_view'),
        'create' => _l('permission_create'),
        'edit' => _l('permission_edit'),
        'delete' => _l('permission_delete'),
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

    // Child under the default Leads menu so all lead work stays in one place.
    $CI->app_menu->add_sidebar_children_item('leads', [
        'slug' => 'siba-leads-workspace',
        'name' => _l('siba_leads_workspace'),
        'href' => admin_url('siba_leads'),
        'position' => 99,
    ]);
}

function siba_leads_load_admin_css()
{
    echo '<link href="' . module_dir_url(SIBA_LEADS_MODULE_NAME, 'assets/css/style.css?v=20260730a') . '" rel="stylesheet" type="text/css">';
}

function siba_leads_load_admin_js()
{
    echo '<script src="' . module_dir_url(SIBA_LEADS_MODULE_NAME, 'assets/js/siba_leads.js?v=20260730a') . '"></script>';
}

function siba_leads_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('siba_leads') . '">' . _l('siba_leads_workspace') . '</a>';
    return $actions;
}
