<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Legacy Gravity Forms custom field IDs → native tblleads columns.
 *
 * @return array<string, string> field id => column
 */
function siba_leads_website_cf_column_map(): array
{
    return [
        '5'  => 'form_identifier',
        '6'  => 'form_source_link',
        '7'  => 'form_source_page_title',
        '8'  => 'sms_verification',
        '9'  => 'demo_request_tracking_id',
        '10' => 'user_package_number',
        '11' => 'request_type',
    ];
}

/**
 * Description / meta keys that mirror native columns (skip when upserting meta).
 *
 * @return string[]
 */
function siba_leads_website_native_meta_keys(): array
{
    return [
        'form_identifier',
        'form_source_link',
        'form_source_page_title',
        'sms_verification',
        'demo_request_tracking_id',
        'user_package_number',
        'request_type',
        'form_title',
        'job_id',
        // Common Persian labels from website description JSON
        'شناسه پیگیری',
        'شناسه پیگیری درخواست فرم',
        'شناسه فرم',
        'لینک صفحه مرجع ثبت فرم',
        'صفحه مرجع ثبت فرم',
        'نوع درخواست',
        'شماره بسته',
    ];
}

/**
 * Best-effort request body for API create/update (JSON or $_POST).
 *
 * @return array<string, mixed>
 */
function siba_leads_website_request_payload(): array
{
    if (function_exists('siba_api_request_body')) {
        $body = siba_api_request_body();
        if (is_array($body) && $body !== []) {
            return $body;
        }
    }

    $CI = &get_instance();
    $raw = $CI->input->raw_input_stream;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && $decoded !== []) {
            return $decoded;
        }
    }

    if (!empty($_POST) && is_array($_POST)) {
        return $_POST;
    }

    return [];
}

/**
 * Extract leads custom-field map from nested or flat payload shapes.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed> field id => value
 */
function siba_leads_extract_leads_custom_fields(array $input): array
{
    $out = [];

    if (isset($input['custom_fields']) && is_array($input['custom_fields'])) {
        $leads = $input['custom_fields']['leads'] ?? null;
        if (is_array($leads)) {
            foreach ($leads as $id => $value) {
                $out[(string) $id] = $value;
            }
        }
    }

    // Flat WP form keys sometimes survive as literal keys.
    foreach ($input as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if (preg_match('/^custom_fields\[leads\]\[(\d+)\]$/', $key, $m)) {
            $out[$m[1]] = $value;
        }
    }

    return $out;
}

/**
 * Normalize SMS verification flag to "0" or "1".
 *
 * @param mixed $value
 */
function siba_leads_normalize_sms_verification($value): string
{
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    $raw = strtolower(trim((string) $value));
    if (in_array($raw, ['1', 'true', 'yes', 'on', 'verified'], true)) {
        return '1';
    }

    return '0';
}

/**
 * Normalize tags to a comma-separated string (or keep array for App_tags).
 *
 * @param mixed $tags
 * @return string|array
 */
function siba_leads_normalize_tags_value($tags)
{
    if (!is_array($tags)) {
        return $tags;
    }

    $clean = [];
    foreach ($tags as $tag) {
        if (is_array($tag)) {
            continue;
        }
        $tag = trim((string) $tag);
        if ($tag !== '') {
            $clean[] = $tag;
        }
    }

    return implode(',', $clean);
}

/**
 * Normalize description to a string (JSON when array).
 *
 * @param mixed $description
 */
function siba_leads_normalize_description_value($description): string
{
    if (is_array($description)) {
        return (string) json_encode($description, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    if (is_object($description)) {
        return (string) json_encode($description, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    return (string) $description;
}

/**
 * Description/meta keys that duplicate native website form columns.
 *
 * @return string[]
 */
function siba_leads_website_duplicate_detail_keys(): array
{
    return [
        'شناسه پیگیری',
        'شناسه پیگیری درخواست فرم',
        'شناسه فرم',
        'صفحه مرجع ثبت فرم',
        'لینک صفحه مرجع ثبت فرم',
        'لینک مرجع فرم',
        'عنوان صفحه مرجع فرم',
        'نام و نام خانوادگی',
        'نام',
        'شماره تماس',
        'موبایل',
        'تلفن',
        'نام مجموعه',
        'ایمیل',
        'آدرس ایمیل',
        'فعالیت کسب و کار',
        'form_id',
        'gravity_entry_id',
        'form_entry',
        'track',
        'tracking',
        'ref_title',
        'post_link',
        'post_id',
        'form_identifier',
        'form_source_link',
        'form_source_page_title',
        'demo_request_tracking_id',
        'sms_verification',
        'user_package_number',
        'request_type',
        'form_title',
        'job_id',
        'package',
        'شماره بسته',
        'شماره بسته کاربر',
        'نوع درخواست',
        'شناسه پیگیری درخواست دمو',
        'name',
        'affiliate_name',
        'mobile',
        'affiliate_mobile',
        'phonenumber',
        'phone',
        'tel',
        'company',
        'email',
        'activity',
        'unique_id',
    ];
}

/**
 * Filter description/meta rows that already appear as native form fields.
 *
 * @param array<string, string> $map
 * @param array<string, mixed>  $native
 * @return array<string, string>
 */
function siba_leads_unique_form_detail_rows(array $map, array $native = []): array
{
    $blocked = [];
    foreach (siba_leads_website_duplicate_detail_keys() as $key) {
        $blocked[mb_strtolower(trim((string) $key))] = true;
    }

    $nativeValues = [];
    foreach ([
        'form_identifier',
        'form_source_link',
        'form_source_page_title',
        'demo_request_tracking_id',
        'user_package_number',
        'request_type',
        'name',
        'phonenumber',
        'email',
        'company',
    ] as $column) {
        $value = trim((string) ($native[$column] ?? ''));
        if ($value !== '') {
            $nativeValues[mb_strtolower($value)] = true;
        }
    }

    $out = [];
    foreach ($map as $key => $value) {
        $key = trim((string) $key);
        $value = trim((string) $value);
        if ($key === '' || $value === '') {
            continue;
        }
        if (isset($blocked[mb_strtolower($key)])) {
            continue;
        }
        if (isset($nativeValues[mb_strtolower($value)])) {
            continue;
        }
        $out[$key] = $value;
    }

    return $out;
}

/**
 * Parse description JSON (or associative text) into key => value.
 *
 * @return array<string, string>
 */
function siba_leads_parse_description_map($description): array
{
    if (is_array($description)) {
        $map = [];
        foreach ($description as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $map[(string) $key] = trim((string) $value);
            }
        }

        return $map;
    }

    $raw = trim((string) $description);
    if ($raw === '') {
        return [];
    }

    // leads_model may nl2br() description when API constant is missing.
    $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw);
    $raw = strip_tags($raw);
    $raw = trim($raw);

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $map = [];
        foreach ($decoded as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $map[(string) $key] = trim((string) $value);
            }
        }

        return $map;
    }

    return [];
}

/**
 * Map website / Gravity Forms payload into native Siba lead columns.
 * Keeps custom_fields for dual-write.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function siba_leads_normalize_website_payload(array $input): array
{
    if ($input === []) {
        return $input;
    }

    $CI = &get_instance();
    $table = db_prefix() . 'leads';
    $columns = $CI->db->table_exists($table) ? $CI->db->list_fields($table) : [];

    $cf = siba_leads_extract_leads_custom_fields($input);
    foreach (siba_leads_website_cf_column_map() as $fieldId => $column) {
        if (!array_key_exists($fieldId, $cf)) {
            continue;
        }
        if ($columns !== [] && !in_array($column, $columns, true)) {
            continue;
        }
        // Prefer explicit native key if already provided.
        if (array_key_exists($column, $input) && $input[$column] !== '' && $input[$column] !== null) {
            continue;
        }
        $value = $cf[$fieldId];
        if ($column === 'sms_verification') {
            $value = siba_leads_normalize_sms_verification($value);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $value = trim((string) $value);
        }
        $input[$column] = $value;
    }

    if (array_key_exists('sms_verification', $input)) {
        $input['sms_verification'] = siba_leads_normalize_sms_verification($input['sms_verification']);
    }

    if (array_key_exists('tags', $input)) {
        $input['tags'] = siba_leads_normalize_tags_value($input['tags']);
    }

    if (array_key_exists('description', $input)) {
        $input['description'] = siba_leads_normalize_description_value($input['description']);
    }

    return $input;
}

/**
 * before_lead_added filter: normalize Gravity Forms / website API payloads.
 *
 * @param mixed $data
 * @return mixed
 */
function siba_leads_filter_website_payload($data)
{
    if (!is_array($data)) {
        return $data;
    }

    return siba_leads_normalize_website_payload($data);
}

/**
 * After create: persist description extras into siba_leads_meta.
 *
 * @param int|string $leadId
 */
function siba_leads_action_website_lead_created($leadId): void
{
    $leadId = (int) $leadId;
    if ($leadId < 1) {
        return;
    }

    $payload = siba_leads_website_request_payload();
    if ($payload === []) {
        $CI = &get_instance();
        if (!class_exists('leads_model', false)) {
            $CI->load->model('leads_model');
        }
        $lead = $CI->leads_model->get($leadId);
        if ($lead && !empty($lead->description)) {
            siba_leads_sync_website_meta($leadId, (string) $lead->description, []);
        }

        return;
    }

    $normalized = siba_leads_normalize_website_payload($payload);
    siba_leads_sync_website_meta(
        $leadId,
        $normalized['description'] ?? '',
        $normalized
    );
}

/**
 * After update: map CF → native columns (generic /api/leads PUT) and sync meta.
 *
 * @param int|string $leadId
 */
function siba_leads_action_website_lead_updated($leadId): void
{
    $leadId = (int) $leadId;
    if ($leadId < 1) {
        return;
    }

    // Avoid recursion when this handler updates the lead again.
    static $syncing = [];
    if (!empty($syncing[$leadId])) {
        return;
    }

    $payload = siba_leads_website_request_payload();
    if ($payload === [] || !siba_leads_payload_looks_like_website($payload)) {
        return;
    }

    $normalized = siba_leads_normalize_website_payload($payload);
    $CI = &get_instance();
    $table = db_prefix() . 'leads';
    if (!$CI->db->table_exists($table)) {
        return;
    }

    $columns = $CI->db->list_fields($table);
    $update = [];
    foreach (array_values(siba_leads_website_cf_column_map()) as $column) {
        if (!in_array($column, $columns, true)) {
            continue;
        }
        if (!array_key_exists($column, $normalized)) {
            continue;
        }
        $update[$column] = $normalized[$column];
    }

    if ($update !== []) {
        $syncing[$leadId] = true;
        $CI->db->where('id', $leadId)->update($table, $update);
        unset($syncing[$leadId]);
    }

    siba_leads_sync_website_meta(
        $leadId,
        $normalized['description'] ?? '',
        $normalized
    );
}

/**
 * Detect website / Gravity Forms style payloads (vs admin lead form saves).
 *
 * @param array<string, mixed> $payload
 */
function siba_leads_payload_looks_like_website(array $payload): bool
{
    $cf = siba_leads_extract_leads_custom_fields($payload);
    foreach (array_keys(siba_leads_website_cf_column_map()) as $id) {
        if (array_key_exists($id, $cf)) {
            return true;
        }
    }

    foreach (array_values(siba_leads_website_cf_column_map()) as $column) {
        if (array_key_exists($column, $payload)) {
            return true;
        }
    }

    if (!empty($payload['meta']) && is_array($payload['meta'])) {
        return true;
    }

    if (defined('API') && array_key_exists('description', $payload)) {
        $map = siba_leads_parse_description_map($payload['description']);
        if ($map !== []) {
            return true;
        }
    }

    return false;
}

/**
 * Upsert description/extra key-values into tblsiba_leads_meta.
 *
 * @param int                  $leadId
 * @param mixed                $description
 * @param array<string, mixed> $normalized
 */
function siba_leads_sync_website_meta(int $leadId, $description, array $normalized = []): void
{
    if ($leadId < 1) {
        return;
    }

    $CI = &get_instance();
    $table = db_prefix() . 'siba_leads_meta';
    if (!$CI->db->table_exists($table)) {
        return;
    }

    $map = siba_leads_parse_description_map($description);

    // Optional explicit extras bag from API clients.
    if (!empty($normalized['meta']) && is_array($normalized['meta'])) {
        foreach ($normalized['meta'] as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $map[(string) $key] = trim((string) $value);
            }
        }
    }

    $skip = array_flip(siba_leads_website_native_meta_keys());
    $staffId = get_staff_user_id();
    $staffId = ($staffId !== false && $staffId !== null && $staffId !== '') ? (int) $staffId : null;
    $now = date('Y-m-d H:i:s');

    foreach ($map as $key => $value) {
        $key = trim((string) $key);
        if ($key === '' || isset($skip[$key])) {
            continue;
        }
        if ($value === '' || $value === null) {
            continue;
        }

        $existing = $CI->db->where(['lead_id' => $leadId, 'meta_key' => $key])
            ->get($table)
            ->row();

        if ($existing) {
            $CI->db->where('id', (int) $existing->id)->update($table, [
                'meta_value' => (string) $value,
                'staff_id'   => $staffId,
            ]);
        } else {
            $CI->db->insert($table, [
                'lead_id'    => $leadId,
                'meta_key'   => $key,
                'meta_value' => (string) $value,
                'dateadded'  => $now,
                'staff_id'   => $staffId,
            ]);
        }
    }
}

/**
 * Load meta rows for a lead.
 *
 * @return array<int, array{meta_key:string, meta_value:string}>
 */
function siba_leads_get_lead_meta(int $leadId): array
{
    if ($leadId < 1) {
        return [];
    }

    $CI = &get_instance();
    $table = db_prefix() . 'siba_leads_meta';
    if (!$CI->db->table_exists($table)) {
        return [];
    }

    $rows = $CI->db->select('meta_key, meta_value')
        ->where('lead_id', $leadId)
        ->order_by('id', 'ASC')
        ->get($table)
        ->result_array();

    return is_array($rows) ? $rows : [];
}

/**
 * Whether SMS verification flag is verified.
 *
 * @param mixed $value
 */
function siba_leads_is_sms_verified($value): bool
{
    return siba_leads_normalize_sms_verification($value) === '1';
}

/**
 * Form-meta payload for the lead modal seed.
 *
 * @param object|array|null $lead
 * @return array<string, mixed>
 */
function siba_leads_resolve_lead_form_meta($lead): array
{
    $get = static function ($row, string $key) {
        if (is_object($row)) {
            return $row->{$key} ?? '';
        }
        if (is_array($row)) {
            return $row[$key] ?? '';
        }

        return '';
    };

    $leadId = (int) $get($lead, 'id');
    $description = (string) $get($lead, 'description');
    $sms = (string) $get($lead, 'sms_verification');

    $native = [
        'form_identifier'          => (string) $get($lead, 'form_identifier'),
        'form_source_link'         => (string) $get($lead, 'form_source_link'),
        'form_source_page_title'   => (string) $get($lead, 'form_source_page_title'),
        'demo_request_tracking_id' => (string) $get($lead, 'demo_request_tracking_id'),
        'user_package_number'      => (string) $get($lead, 'user_package_number'),
        'request_type'             => (string) $get($lead, 'request_type'),
        'name'                     => (string) $get($lead, 'name'),
        'phonenumber'              => (string) $get($lead, 'phonenumber'),
        'email'                    => (string) $get($lead, 'email'),
        'company'                  => (string) $get($lead, 'company'),
    ];

    $descriptionMap = siba_leads_parse_description_map($description);
    $descriptionMap = siba_leads_unique_form_detail_rows($descriptionMap, $native);

    return [
        'form_identifier'          => $native['form_identifier'],
        'form_source_link'         => $native['form_source_link'],
        'form_source_page_title'   => $native['form_source_page_title'],
        'sms_verification'         => $sms,
        'sms_verified'             => siba_leads_is_sms_verified($sms),
        'demo_request_tracking_id' => $native['demo_request_tracking_id'],
        'user_package_number'      => $native['user_package_number'],
        'request_type'             => $native['request_type'],
        'description'              => $description,
        'description_map'          => $descriptionMap,
        'meta'                     => siba_leads_get_lead_meta($leadId),
    ];
}

/**
 * Seed window.sibaLeadFormMetaSeed for the lead modal.
 *
 * @param int|string $leadId
 */
function siba_leads_lead_modal_form_meta_seed($leadId): void
{
    $leadId = (int) $leadId;
    if ($leadId < 1) {
        echo '<script>window.sibaLeadFormMetaSeed = null;</script>';

        return;
    }

    $CI = &get_instance();
    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    $lead = $CI->leads_model->get($leadId);
    if (!$lead) {
        echo '<script>window.sibaLeadFormMetaSeed = null;</script>';

        return;
    }

    $meta = siba_leads_resolve_lead_form_meta($lead);
    echo '<script>window.sibaLeadFormMetaSeed = ' . json_encode($meta, JSON_UNESCAPED_UNICODE) . ';</script>';
}
