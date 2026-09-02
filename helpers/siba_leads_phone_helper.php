<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Convert Persian/Arabic digits to Latin.
 */
function siba_leads_to_latin_digits($value): string
{
    $value = (string) $value;
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $latin   = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str_replace($arabic, $latin, str_replace($persian, $latin, $value));
}

/**
 * Canonical local phone: digits only, Iran +98 → leading 0.
 * Examples: 09121234567, +989121234567, 989121234567, ۹۱۲۱۲۳۴۵۶۷ → 09121234567
 */
function siba_leads_normalize_phone($phone): string
{
    $digits = preg_replace('/\D+/', '', siba_leads_to_latin_digits($phone));
    if ($digits === null || $digits === '') {
        return '';
    }

    $digits = ltrim($digits, '0');
    if (strpos($digits, '98') === 0 && strlen($digits) >= 12) {
        $digits = substr($digits, 2);
    }

    if ($digits === '') {
        return '';
    }

    if ($digits[0] !== '0') {
        $digits = '0' . $digits;
    }

    return $digits;
}

/**
 * Lookup variants that may already be stored on a lead row.
 *
 * @return string[]
 */
function siba_leads_phone_variants($phone): array
{
    $normalized = siba_leads_normalize_phone($phone);
    if ($normalized === '') {
        return [];
    }

    $last10 = strlen($normalized) >= 10 ? substr($normalized, -10) : ltrim($normalized, '0');
    if ($last10 === '') {
        return [$normalized];
    }

    return array_values(array_unique(array_filter([
        $normalized,
        $last10,
        '0' . $last10,
        '98' . $last10,
        '+98' . $last10,
        '0098' . $last10,
    ])));
}

/**
 * Existing lead id with the same phone, or 0.
 */
function siba_leads_find_lead_id_by_phone($phone, $excludeId = 0): int
{
    $normalized = siba_leads_normalize_phone($phone);
    if ($normalized === '') {
        return 0;
    }

    $CI = &get_instance();
    $table = db_prefix() . 'leads';
    if (!$CI->db->table_exists($table) || !$CI->db->field_exists('phonenumber', $table)) {
        return 0;
    }

    $last10 = strlen($normalized) >= 10 ? substr($normalized, -10) : ltrim($normalized, '0');
    $variants = siba_leads_phone_variants($phone);

    $CI->db->select('id, phonenumber');
    $CI->db->from($table);
    if ((int) $excludeId > 0) {
        $CI->db->where('id !=', (int) $excludeId);
    }
    $CI->db->group_start();
    foreach ($variants as $variant) {
        $CI->db->or_where('phonenumber', $variant);
    }
    if ($last10 !== '') {
        $CI->db->or_like('phonenumber', $last10, 'before');
    }
    $CI->db->group_end();
    $CI->db->limit(30);
    $rows = $CI->db->get()->result_array();

    foreach ($rows as $row) {
        if (siba_leads_normalize_phone($row['phonenumber'] ?? '') === $normalized) {
            return (int) $row['id'];
        }
    }

    return 0;
}

/**
 * True when this phone belongs to another lead, including earlier creates in this request.
 */
function siba_leads_phone_is_duplicate($phone, $excludeId = 0): bool
{
    $normalized = siba_leads_normalize_phone($phone);
    if ($normalized === '') {
        return false;
    }

    $created = siba_leads_created_phones_this_request();
    if (isset($created[$normalized]) && (int) $created[$normalized] !== (int) $excludeId) {
        return true;
    }

    return siba_leads_find_lead_id_by_phone($phone, $excludeId) > 0;
}

/**
 * Remember a phone after a successful create so bulk API/CSV cannot insert twice.
 */
function siba_leads_remember_created_phone($phone, $leadId = 0): void
{
    $normalized = siba_leads_normalize_phone($phone);
    if ($normalized === '') {
        return;
    }

    $bucket = &siba_leads_created_phones_this_request();
    $bucket[$normalized] = (int) $leadId;
}

/**
 * @return array<string,int>
 */
function &siba_leads_created_phones_this_request(): array
{
    if (!isset($GLOBALS['siba_leads_created_phones']) || !is_array($GLOBALS['siba_leads_created_phones'])) {
        $GLOBALS['siba_leads_created_phones'] = [];
    }

    return $GLOBALS['siba_leads_created_phones'];
}

/**
 * Resolve the lead id being edited from POST/URI (for duplicate-phone exclusion).
 */
function siba_leads_resolve_lead_exclude_id_from_request(): int
{
    $CI = &get_instance();

    $fromPost = (int) $CI->input->post('lead_id');
    if ($fromPost > 0) {
        return $fromPost;
    }

    $fromPost = (int) $CI->input->post('leadid');
    if ($fromPost > 0) {
        return $fromPost;
    }

    $segments = $CI->uri->segment_array();
    $count    = count($segments);
    for ($i = 0; $i < $count - 1; $i++) {
        if (strtolower((string) $segments[$i]) === 'leads'
            && strtolower((string) ($segments[$i + 1] ?? '')) === 'lead'
            && isset($segments[$i + 2])
            && ctype_digit((string) $segments[$i + 2])) {
            return (int) $segments[$i + 2];
        }
    }

    $paths = array_filter([
        (string) $CI->uri->uri_string(),
        (string) ($CI->input->server('REQUEST_URI') ?: ''),
    ]);

    foreach ($paths as $path) {
        if (preg_match('#leads/lead/(\d+)#i', $path, $m)) {
            return (int) $m[1];
        }
    }

    return 0;
}

function siba_leads_duplicate_phone_message($existingId = 0): string
{
    $msg = _l('siba_leads_duplicate_phone');
    if ($existingId > 0) {
        $msg .= ' (#' . (int) $existingId . ')';
    }

    return $msg;
}

/**
 * Stop lead save when the phone already exists (admin AJAX form / model add).
 */
function siba_leads_abort_if_duplicate_phone($phone, $excludeId = 0): void
{
    $existing = siba_leads_find_lead_id_by_phone($phone, $excludeId);
    $normalized = siba_leads_normalize_phone($phone);
    if ($normalized !== '') {
        $created = siba_leads_created_phones_this_request();
        if (isset($created[$normalized]) && (int) $created[$normalized] !== (int) $excludeId) {
            $existing = $existing ?: (int) $created[$normalized];
        }
    }

    if ($existing <= 0) {
        return;
    }

    $CI  = &get_instance();
    $msg = siba_leads_duplicate_phone_message($existing);
    $uri = (string) $CI->uri->uri_string();

    if (strpos($uri, 'siba_api/') !== false) {
        return;
    }

    if ($CI->input->is_ajax_request() || strpos($uri, 'leads/lead') !== false) {
        echo json_encode([
            'success'     => false,
            'message'     => $msg,
            'id'          => false,
            'existing_id' => $existing,
            'leadView'    => [],
        ]);
        die;
    }

    show_error($msg, 409, _l('siba_leads_duplicate_phone_title'));
}

/**
 * Admin POST guards: live unique check + block lead create/update.
 */
function siba_leads_guard_duplicate_phone()
{
    $CI = &get_instance();
    if (strtoupper((string) $CI->input->method(true)) !== 'POST') {
        return;
    }

    $uri = (string) $CI->uri->uri_string();
    $requestUri = (string) ($CI->input->server('REQUEST_URI') ?: '');
    $path = $uri . ' ' . $requestUri;

    if (preg_match('#(?:^|/)siba_leads/validate_phone(?:/|\?|$)#', $path)) {
        if (!is_staff_member()) {
            ajax_access_denied();
        }
        $exclude = siba_leads_resolve_lead_exclude_id_from_request();
        $dup     = siba_leads_phone_is_duplicate($CI->input->post('phonenumber'), $exclude);
        echo json_encode(!$dup);
        die;
    }

    if (!preg_match('#leads/lead(?:/(\d+))?(?:/|\?|$)#i', $path, $m)) {
        return;
    }

    if ($CI->input->post('phonenumber') === null && $CI->input->post('name') === null) {
        return;
    }

    $exclude = siba_leads_resolve_lead_exclude_id_from_request();
    siba_leads_abort_if_duplicate_phone($CI->input->post('phonenumber'), $exclude);
}
