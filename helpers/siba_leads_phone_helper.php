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

/**
 * Existing lead ids that share this phone (normalized), excluding $excludeId.
 *
 * @return int[]
 */
function siba_leads_find_sibling_lead_ids_by_phone($phone, $excludeId = 0, $limit = 10): array
{
    $normalized = siba_leads_normalize_phone($phone);
    if ($normalized === '') {
        return [];
    }

    $CI = &get_instance();
    $table = db_prefix() . 'leads';
    if (!$CI->db->table_exists($table) || !$CI->db->field_exists('phonenumber', $table)) {
        return [];
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
    $CI->db->order_by('id', 'ASC');
    $CI->db->limit(max(1, (int) $limit) * 3);
    $rows = $CI->db->get()->result_array();

    $ids = [];
    foreach ($rows as $row) {
        if (siba_leads_normalize_phone($row['phonenumber'] ?? '') !== $normalized) {
            continue;
        }
        $ids[] = (int) $row['id'];
        if (count($ids) >= (int) $limit) {
            break;
        }
    }

    return $ids;
}

/**
 * Soft duplicate info for API / UI (never blocks insert by itself).
 *
 * @return array{is_duplicate:bool,existing_id:int,sibling_ids:int[]}
 */
function siba_leads_phone_duplicate_info($phone, $excludeId = 0): array
{
    $siblings = siba_leads_find_sibling_lead_ids_by_phone($phone, $excludeId, 10);
    $existing = $siblings[0] ?? 0;

    $normalized = siba_leads_normalize_phone($phone);
    if ($normalized !== '') {
        $created = siba_leads_created_phones_this_request();
        if (isset($created[$normalized]) && (int) $created[$normalized] !== (int) $excludeId) {
            $fromReq = (int) $created[$normalized];
            if ($fromReq > 0 && !in_array($fromReq, $siblings, true)) {
                array_unshift($siblings, $fromReq);
                $existing = $existing ?: $fromReq;
            }
        }
    }

    return [
        'is_duplicate' => $existing > 0,
        'existing_id'  => (int) $existing,
        'sibling_ids'  => array_values(array_unique(array_map('intval', $siblings))),
    ];
}

/**
 * First customer (userid) whose client or contact phone matches, or 0.
 * Prefer the lowest userid when several rows match.
 */
function siba_leads_find_client_id_by_phone($phone): int
{
    $normalized = siba_leads_normalize_phone($phone);
    if ($normalized === '') {
        return 0;
    }

    $CI = &get_instance();
    $clientsTable  = db_prefix() . 'clients';
    $contactsTable = db_prefix() . 'contacts';
    if (!$CI->db->table_exists($clientsTable)) {
        return 0;
    }

    $last10   = strlen($normalized) >= 10 ? substr($normalized, -10) : ltrim($normalized, '0');
    $variants = siba_leads_phone_variants($phone);
    $matches  = [];

    $clientPhoneCols = ['phonenumber'];
    foreach (['phonenumber2', 'telephone'] as $col) {
        if ($CI->db->field_exists($col, $clientsTable)) {
            $clientPhoneCols[] = $col;
        }
    }

    $CI->db->select('userid, ' . implode(', ', $clientPhoneCols));
    $CI->db->from($clientsTable);
    $CI->db->group_start();
    foreach ($clientPhoneCols as $col) {
        foreach ($variants as $variant) {
            $CI->db->or_where($col, $variant);
        }
        if ($last10 !== '') {
            $CI->db->or_like($col, $last10, 'before');
        }
    }
    $CI->db->group_end();
    $CI->db->order_by('userid', 'ASC');
    $CI->db->limit(40);
    foreach ($CI->db->get()->result_array() as $row) {
        foreach ($clientPhoneCols as $col) {
            if (siba_leads_normalize_phone($row[$col] ?? '') === $normalized) {
                $matches[] = (int) $row['userid'];
                break;
            }
        }
    }

    if ($CI->db->table_exists($contactsTable) && $CI->db->field_exists('phonenumber', $contactsTable)) {
        $contactPhoneCols = ['phonenumber'];
        foreach (['contact_phonenumber2', 'contact_telephone'] as $col) {
            if ($CI->db->field_exists($col, $contactsTable)) {
                $contactPhoneCols[] = $col;
            }
        }

        $CI->db->select('userid, ' . implode(', ', $contactPhoneCols));
        $CI->db->from($contactsTable);
        $CI->db->group_start();
        foreach ($contactPhoneCols as $col) {
            foreach ($variants as $variant) {
                $CI->db->or_where($col, $variant);
            }
            if ($last10 !== '') {
                $CI->db->or_like($col, $last10, 'before');
            }
        }
        $CI->db->group_end();
        $CI->db->order_by('userid', 'ASC');
        $CI->db->limit(40);
        foreach ($CI->db->get()->result_array() as $row) {
            foreach ($contactPhoneCols as $col) {
                if (siba_leads_normalize_phone($row[$col] ?? '') === $normalized) {
                    $matches[] = (int) $row['userid'];
                    break;
                }
            }
        }
    }

    $matches = array_values(array_filter(array_unique(array_map('intval', $matches))));
    if ($matches === []) {
        return 0;
    }
    sort($matches, SORT_NUMERIC);

    return (int) $matches[0];
}

/**
 * Annotate kanban/list lead rows with related_client_userid (phone match on clients/contacts).
 *
 * @param array<int, array<string, mixed>> $leads
 * @return array<int, array<string, mixed>>
 */
function siba_leads_annotate_related_customers(array $leads): array
{
    if ($leads === []) {
        return $leads;
    }

    $needByNorm = [];
    foreach ($leads as $idx => $lead) {
        $leads[$idx]['related_client_userid'] = 0;

        $formalClient = (int) ($lead['client_userid'] ?? 0);
        if ($formalClient > 0 || (string) ($lead['is_lead_client'] ?? '0') !== '0') {
            // Already converted — formal client badge / URL take precedence.
            continue;
        }

        $phone = $lead['phonenumber'] ?? '';
        $norm  = siba_leads_normalize_phone($phone);
        if ($norm === '') {
            continue;
        }
        $needByNorm[$norm][] = $idx;
    }

    if ($needByNorm === []) {
        return $leads;
    }

    $cache = siba_leads_batch_find_client_ids_by_phones(array_keys($needByNorm));
    foreach ($needByNorm as $norm => $indexes) {
        $clientId = (int) ($cache[$norm] ?? 0);
        foreach ($indexes as $idx) {
            $leads[$idx]['related_client_userid'] = $clientId;
        }
    }

    return $leads;
}

/** Same digit-suffix predicate as LIKE '%suffix', grouped by length to avoid repeated LIKE scans. */
function siba_leads_phone_suffix_where($db, string $column, array $suffixes): void
{
    $groups = [];
    foreach ($suffixes as $suffix) {
        $suffix = (string) $suffix;
        // Preserve the legacy predicate for unexpected input outside normalized digit phones.
        if (!preg_match('/^[0-9]+$/D', $suffix)) {
            $db->or_like($column, $suffix, 'before');
            continue;
        }
        $groups[strlen($suffix)][] = $db->escape($suffix);
    }
    foreach ($groups as $length => $values) {
        $db->or_where('RIGHT(' . $db->escape_identifiers($column) . ',' . (int) $length . ') IN (' . implode(',', $values) . ')', null, false);
    }
}

/** Preserve native string/numeric IN comparison while allowing homogeneous-list lookup. */
function siba_leads_phone_variants_where($db, string $column, array $variants): void
{
    $strings = [];
    $numbers = [];
    foreach ($variants as $variant) {
        if (is_int($variant) || is_float($variant)) $numbers[] = (string) $variant;
        else $strings[] = (string) $variant;
    }
    if ($strings) $db->or_where_in($column, $strings);
    // Comparing a VARCHAR phone to a native numeric literal uses floating point conversion.
    if ($numbers) $db->or_where('(0e0 + ' . $db->escape_identifiers($column) . ') IN (' . implode(',', $numbers) . ')', null, false);
}

/**
 * Batch map normalized phones → lowest matching client userid.
 *
 * @param string[] $normalizedPhones
 * @return array<string, int>
 */
function siba_leads_batch_find_client_ids_by_phones(array $normalizedPhones): array
{
    $normalizedPhones = array_values(array_unique(array_filter(array_map('strval', $normalizedPhones))));
    if ($normalizedPhones === []) {
        return [];
    }

    $CI = &get_instance();
    $clientsTable  = db_prefix() . 'clients';
    $contactsTable = db_prefix() . 'contacts';
    if (!$CI->db->table_exists($clientsTable)) {
        return [];
    }

    $last10s = [];
    $variants = [];
    foreach ($normalizedPhones as $norm) {
        $last10 = strlen($norm) >= 10 ? substr($norm, -10) : ltrim($norm, '0');
        if ($last10 !== '') {
            $last10s[$last10] = true;
            foreach (siba_leads_phone_variants($norm) as $variant) {
                $variants[$variant] = true;
            }
        }
    }
    $last10List = array_keys($last10s);
    $variantList = array_keys($variants);
    if ($variantList === [] && $last10List === []) {
        return [];
    }

    $clientPhoneCols = ['phonenumber'];
    foreach (['phonenumber2', 'telephone'] as $col) {
        if ($CI->db->field_exists($col, $clientsTable)) {
            $clientPhoneCols[] = $col;
        }
    }

    $map = array_fill_keys($normalizedPhones, 0);

    $CI->db->select('userid, ' . implode(', ', $clientPhoneCols));
    $CI->db->from($clientsTable);
    $CI->db->group_start();
    foreach ($clientPhoneCols as $col) {
        if ($variantList !== []) {
            siba_leads_phone_variants_where($CI->db, $col, $variantList);
        }
        siba_leads_phone_suffix_where($CI->db, $col, $last10List);
    }
    $CI->db->group_end();
    $CI->db->order_by('userid', 'ASC');
    $CI->db->limit(max(80, count($normalizedPhones) * 8));
    foreach ($CI->db->get()->result_array() as $row) {
        $userid = (int) $row['userid'];
        foreach ($clientPhoneCols as $col) {
            $norm = siba_leads_normalize_phone($row[$col] ?? '');
            if ($norm !== '' && isset($map[$norm]) && $map[$norm] === 0) {
                $map[$norm] = $userid;
            }
        }
    }

    if ($CI->db->table_exists($contactsTable) && $CI->db->field_exists('phonenumber', $contactsTable)) {
        $contactPhoneCols = ['phonenumber'];
        foreach (['contact_phonenumber2', 'contact_telephone'] as $col) {
            if ($CI->db->field_exists($col, $contactsTable)) {
                $contactPhoneCols[] = $col;
            }
        }

        $CI->db->select('userid, ' . implode(', ', $contactPhoneCols));
        $CI->db->from($contactsTable);
        $CI->db->group_start();
        foreach ($contactPhoneCols as $col) {
            if ($variantList !== []) {
                siba_leads_phone_variants_where($CI->db, $col, $variantList);
            }
            siba_leads_phone_suffix_where($CI->db, $col, $last10List);
        }
        $CI->db->group_end();
        $CI->db->order_by('userid', 'ASC');
        $CI->db->limit(max(80, count($normalizedPhones) * 8));
        foreach ($CI->db->get()->result_array() as $row) {
            $userid = (int) $row['userid'];
            foreach ($contactPhoneCols as $col) {
                $norm = siba_leads_normalize_phone($row[$col] ?? '');
                if ($norm !== '' && isset($map[$norm]) && $map[$norm] === 0) {
                    $map[$norm] = $userid;
                }
            }
        }
    }

    return $map;
}

/**
 * Annotate kanban/list lead rows with phone_duplicate + phone_duplicate_of.
 *
 * @param array<int, array<string, mixed>> $leads
 * @return array<int, array<string, mixed>>
 */
function siba_leads_annotate_phone_duplicates(array $leads): array
{
    if ($leads === []) {
        return $leads;
    }

    $byNorm = [];
    $excludeIds = [];
    foreach ($leads as $idx => $lead) {
        $norm = siba_leads_normalize_phone($lead['phonenumber'] ?? '');
        $leads[$idx]['phone_duplicate'] = false;
        $leads[$idx]['phone_duplicate_of'] = 0;
        if ($norm === '') {
            continue;
        }
        $byNorm[$norm][] = $idx;
        $id = (int) ($lead['id'] ?? 0);
        if ($id > 0) {
            $excludeIds[$id] = true;
        }
    }

    // Within the current page first.
    foreach ($byNorm as $norm => $indexes) {
        if (count($indexes) < 2) {
            continue;
        }
        $minId = null;
        foreach ($indexes as $idx) {
            $id = (int) ($leads[$idx]['id'] ?? 0);
            if ($minId === null || ($id > 0 && $id < $minId)) {
                $minId = $id;
            }
        }
        foreach ($indexes as $idx) {
            $id = (int) ($leads[$idx]['id'] ?? 0);
            if ($minId && $id !== $minId) {
                $leads[$idx]['phone_duplicate'] = true;
                $leads[$idx]['phone_duplicate_of'] = $minId;
            }
        }
    }

    if ($byNorm === []) {
        return $leads;
    }

    // One batched DB pass for siblings outside this page.
    $oldestOutside = siba_leads_batch_oldest_sibling_lead_ids_by_phones(array_keys($byNorm), array_keys($excludeIds));
    foreach ($byNorm as $norm => $indexes) {
        $outsideId = (int) ($oldestOutside[$norm] ?? 0);
        if ($outsideId < 1) {
            continue;
        }
        foreach ($indexes as $idx) {
            $id = (int) ($leads[$idx]['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            // Oldest sibling overall wins as phone_duplicate_of.
            $currentOf = (int) ($leads[$idx]['phone_duplicate_of'] ?? 0);
            $bestOf = $outsideId;
            if ($currentOf > 0 && $currentOf < $bestOf) {
                $bestOf = $currentOf;
            }
            if ($id !== $bestOf) {
                $leads[$idx]['phone_duplicate'] = true;
                $leads[$idx]['phone_duplicate_of'] = $bestOf;
            }
        }
    }

    return $leads;
}

/**
 * Batch: for each normalized phone, oldest lead id outside $excludeIds (or 0).
 *
 * @param string[] $normalizedPhones
 * @param int[] $excludeIds
 * @return array<string, int>
 */
function siba_leads_batch_oldest_sibling_lead_ids_by_phones(array $normalizedPhones, array $excludeIds = []): array
{
    $normalizedPhones = array_values(array_unique(array_filter(array_map('strval', $normalizedPhones))));
    if ($normalizedPhones === []) {
        return [];
    }

    $CI = &get_instance();
    $table = db_prefix() . 'leads';
    if (!$CI->db->table_exists($table) || !$CI->db->field_exists('phonenumber', $table)) {
        return [];
    }

    $last10s = [];
    $variants = [];
    foreach ($normalizedPhones as $norm) {
        $last10 = strlen($norm) >= 10 ? substr($norm, -10) : ltrim($norm, '0');
        if ($last10 !== '') {
            $last10s[$last10] = true;
            foreach (siba_leads_phone_variants($norm) as $variant) {
                $variants[$variant] = true;
            }
        }
    }
    $last10List = array_keys($last10s);
    $variantList = array_keys($variants);
    if ($variantList === [] && $last10List === []) {
        return [];
    }

    $CI->db->select('id, phonenumber');
    $CI->db->from($table);
    $excludeIds = array_values(array_filter(array_map('intval', $excludeIds)));
    if ($excludeIds !== []) {
        $CI->db->where_not_in('id', $excludeIds);
    }
    $CI->db->group_start();
    if ($variantList !== []) {
        $CI->db->or_where_in('phonenumber', $variantList);
    }
    foreach ($last10List as $last10) {
        $CI->db->or_like('phonenumber', $last10, 'before');
    }
    $CI->db->group_end();
    $CI->db->order_by('id', 'ASC');
    $CI->db->limit(max(100, count($normalizedPhones) * 10));
    $rows = $CI->db->get()->result_array();

    $map = array_fill_keys($normalizedPhones, 0);
    foreach ($rows as $row) {
        $norm = siba_leads_normalize_phone($row['phonenumber'] ?? '');
        if ($norm === '' || !isset($map[$norm]) || $map[$norm] !== 0) {
            continue;
        }
        $map[$norm] = (int) $row['id'];
    }

    return $map;
}

function siba_leads_duplicate_phone_message($existingId = 0): string
{
    $msg = _l('siba_leads_duplicate_phone');
    if ($existingId > 0) {
        $msg .= ' (#' . (int) $existingId . ')';
    }

    return $msg;
}

function siba_leads_duplicate_phone_warning_message($existingId = 0): string
{
    $msg = _l('siba_leads_duplicate_phone_warning');
    if ($existingId > 0) {
        $msg .= ' (#' . (int) $existingId . ')';
    }

    return $msg;
}

/**
 * @deprecated Soft policy: duplicates are allowed. Kept as no-op for old callers.
 */
function siba_leads_abort_if_duplicate_phone($phone, $excludeId = 0): void
{
    // Intentionally empty — duplicate phones are allowed; UI/API warn with a badge.
}

/**
 * Admin: soft phone check (always allow save). validate_phone returns JSON for warning UI.
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
        $info    = siba_leads_phone_duplicate_info($CI->input->post('phonenumber'), $exclude);
        // Soft policy: always "valid" for jQuery Validate; clients that expect bool still get true.
        // Richer payload for the warning UI:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'           => true,
            'available'    => !$info['is_duplicate'],
            'is_duplicate' => $info['is_duplicate'],
            'existing_id'  => $info['existing_id'] ?: null,
            'message'      => $info['is_duplicate']
                ? siba_leads_duplicate_phone_warning_message($info['existing_id'])
                : '',
        ], JSON_UNESCAPED_UNICODE);
        die;
    }

    // Do not abort leads/lead POST — duplicates are allowed.
}
