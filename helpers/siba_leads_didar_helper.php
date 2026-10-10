<?php

defined('BASEPATH') or exit('No direct script access allowed');

define('SIBA_LEADS_DIDAR_BASE_URL_OPTION', 'siba_leads_didar_base_url');
define('SIBA_LEADS_DIDAR_API_KEY_OPTION', 'siba_leads_didar_api_key');
define('SIBA_LEADS_DIDAR_PIPELINE_OPTION', 'siba_leads_didar_pipeline_id');
define('SIBA_LEADS_DIDAR_SOURCE_OPTION', 'siba_leads_didar_import_source_id');
define('SIBA_LEADS_DIDAR_SSL_VERIFY_OPTION', 'siba_leads_didar_ssl_verify');
define('SIBA_LEADS_DIDAR_IMPORT_RUN_OPTION', 'siba_leads_didar_import_run');
define('SIBA_LEADS_DIDAR_STAFF_MAP_OPTION', 'siba_leads_didar_staff_map_json');
define('SIBA_LEADS_DIDAR_STAFF_FIELD_SLUG_OPTION', 'siba_leads_didar_staff_field_slug');

/**
 * Didar pipeline stage titles allowed for import (only these become Perfex statuses / leads).
 *
 * @return string[]
 */
function siba_leads_didar_allowed_stage_titles(): array
{
    return [
        'لید خام',
        'بی پاسخ',
        'سوال یا آموزش',
        'تصمیم ارسال پیش فاکتور',
        'پیگیری واریز',
    ];
}

/**
 * Normalize stage title for comparison (trim + Arabic/Persian ي/ك variants).
 */
function siba_leads_didar_normalize_stage_title(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return '';
    }
    // Unify Arabic Yeh/Kaf with Persian forms.
    $title = str_replace(['ي', 'ك'], ['ی', 'ک'], $title);
    $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

    return function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
}

/**
 * Whether a Didar stage title is in the import allow-list.
 */
function siba_leads_didar_is_allowed_stage_title(string $title): bool
{
    $norm = siba_leads_didar_normalize_stage_title($title);
    if ($norm === '') {
        return false;
    }
    foreach (siba_leads_didar_allowed_stage_titles() as $allowed) {
        if (siba_leads_didar_normalize_stage_title($allowed) === $norm) {
            return true;
        }
    }

    return false;
}

/**
 * Ensure temporary tblstaff.didar_id column exists.
 */
function siba_leads_didar_ensure_staff_didar_id_column(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $CI    = &get_instance();
    $table = db_prefix() . 'staff';
    if (!$CI->db->table_exists($table) || $CI->db->field_exists('didar_id', $table)) {
        return;
    }

    $CI->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `didar_id` VARCHAR(64) NULL DEFAULT NULL');
}

/**
 * Map from staff.didar_id column → staffid.
 *
 * @return array<string,int>
 */
function siba_leads_didar_staff_map_from_didar_id_column(): array
{
    siba_leads_didar_ensure_staff_didar_id_column();
    $CI  = &get_instance();
    $map = [];

    if (!$CI->db->field_exists('didar_id', db_prefix() . 'staff')) {
        return $map;
    }

    $rows = $CI->db->select('staffid, didar_id')
        ->where('didar_id IS NOT NULL', null, false)
        ->where('didar_id !=', '')
        ->get(db_prefix() . 'staff')
        ->result_array();

    foreach ($rows as $row) {
        siba_leads_didar_register_staff_key($map, (string) ($row['didar_id'] ?? ''), (int) ($row['staffid'] ?? 0));
    }

    return $map;
}

/**
 * Write Didar user Id onto matching staff.didar_id (email / m_user / Code).
 * Does not overwrite a non-empty didar_id unless $overwrite is true.
 *
 * @param array<int,array<string,mixed>> $didarUsers
 * @return array{updated:int,skipped:int,unmatched:int}
 */
function siba_leads_didar_sync_staff_didar_ids(array $didarUsers, bool $overwrite = false): array
{
    siba_leads_didar_ensure_staff_didar_id_column();
    $CI = &get_instance();
    $stats = ['updated' => 0, 'skipped' => 0, 'unmatched' => 0];

    if (!$CI->db->field_exists('didar_id', db_prefix() . 'staff')) {
        return $stats;
    }

    $hasMUser = $CI->db->field_exists('m_user', db_prefix() . 'staff');
    $staffRows = $CI->db->select('staffid, email, didar_id' . ($hasMUser ? ', m_user' : ''))
        ->get(db_prefix() . 'staff')
        ->result_array();

    $byEmail = [];
    $byMUser = [];
    foreach ($staffRows as $row) {
        $sid = (int) ($row['staffid'] ?? 0);
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email !== '') {
            $byEmail[$email] = $row;
        }
        if ($hasMUser) {
            $mu = (int) ($row['m_user'] ?? 0);
            if ($mu > 0) {
                $byMUser[$mu] = $row;
            }
        }
    }

    foreach ($didarUsers as $user) {
        if (!is_array($user)) {
            continue;
        }

        $didarId = siba_leads_didar_normalize_user_key((string) ($user['Id'] ?? ''));
        if ($didarId === '') {
            $didarId = siba_leads_didar_normalize_user_key((string) ($user['UserId'] ?? ''));
        }
        if ($didarId === '') {
            $stats['unmatched']++;
            continue;
        }

        $match = null;
        $email = strtolower(trim((string) ($user['UserName'] ?? '')));
        if ($email !== '' && isset($byEmail[$email])) {
            $match = $byEmail[$email];
        }
        if ($match === null) {
            $code = (int) ($user['Code'] ?? 0);
            if ($code > 0 && isset($byMUser[$code])) {
                $match = $byMUser[$code];
            }
        }

        if ($match === null) {
            $stats['unmatched']++;
            continue;
        }

        $current = siba_leads_didar_normalize_user_key((string) ($match['didar_id'] ?? ''));
        if ($current !== '' && !$overwrite) {
            $stats['skipped']++;
            continue;
        }
        if ($current === $didarId) {
            $stats['skipped']++;
            continue;
        }

        $CI->db->where('staffid', (int) $match['staffid']);
        $CI->db->update(db_prefix() . 'staff', ['didar_id' => $didarId]);
        $stats['updated']++;
    }

    return $stats;
}

/**
 * Ensure Perfex lead source exists for a Didar «شیوه آشنایی» title.
 */
function siba_leads_didar_ensure_lead_source_by_title(string $title): int
{
    $result = siba_leads_didar_ensure_lead_source_by_title_ex($title);

    return (int) ($result['id'] ?? 0);
}

/**
 * @return array{id:int,created:bool}
 */
function siba_leads_didar_ensure_lead_source_by_title_ex(string $title): array
{
    $title = trim($title);
    if ($title === '') {
        return ['id' => 0, 'created' => false];
    }

    $CI = &get_instance();
    $CI->load->model('leads_model');

    foreach ($CI->leads_model->get_source() as $source) {
        if (strcasecmp(trim((string) ($source['name'] ?? '')), $title) === 0) {
            return ['id' => (int) $source['id'], 'created' => false];
        }
    }

    $id = (int) $CI->leads_model->add_source(['name' => $title]);

    return ['id' => $id, 'created' => $id > 0];
}

/**
 * Sync every Didar «شیوه آشنایی» into Perfex Setup → Leads → Sources (same titles).
 *
 * @param array<int,array<string,mixed>> $didarSources
 * @return array{
 *   map:array<string,int>,
 *   stats:array{created:int,existing:int,skipped:int,total_didar:int,titles:string[]}
 * }
 */
function siba_leads_didar_sync_lead_sources(array $didarSources): array
{
    $map = [];
    $stats = [
        'created'     => 0,
        'existing'    => 0,
        'skipped'     => 0,
        'total_didar' => 0,
        'titles'      => [],
    ];

    foreach ($didarSources as $row) {
        if (!is_array($row)) {
            continue;
        }
        $stats['total_didar']++;

        $id = siba_leads_didar_normalize_user_key((string) ($row['Id'] ?? $row['id'] ?? ''));
        $title = trim((string) (
            $row['Title'] ?? $row['Name'] ?? $row['title'] ?? $row['name'] ?? ''
        ));
        if ($title === '') {
            $stats['skipped']++;
            continue;
        }

        $ensured = siba_leads_didar_ensure_lead_source_by_title_ex($title);
        $perfexId = (int) ($ensured['id'] ?? 0);
        if ($perfexId < 1) {
            $stats['skipped']++;
            continue;
        }

        if (!empty($ensured['created'])) {
            $stats['created']++;
        } else {
            $stats['existing']++;
        }

        $stats['titles'][] = $title;
        if ($id !== '') {
            $map[$id] = $perfexId;
        }
    }

    // Keep generic fallback source available for deals with no SourceId.
    siba_leads_didar_ensure_source_id();

    $stats['titles'] = array_values(array_unique($stats['titles']));

    return ['map' => $map, 'stats' => $stats];
}

/**
 * Build Didar SourceId → Perfex leads_sources.id from API source list.
 *
 * @param array<int,array<string,mixed>> $didarSources
 * @return array<string,int>
 */
function siba_leads_didar_build_source_map(array $didarSources): array
{
    $result = siba_leads_didar_sync_lead_sources($didarSources);

    return $result['map'];
}

/**
 * Resolve Perfex source id for a deal (Didar SourceId / SourceOther).
 *
 * @param array<string,mixed> $deal
 * @param array<string,int>   $sourceMap
 */
function siba_leads_didar_resolve_deal_source_id(array $deal, array $sourceMap, int $fallback = 0): int
{
    $sourceId = siba_leads_didar_normalize_user_key((string) ($deal['SourceId'] ?? ''));
    if ($sourceId !== '' && isset($sourceMap[$sourceId])) {
        return (int) $sourceMap[$sourceId];
    }

    // Nested object from some Didar payloads
    if (!empty($deal['Source']) && is_array($deal['Source'])) {
        $nestedId = siba_leads_didar_normalize_user_key((string) ($deal['Source']['Id'] ?? ''));
        if ($nestedId !== '' && isset($sourceMap[$nestedId])) {
            return (int) $sourceMap[$nestedId];
        }
        $nestedTitle = trim((string) ($deal['Source']['Title'] ?? ''));
        if ($nestedTitle !== '') {
            $id = siba_leads_didar_ensure_lead_source_by_title($nestedTitle);
            if ($id > 0) {
                return $id;
            }
        }
    }

    $other = trim((string) ($deal['SourceOther'] ?? ''));
    if ($other !== '') {
        $id = siba_leads_didar_ensure_lead_source_by_title($other);
        if ($id > 0) {
            return $id;
        }
    }

    return $fallback > 0 ? $fallback : siba_leads_didar_ensure_source_id();
}

/**
 * Normalize Didar user / owner id for map lookup.
 */
function siba_leads_didar_normalize_user_key($id): string
{
    $id = strtolower(trim((string) $id));
    if ($id === '' || $id === '00000000-0000-0000-0000-000000000000') {
        return '';
    }

    return $id;
}

/**
 * @param array<string,int> $map
 */
function siba_leads_didar_register_staff_key(array &$map, string $key, int $staffId): void
{
    $key = siba_leads_didar_normalize_user_key($key);
    if ($key === '' || $staffId < 1) {
        return;
    }
    $map[$key] = $staffId;
}

/**
 * @param array<string,int> $map
 */
function siba_leads_didar_resolve_staff_id($didarUserId, array $map, int $fallback = 0): int
{
    $key = siba_leads_didar_normalize_user_key($didarUserId);
    if ($key !== '' && isset($map[$key])) {
        return (int) $map[$key];
    }

    return $fallback > 0 ? $fallback : 0;
}

/**
 * Manual JSON map from settings: { "didar-uuid": staffid, ... }
 *
 * @return array<string,int>
 */
function siba_leads_didar_manual_staff_map(): array
{
    $raw = get_option(SIBA_LEADS_DIDAR_STAFF_MAP_OPTION);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $didarId => $staffId) {
        siba_leads_didar_register_staff_key($out, (string) $didarId, (int) $staffId);
    }

    return $out;
}

/**
 * Staff custom field values (Didar user UUID) → staffid.
 *
 * @return array<string,int>
 */
function siba_leads_didar_staff_map_from_custom_fields(): array
{
    $CI = &get_instance();
    $map = [];
    $slugNeedle = strtolower(trim((string) get_option(SIBA_LEADS_DIDAR_STAFF_FIELD_SLUG_OPTION)));
    if ($slugNeedle === '') {
        $slugNeedle = 'didar';
    }

    if (!function_exists('get_custom_fields')) {
        $CI->load->helper('custom_fields');
    }

    $fields = get_custom_fields('staff');
    if (!is_array($fields)) {
        return $map;
    }

    $fieldIds = [];
    foreach ($fields as $field) {
        $label = strtolower((string) ($field['name'] ?? ''));
        $slug  = strtolower((string) ($field['slug'] ?? ''));
        if (strpos($label, $slugNeedle) !== false || strpos($slug, $slugNeedle) !== false) {
            $fieldIds[] = (int) ($field['id'] ?? 0);
        }
    }

    if ($fieldIds === []) {
        return $map;
    }

    $CI->db->select('relid, value');
    $CI->db->where('fieldto', 'staff');
    $CI->db->where_in('fieldid', $fieldIds);
    $rows = $CI->db->get(db_prefix() . 'customfieldsvalues')->result_array();

    foreach ($rows as $row) {
        $staffId = (int) ($row['relid'] ?? 0);
        $value   = trim((string) ($row['value'] ?? ''));
        if ($staffId < 1 || $value === '') {
            continue;
        }
        siba_leads_didar_register_staff_key($map, $value, $staffId);
        if (ctype_digit($value)) {
            siba_leads_didar_register_staff_key($map, $value, $staffId);
        }
    }

    return $map;
}

/**
 * tblstaff.m_user (legacy numeric id) when Didar user Code matches.
 *
 * @return array<string,int>
 */
function siba_leads_didar_staff_map_from_m_user(array $didarUsers): array
{
    $CI = &get_instance();
    $map = [];
    if (!$CI->db->field_exists('m_user', db_prefix() . 'staff')) {
        return $map;
    }

    if (!function_exists('api_resolve_staff_by_legacy_m_user')) {
        $helper = APP_MODULES_PATH . 'api/helpers/api_helper.php';
        if (is_file($helper)) {
            require_once $helper;
        }
    }

    foreach ($didarUsers as $user) {
        if (!is_array($user)) {
            continue;
        }
        $code = (int) ($user['Code'] ?? 0);
        if ($code < 1) {
            continue;
        }

        $staffId = 0;
        if (function_exists('api_resolve_staff_by_legacy_m_user')) {
            $resolved = api_resolve_staff_by_legacy_m_user($code);
            if (!empty($resolved['found'])) {
                $staffId = (int) $resolved['staffid'];
            }
        }

        if ($staffId < 1) {
            $row = $CI->db->select('staffid')->where('m_user', $code)->get(db_prefix() . 'staff')->row();
            $staffId = $row ? (int) $row->staffid : 0;
        }

        if ($staffId < 1) {
            continue;
        }

        siba_leads_didar_register_staff_key($map, (string) $code, $staffId);
        siba_leads_didar_register_staff_key($map, (string) ($user['Id'] ?? ''), $staffId);
        siba_leads_didar_register_staff_key($map, (string) ($user['UserId'] ?? ''), $staffId);
    }

    return $map;
}

/**
 * @return array<string,mixed>
 */
function siba_leads_didar_import_run_get(): array
{
    $raw = get_option(SIBA_LEADS_DIDAR_IMPORT_RUN_OPTION);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode((string) $raw, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string,mixed> $run
 */
function siba_leads_didar_import_run_set(array $run): void
{
    update_option(SIBA_LEADS_DIDAR_IMPORT_RUN_OPTION, json_encode($run, JSON_UNESCAPED_UNICODE));
}

function siba_leads_didar_import_run_clear(): void
{
    update_option(SIBA_LEADS_DIDAR_IMPORT_RUN_OPTION, '');
}

/**
 * @return array{level:string,time:string,message:string}
 */
function siba_leads_didar_log_entry(string $level, string $message): array
{
    return [
        'level'   => $level,
        'time'    => date('H:i:s'),
        'message' => $message,
    ];
}

/**
 * When false, cURL skips TLS certificate verification (local / corporate proxy only).
 */
function siba_leads_didar_ssl_verify_enabled(): bool
{
    return get_option(SIBA_LEADS_DIDAR_SSL_VERIFY_OPTION) !== '0';
}

/**
 * Bundled CA file shipped with the API module (helps PHP on Windows).
 */
function siba_leads_didar_cacert_path(): ?string
{
    $candidates = [
        APP_MODULES_PATH . 'api/vendor/rmccue/requests/certificates/cacert.pem',
    ];
    if (defined('FCPATH')) {
        $candidates[] = FCPATH . 'modules/api/vendor/rmccue/requests/certificates/cacert.pem';
    }

    foreach ($candidates as $path) {
        if (is_string($path) && is_readable($path)) {
            return $path;
        }
    }

    return null;
}
define('SIBA_LEADS_DIDAR_META_DEAL', 'didar_deal_id');
define('SIBA_LEADS_DIDAR_META_TASK', 'didar_import_task_id');

/**
 * @return string
 */
function siba_leads_didar_base_url(): string
{
    $url = trim((string) get_option(SIBA_LEADS_DIDAR_BASE_URL_OPTION));
    if ($url === '') {
        $url = 'https://app.didar.me';
    }

    return rtrim($url, '/');
}

/**
 * @return string
 */
function siba_leads_didar_api_key(): string
{
    return trim((string) get_option(SIBA_LEADS_DIDAR_API_KEY_OPTION));
}

/**
 * @param mixed $value
 * @return string
 */
function siba_leads_didar_utc_to_mysql($value): string
{
    $value = trim((string) $value);
    if ($value === '' || strpos($value, '0001-01-01') === 0 || strpos($value, '1930-01-01') === 0) {
        return date('Y-m-d H:i:s');
    }

    try {
        $dt = new DateTime($value);
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));

        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return date('Y-m-d H:i:s');
    }
}

/**
 * @param int $leadId
 * @param string $key
 * @param string $value
 */
function siba_leads_didar_set_meta(int $leadId, string $key, string $value): void
{
    if ($leadId < 1 || $key === '') {
        return;
    }

    $CI = &get_instance();
    $table = db_prefix() . 'siba_leads_meta';
    if (!$CI->db->table_exists($table)) {
        return;
    }

    $CI->db->where('lead_id', $leadId);
    $CI->db->where('meta_key', $key);
    $row = $CI->db->get($table)->row();
    $payload = [
        'meta_value' => $value,
        'dateadded'  => date('Y-m-d H:i:s'),
        'staff_id'   => get_staff_user_id() ?: null,
    ];

    if ($row) {
        $CI->db->where('id', (int) $row->id);
        $CI->db->update($table, $payload);
    } else {
        $payload['lead_id']  = $leadId;
        $payload['meta_key'] = $key;
        $CI->db->insert($table, $payload);
    }
}

/**
 * @param string $dealId
 * @return int
 */
function siba_leads_didar_find_lead_by_deal_id(string $dealId): int
{
    $dealId = trim($dealId);
    if ($dealId === '') {
        return 0;
    }

    $CI = &get_instance();
    $table = db_prefix() . 'siba_leads_meta';
    if (!$CI->db->table_exists($table)) {
        return 0;
    }

    $row = $CI->db->select('lead_id')
        ->where('meta_key', SIBA_LEADS_DIDAR_META_DEAL)
        ->where('meta_value', $dealId)
        ->get($table)
        ->row();

    return $row ? (int) $row->lead_id : 0;
}

/**
 * @param int $leadId
 * @return int
 */
function siba_leads_didar_import_task_id(int $leadId): int
{
    if ($leadId < 1) {
        return 0;
    }

    $CI = &get_instance();
    $table = db_prefix() . 'siba_leads_meta';
    if (!$CI->db->table_exists($table)) {
        return 0;
    }

    $row = $CI->db->select('meta_value')
        ->where('lead_id', $leadId)
        ->where('meta_key', SIBA_LEADS_DIDAR_META_TASK)
        ->get($table)
        ->row();

    return $row ? (int) $row->meta_value : 0;
}

/**
 * Ensure a lead source row for Didar imports.
 *
 * @return int
 */
function siba_leads_didar_ensure_source_id(): int
{
    $stored = (int) get_option(SIBA_LEADS_DIDAR_SOURCE_OPTION);
    if ($stored > 0) {
        $CI = &get_instance();
        $CI->load->model('leads_model');
        if ($CI->leads_model->get_source($stored)) {
            return $stored;
        }
    }

    $CI = &get_instance();
    $CI->load->model('leads_model');
    foreach ($CI->leads_model->get_source() as $source) {
        if (strcasecmp((string) ($source['name'] ?? ''), 'Didar') === 0) {
            update_option(SIBA_LEADS_DIDAR_SOURCE_OPTION, (int) $source['id']);

            return (int) $source['id'];
        }
    }

    $id = (int) $CI->leads_model->add_source(['name' => 'Didar']);
    if ($id > 0) {
        update_option(SIBA_LEADS_DIDAR_SOURCE_OPTION, $id);
    }

    return $id;
}

/**
 * Delete all leads, their related data, and all lead status columns.
 *
 * @return array{leads_deleted:int,statuses_deleted:int}
 */
function siba_leads_didar_wipe_workspace(): array
{
    $CI = &get_instance();
    $CI->load->model('leads_model');

    $leadIds = array_map('intval', array_column(
        $CI->db->select('id')->get(db_prefix() . 'leads')->result_array(),
        'id'
    ));

    $deleted = 0;
    foreach ($leadIds as $leadId) {
        if ($leadId > 0 && $CI->leads_model->delete($leadId)) {
            $deleted++;
        }
    }

    if ($CI->db->table_exists(db_prefix() . 'siba_leads_meta')) {
        $CI->db->empty_table(db_prefix() . 'siba_leads_meta');
    }

    $statusesDeleted = 0;
    $statuses = $CI->leads_model->get_status();
    foreach ($statuses as $status) {
        $sid = (int) ($status['id'] ?? 0);
        if ($sid < 1) {
            continue;
        }
        $result = $CI->leads_model->delete_status($sid);
        if ($result === true) {
            $statusesDeleted++;
        }
    }

    update_option('siba_leads_backlog_status_id', '');
    siba_leads_didar_import_run_clear();
    update_option('leads_default_status', '');
    if (isset($CI->app_object_cache)) {
        $CI->app_object_cache->delete('leads-all-statuses-' . md5(serialize([])));
    }

    return [
        'leads_deleted'    => $deleted,
        'statuses_deleted' => $statusesDeleted,
    ];
}

/**
 * Merge staff-map layer onto $map. Higher-priority layers should be applied later.
 *
 * @param array<string,int> $map
 * @param array<string,int> $layer
 * @param array<string,int> $sources
 * @param string $sourceKey
 * @return array<string,int>
 */
function siba_leads_didar_merge_staff_map_layer(array $map, array $layer, array &$sources, string $sourceKey): array
{
    foreach ($layer as $key => $staffId) {
        $staffId = (int) $staffId;
        if ($staffId < 1) {
            continue;
        }
        $norm = siba_leads_didar_normalize_user_key((string) $key);
        if ($norm === '') {
            continue;
        }
        if (!isset($map[$norm]) || (int) $map[$norm] !== $staffId) {
            $sources[$sourceKey] = (int) ($sources[$sourceKey] ?? 0) + 1;
        }
        $map[$norm] = $staffId;
    }

    return $map;
}

/**
 * Build Didar user id → tblstaff.staffid.
 *
 * Priority (later wins): email → m_user → didar_id column → custom field → manual JSON.
 * Then expand Code ↔ Id ↔ UserId, preferring Code so manual JSON Codes beat email UUID matches.
 *
 * @return array{map:array<string,int>,sources:array<string,int>,sync:array<string,int>,preview:string[]}
 */
function siba_leads_didar_build_staff_map(array $didarUsers, bool $syncColumn = true): array
{
    $CI = &get_instance();
    siba_leads_didar_ensure_staff_didar_id_column();

    $sync = ['updated' => 0, 'skipped' => 0, 'unmatched' => 0];
    if ($syncColumn && $didarUsers !== []) {
        $sync = siba_leads_didar_sync_staff_didar_ids($didarUsers, false);
    }

    $map = [];
    $sources = [
        'didar_id'     => 0,
        'custom_field' => 0,
        'manual_json'  => 0,
        'm_user'       => 0,
        'email'        => 0,
    ];

    // 1) Lowest priority: email match
    $staffRows = $CI->db->select('staffid, email, firstname, lastname')
        ->where('active', 1)
        ->get(db_prefix() . 'staff')
        ->result_array();
    $byEmail = [];
    foreach ($staffRows as $row) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email !== '') {
            $byEmail[$email] = (int) $row['staffid'];
        }
    }
    $emailLayer = [];
    foreach ($didarUsers as $user) {
        if (!is_array($user)) {
            continue;
        }
        $email = strtolower(trim((string) ($user['UserName'] ?? '')));
        $staffId = $email !== '' && isset($byEmail[$email]) ? (int) $byEmail[$email] : 0;
        if ($staffId < 1) {
            continue;
        }
        foreach ([(string) ($user['Id'] ?? ''), (string) ($user['UserId'] ?? ''), (string) ($user['Code'] ?? '')] as $raw) {
            siba_leads_didar_register_staff_key($emailLayer, $raw, $staffId);
        }
    }
    $map = siba_leads_didar_merge_staff_map_layer($map, $emailLayer, $sources, 'email');

    // 2) m_user = Didar Code
    $map = siba_leads_didar_merge_staff_map_layer(
        $map,
        siba_leads_didar_staff_map_from_m_user($didarUsers),
        $sources,
        'm_user'
    );

    // 3) staff.didar_id column
    $map = siba_leads_didar_merge_staff_map_layer(
        $map,
        siba_leads_didar_staff_map_from_didar_id_column(),
        $sources,
        'didar_id'
    );

    // 4) custom field
    $map = siba_leads_didar_merge_staff_map_layer(
        $map,
        siba_leads_didar_staff_map_from_custom_fields(),
        $sources,
        'custom_field'
    );

    // 5) Manual JSON wins (Codes or UUIDs)
    $map = siba_leads_didar_merge_staff_map_layer(
        $map,
        siba_leads_didar_manual_staff_map(),
        $sources,
        'manual_json'
    );

    // Expand aliases; Code wins when both Code and UUID disagree.
    $map = siba_leads_didar_expand_map_aliases($map, $didarUsers);

    $preview = [];
    foreach ($didarUsers as $user) {
        if (!is_array($user)) {
            continue;
        }
        $code = (string) (int) ($user['Code'] ?? 0);
        $name = trim((string) ($user['DisplayName'] ?? ''));
        if ($name === '') {
            $name = trim((string) (($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? '')));
        }
        $staffId = 0;
        if ($code !== '0') {
            $staffId = (int) ($map[siba_leads_didar_normalize_user_key($code)] ?? 0);
        }
        if ($staffId < 1) {
            $staffId = (int) ($map[siba_leads_didar_normalize_user_key((string) ($user['Id'] ?? ''))] ?? 0);
        }
        $preview[] = 'Code ' . $code . ' / ' . $name . ' → staffid ' . ($staffId > 0 ? (string) $staffId : 'UNMAPPED');
    }

    return ['map' => $map, 'sources' => $sources, 'sync' => $sync, 'preview' => $preview];
}

/**
 * For each Didar user, register Id + UserId + Code to one staffid.
 * Prefer Code when present so manual JSON Codes beat earlier email UUID matches.
 *
 * @param array<string,int> $map
 * @param array<int,array<string,mixed>> $didarUsers
 * @return array<string,int>
 */
function siba_leads_didar_expand_map_aliases(array $map, array $didarUsers): array
{
    foreach ($didarUsers as $user) {
        if (!is_array($user)) {
            continue;
        }

        $codeKey   = siba_leads_didar_normalize_user_key((string) ($user['Code'] ?? ''));
        $idKey     = siba_leads_didar_normalize_user_key((string) ($user['Id'] ?? ''));
        $userIdKey = siba_leads_didar_normalize_user_key((string) ($user['UserId'] ?? ''));

        $staffId = 0;
        if ($codeKey !== '' && !empty($map[$codeKey])) {
            $staffId = (int) $map[$codeKey];
        } elseif ($idKey !== '' && !empty($map[$idKey])) {
            $staffId = (int) $map[$idKey];
        } elseif ($userIdKey !== '' && !empty($map[$userIdKey])) {
            $staffId = (int) $map[$userIdKey];
        }

        if ($staffId < 1) {
            continue;
        }

        foreach ([$idKey, $userIdKey, $codeKey] as $key) {
            if ($key !== '') {
                $map[$key] = $staffId;
            }
        }
    }

    return $map;
}

/**
 * Attribute lead activity / ownership to the Didar assignee (not the importing admin).
 * Rewrites lead_activity_log staff + assignment payload, and lead.addedfrom.
 */
function siba_leads_didar_attribute_lead_to_staff(int $leadId, int $staffId): void
{
    if ($leadId < 1 || $staffId < 1) {
        return;
    }

    $CI = &get_instance();
    $name = get_staff_full_name($staffId);
    if ($name === '') {
        return;
    }

    $CI->db->where('id', $leadId);
    $CI->db->update(db_prefix() . 'leads', [
        'addedfrom' => $staffId,
        'assigned'  => $staffId,
    ]);

    if (!$CI->db->table_exists(db_prefix() . 'lead_activity_log')) {
        return;
    }

    $CI->db->where('leadid', $leadId);
    $CI->db->update(db_prefix() . 'lead_activity_log', [
        'staffid'   => $staffId,
        'full_name' => $name,
    ]);

    // Assignment line: "from X to Y" — use assignee for both sides so the actor matches.
    $profileLink = '<a href="' . admin_url('profile/' . $staffId) . '" target="_blank">' . e($name) . '</a>';
    $assignPayload = serialize([e($name), $profileLink]);

    $CI->db->where('leadid', $leadId);
    $CI->db->where_in('description', [
        'not_lead_activity_assigned_to',
        'not_lead_activity_assigned_from_form',
    ]);
    $CI->db->update(db_prefix() . 'lead_activity_log', [
        'additional_data' => $assignPayload,
        'staffid'         => $staffId,
        'full_name'       => $name,
    ]);
}

/**
 * Resolve Perfex staffid for a Didar deal owner (OwnerId / nested Owner / Creator).
 *
 * @param array<string,mixed> $deal
 * @param array<string,int>   $map
 */
function siba_leads_didar_resolve_deal_owner_staff_id(array $deal, array $map): int
{
    $candidates = [
        $deal['OwnerId'] ?? '',
        $deal['OwnerUserId'] ?? '',
    ];

    if (!empty($deal['Owner']) && is_array($deal['Owner'])) {
        $candidates[] = $deal['Owner']['Id'] ?? '';
        $candidates[] = $deal['Owner']['UserId'] ?? '';
        $candidates[] = $deal['Owner']['Code'] ?? '';
    }

    $candidates[] = $deal['CreatorId'] ?? '';
    if (!empty($deal['Creator']) && is_array($deal['Creator'])) {
        $candidates[] = $deal['Creator']['Id'] ?? '';
        $candidates[] = $deal['Creator']['UserId'] ?? '';
        $candidates[] = $deal['Creator']['Code'] ?? '';
    }

    foreach ($candidates as $candidate) {
        $staffId = siba_leads_didar_resolve_staff_id($candidate, $map, 0);
        if ($staffId > 0) {
            return $staffId;
        }
    }

    return 0;
}

/**
 * @param string $html
 * @return string
 */
function siba_leads_didar_plain_text(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim((string) $text);
}

/**
 * Keep / normalize temporary staff.didar_id on create/update.
 *
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function siba_leads_didar_filter_staff_didar_id(array $data, $id = null): array
{
    siba_leads_didar_ensure_staff_didar_id_column();

    if (!array_key_exists('didar_id', $data)) {
        return $data;
    }

    $value = siba_leads_didar_normalize_user_key((string) $data['didar_id']);
    $data['didar_id'] = $value !== '' ? $value : null;

    return $data;
}

/**
 * Inject temporary Didar id field on staff member admin form.
 */
function siba_leads_didar_inject_staff_didar_id_field(): void
{
    $CI = &get_instance();
    if (!is_admin() && !staff_can('edit', 'staff')) {
        return;
    }

    $uri = (string) $CI->uri->uri_string();
    if (strpos($uri, 'staff/member') === false) {
        return;
    }

    siba_leads_didar_ensure_staff_didar_id_column();
    if (!$CI->db->field_exists('didar_id', db_prefix() . 'staff')) {
        return;
    }

    $value = '';
    $segments = $CI->uri->segment_array();
    $memberId = 0;
    foreach ($segments as $i => $seg) {
        if ($seg === 'member' && isset($segments[$i + 1]) && is_numeric($segments[$i + 1])) {
            $memberId = (int) $segments[$i + 1];
            break;
        }
    }
    if ($memberId > 0) {
        $row = $CI->db->select('didar_id')->where('staffid', $memberId)->get(db_prefix() . 'staff')->row();
        if ($row && !empty($row->didar_id)) {
            $value = (string) $row->didar_id;
        }
    }

    $label = _l('siba_leads_didar_staff_didar_id');
    $help  = _l('siba_leads_didar_staff_didar_id_help');
    $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $escapedHelp  = htmlspecialchars($help, ENT_QUOTES, 'UTF-8');

    echo '<script>
(function () {
    if (document.getElementById("siba_didar_id")) { return; }
    var email = document.getElementById("email");
    if (!email || !email.closest) { return; }
    var wrap = email.closest(".form-group") || email.parentNode;
    if (!wrap || !wrap.parentNode) { return; }
    var box = document.createElement("div");
    box.className = "form-group";
    box.innerHTML = \'<label for="siba_didar_id" class="control-label">' . $escapedLabel . '</label>\'
        + \'<input type="text" class="form-control" name="didar_id" id="siba_didar_id" value="' . $escapedValue . '" autocomplete="off" dir="ltr">\'
        + \'<p class="text-muted tw-text-sm tw-mt-1">' . $escapedHelp . '</p>\';
    wrap.parentNode.insertBefore(box, wrap.nextSibling);
})();
</script>';
}
