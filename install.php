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
