<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'siba_leads_meta')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "siba_leads_meta` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `lead_id` int(11) NOT NULL,
        `meta_key` varchar(100) NOT NULL,
        `meta_value` longtext NULL,
        `dateadded` datetime NOT NULL,
        `staff_id` int(11) NULL,
        PRIMARY KEY (`id`),
        KEY `lead_id` (`lead_id`),
        KEY `meta_key` (`meta_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!get_option('siba_leads_repo_url')) {
    add_option('siba_leads_repo_url', SIBA_LEADS_REPOSITORY);
}

if (get_option('siba_leads_team_roles') === false || get_option('siba_leads_team_roles') === null || get_option('siba_leads_team_roles') === '') {
    // Empty map — roles are picked in Siba Leads → Teams settings.
    add_option('siba_leads_team_roles', json_encode([
        'sales'   => 0,
        'support' => 0,
        'tech'    => 0,
    ]));
}

if (get_option('siba_leads_auto_assign_sales') === false || get_option('siba_leads_auto_assign_sales') === '') {
    add_option('siba_leads_auto_assign_sales', '1');
}

if (get_option('siba_leads_sales_rr_index') === false || get_option('siba_leads_sales_rr_index') === '') {
    add_option('siba_leads_sales_rr_index', '0');
}

if (get_option('siba_leads_didar_base_url') === false || get_option('siba_leads_didar_base_url') === '') {
    add_option('siba_leads_didar_base_url', 'https://app.didar.me');
}
if (get_option('siba_leads_didar_api_key') === false) {
    add_option('siba_leads_didar_api_key', '');
}
if (get_option('siba_leads_didar_pipeline_id') === false) {
    add_option('siba_leads_didar_pipeline_id', '');
}
if (get_option('siba_leads_didar_import_source_id') === false) {
    add_option('siba_leads_didar_import_source_id', '0');
}
if (get_option('siba_leads_didar_ssl_verify') === false || get_option('siba_leads_didar_ssl_verify') === '') {
    add_option('siba_leads_didar_ssl_verify', '1');
}

// Temporary Didar user id on staff (UUID) — reinstall-safe
if ($CI->db->table_exists(db_prefix() . 'staff')
    && !$CI->db->field_exists('didar_id', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff`
        ADD COLUMN `didar_id` VARCHAR(64) NULL DEFAULT NULL AFTER `email`;');
}

if (!$CI->db->table_exists(db_prefix() . 'siba_leads_failure_reasons')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "siba_leads_failure_reasons` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(191) NOT NULL,
        `color` varchar(20) NOT NULL DEFAULT '#6b7280',
        `datecreated` datetime NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

$CI->load->helper('siba_leads/siba_leads');
if (function_exists('siba_leads_ensure_lead_columns')) {
    siba_leads_ensure_lead_columns();
}
if (function_exists('siba_leads_ensure_failure_reasons_table')) {
    siba_leads_ensure_failure_reasons_table();
}
