<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Extra lead columns for Siba province/city lookup IDs.
 *
 * @return array<string, string>
 */
function siba_leads_location_columns(): array
{
    return [
        'province_id' => 'INT(11) NOT NULL DEFAULT 0',
        'city_id'     => 'INT(11) NOT NULL DEFAULT 0',
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
/**
 * Default country for new Siba leads (Iran).
 */
function siba_leads_default_country_id(): int
{
    static $countryId = null;

    if ($countryId !== null) {
        return $countryId;
    }

    $CI = &get_instance();
    $row = $CI->db->select('country_id')
        ->group_start()
        ->where('iso2', 'IR')
        ->or_where('short_name', 'Iran')
        ->or_where('long_name', 'Iran, Islamic Republic of')
        ->group_end()
        ->limit(1)
        ->get(db_prefix() . 'countries')
        ->row();

    $countryId = $row ? (int) $row->country_id : 0;

    return $countryId;
}

function siba_leads_get_active_provinces(): array
{
    $CI = &get_instance();
    $table = db_prefix() . 'siba_provinces';
    if (!$CI->db->table_exists($table)) {
        return [];
    }

    return $CI->db->where('status', 1)
        ->order_by('province_name', 'ASC')
        ->get($table)
        ->result_array();
}

/**
 * @return array<int, array<string, mixed>>
 */
function siba_leads_get_active_cities($province_id): array
{
    $province_id = (int) $province_id;
    if ($province_id < 1) {
        return [];
    }

    $CI = &get_instance();
    $table = db_prefix() . 'siba_cities';
    if (!$CI->db->table_exists($table)) {
        return [];
    }

    return $CI->db->where(['province_id' => $province_id, 'status' => 1])
        ->order_by('city_name', 'ASC')
        ->get($table)
        ->result_array();
}

function siba_leads_get_province_name($province_id): string
{
    $province_id = (int) $province_id;
    if ($province_id < 1) {
        return '';
    }

    $CI = &get_instance();
    $row = $CI->db->select('province_name')
        ->where(['province_id' => $province_id, 'status' => 1])
        ->get(db_prefix() . 'siba_provinces')
        ->row();

    return $row ? (string) $row->province_name : '';
}

function siba_leads_get_city_name($city_id): string
{
    $city_id = (int) $city_id;
    if ($city_id < 1) {
        return '';
    }

    $CI = &get_instance();
    $row = $CI->db->select('city_name')
        ->where(['city_id' => $city_id, 'status' => 1])
        ->get(db_prefix() . 'siba_cities')
        ->row();

    return $row ? (string) $row->city_name : '';
}

/**
 * Resolve province/city IDs for a lead row (prefers stored IDs, falls back to text match).
 *
 * @param object|array|null $lead
 * @return array{province_id:int,city_id:int,province_name:string,city_name:string}
 */
function siba_leads_resolve_lead_location($lead): array
{
    $province_id = 0;
    $city_id     = 0;
    $state_text  = '';
    $city_text   = '';

    if (is_object($lead)) {
        $province_id = (int) ($lead->province_id ?? 0);
        $city_id     = (int) ($lead->city_id ?? 0);
        $state_text  = trim((string) ($lead->state ?? ''));
        $city_text   = trim((string) ($lead->city ?? ''));
    } elseif (is_array($lead)) {
        $province_id = (int) ($lead['province_id'] ?? 0);
        $city_id     = (int) ($lead['city_id'] ?? 0);
        $state_text  = trim((string) ($lead['state'] ?? ''));
        $city_text   = trim((string) ($lead['city'] ?? ''));
    }

    if ($province_id < 1 && $state_text !== '' && function_exists('siba_license_match_province_id')) {
        $province_id = siba_license_match_province_id($state_text);
    }
    if ($province_id < 1 && $city_text !== '' && function_exists('siba_license_match_province_id')) {
        $province_id = siba_license_match_province_id($city_text);
    }
    if ($city_id < 1 && $city_text !== '' && function_exists('siba_license_match_city_id')) {
        $city_id = siba_license_match_city_id($city_text, $province_id);
    }

    $province_name = $province_id > 0 ? siba_leads_get_province_name($province_id) : $state_text;
    $city_name     = $city_id > 0 ? siba_leads_get_city_name($city_id) : $city_text;

    return [
        'province_id'   => $province_id,
        'city_id'       => $city_id,
        'province_name' => $province_name,
        'city_name'     => $city_name,
    ];
}

/**
 * Normalize lead POST/API payload: keep province_id/city_id and sync legacy city/state text.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function siba_leads_sync_location_fields(array $data): array
{
    $province_id = (int) ($data['province_id'] ?? 0);
    $city_id     = (int) ($data['city_id'] ?? 0);

    $data['province_id'] = $province_id;
    $data['city_id']     = $city_id;

    if ($province_id > 0) {
        $province_name = siba_leads_get_province_name($province_id);
        if ($province_name !== '') {
            $data['state'] = $province_name;
        }
    }

    if ($city_id > 0) {
        $city_name = siba_leads_get_city_name($city_id);
        if ($city_name !== '') {
            $data['city'] = $city_name;
        }
    } elseif ($province_id < 1) {
        $data['state'] = '';
        $data['city']  = '';
    } elseif ($city_id < 1) {
        $data['city'] = '';
    }

    if ($province_id < 1 && !empty($data['state']) && function_exists('siba_license_match_province_id')) {
        $data['province_id'] = siba_license_match_province_id($data['state']);
    }
    if ($city_id < 1 && !empty($data['city']) && function_exists('siba_license_match_city_id')) {
        $data['city_id'] = siba_license_match_city_id(
            $data['city'],
            (int) ($data['province_id'] ?? 0)
        );
    }

    return $data;
}

/**
 * Merge $_POST location fields before core Leads controller saves.
 */
function siba_leads_intercept_lead_location_post(): void
{
    $CI = &get_instance();
    if ($CI->router->fetch_class() !== 'leads' || $CI->router->fetch_method() !== 'lead') {
        return;
    }
    if (!$CI->input->post()) {
        return;
    }

    $normalized = siba_leads_sync_location_fields($CI->input->post(null, true));
    foreach ($normalized as $key => $value) {
        $_POST[$key] = $value;
    }
}

/**
 * before_lead_added filter callback.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function siba_leads_filter_lead_location_fields($data)
{
    if (!is_array($data)) {
        return $data;
    }

    $data = siba_leads_sync_location_fields($data);

    if (empty($data['country']) && function_exists('siba_leads_default_country_id')) {
        $defaultCountry = siba_leads_default_country_id();
        if ($defaultCountry > 0) {
            $data['country'] = $defaultCountry;
        }
    }

    return $data;
}

/**
 * Ensure province/city columns are written even when core update skips them.
 *
 * @param int $lead_id
 */
function siba_leads_persist_location_on_save($lead_id): void
{
    $lead_id = (int) $lead_id;
    if ($lead_id < 1) {
        return;
    }

    $CI = &get_instance();
    if (!$CI->input->post()) {
        return;
    }

    if (!$CI->db->field_exists('province_id', db_prefix() . 'leads')) {
        return;
    }

    $data = siba_leads_sync_location_fields($CI->input->post(null, true));

    $CI->db->where('id', $lead_id)->update(db_prefix() . 'leads', [
        'province_id' => (int) ($data['province_id'] ?? 0),
        'city_id'     => (int) ($data['city_id'] ?? 0),
        'state'       => (string) ($data['state'] ?? ''),
        'city'        => (string) ($data['city'] ?? ''),
    ]);
}

/**
 * Seed script for lead modal (existing lead edit/view).
 *
 * @param int|string $lead_id
 */
function siba_leads_lead_modal_location_seed($lead_id): void
{
    $lead_id = (int) $lead_id;
    if ($lead_id < 1) {
        echo '<script>window.sibaLeadLocationSeed = null;</script>';

        return;
    }

    $CI = &get_instance();
    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    $lead = $CI->leads_model->get($lead_id);
    if (!$lead) {
        echo '<script>window.sibaLeadLocationSeed = null;</script>';

        return;
    }

    $location = siba_leads_resolve_lead_location($lead);
    echo '<script>window.sibaLeadLocationSeed = ' . json_encode([
        'province_id'   => $location['province_id'],
        'city_id'       => $location['city_id'],
        'province_name' => $location['province_name'],
        'city_name'     => $location['city_name'],
    ], JSON_UNESCAPED_UNICODE) . ';</script>';
}
