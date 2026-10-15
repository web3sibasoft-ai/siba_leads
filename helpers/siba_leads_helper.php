<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/siba_leads_teams_helper.php';
require_once __DIR__ . '/siba_leads_assignment_helper.php';
require_once __DIR__ . '/siba_leads_phone_helper.php';
require_once __DIR__ . '/siba_leads_location_helper.php';
require_once __DIR__ . '/siba_leads_profile_helper.php';
require_once __DIR__ . '/siba_leads_website_payload_helper.php';
require_once __DIR__ . '/siba_leads_reports_helper.php';
require_once __DIR__ . '/siba_leads_timeline_helper.php';
require_once __DIR__ . '/siba_leads_didar_helper.php';

/**
 * Ensure Jalali view helpers from siba_license are loaded.
 */
function siba_leads_ensure_jalali_helpers(): void
{
    if (function_exists('to_view_date_custom') && function_exists('to_view_datetime_custom')) {
        return;
    }
    $CI = &get_instance();
    $CI->load->helper('siba_license/siba_license');
}

/**
 * Gregorian date/datetime → Jalali Y/m/d for display.
 */
function siba_leads_jalali_date($date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }
    siba_leads_ensure_jalali_helpers();
    if (function_exists('to_view_date_custom')) {
        $out = to_view_date_custom($date);

        return $out !== '' ? (string) $out : '';
    }

    return (string) $date;
}

/**
 * Gregorian datetime → Jalali Y/m/d H:i for display.
 */
function siba_leads_jalali_datetime($datetime): string
{
    if ($datetime === null || $datetime === '' || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return '';
    }
    siba_leads_ensure_jalali_helpers();
    if (function_exists('to_view_datetime_custom')) {
        $out = to_view_datetime_custom($datetime);

        return $out !== '' ? (string) $out : '';
    }

    return siba_leads_jalali_date($datetime);
}

/**
 * Gregorian month key YYYY-MM → Jalali Y/m for display.
 */
function siba_leads_jalali_month($ym): string
{
    $ym = trim((string) $ym);
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
        return siba_leads_jalali_date($ym);
    }

    siba_leads_ensure_jalali_helpers();
    $ts = strtotime($m[1] . '-' . $m[2] . '-01 12:00:00');
    if (!$ts) {
        return $ym;
    }

    if (function_exists('siba_license_ensure_jdf_helper')) {
        siba_license_ensure_jdf_helper();
    }
    if (function_exists('jdate')) {
        return (string) jdate('Y/m', $ts, '', 'Asia/Tehran', 'en');
    }

    return $ym;
}

/**
 * Can the current staff see every lead on the Siba kanban?
 */
function siba_leads_can_view_all(): bool
{
    return is_admin() || staff_can('view_all', SIBA_LEADS_MODULE_NAME);
}

/**
 * Can the current staff create / generate new leads?
 */
function siba_leads_can_create(): bool
{
    return is_admin() || staff_can('create', SIBA_LEADS_MODULE_NAME);
}

/**
 * Block admin create-lead requests when staff lacks siba_leads → create.
 * Covers empty modal load (GET) and create submit (POST) on leads/lead.
 */
function siba_leads_guard_create_permission()
{
    $CI = &get_instance();
    $uri = (string) $CI->uri->uri_string();
    $requestUri = (string) ($CI->input->server('REQUEST_URI') ?: '');
    $path = $uri . ' ' . $requestUri;

    // Match admin/leads/lead with no numeric id (create flow only).
    if (!preg_match('#(?:^|/)leads/lead(?:/?|\?|$)#', $path)) {
        return;
    }
    if (preg_match('#(?:^|/)leads/lead/\d+#', $path)) {
        return;
    }

    if (siba_leads_can_create()) {
        return;
    }

    if ($CI->input->is_ajax_request()) {
        ajax_access_denied();
    }

    access_denied(SIBA_LEADS_MODULE_NAME);
}

/**
 * Temporary maintenance gate for all admin leads / siba_leads pages.
 */
function siba_leads_is_maintenance_mode(): bool
{
    return defined('SIBA_LEADS_MAINTENANCE_MODE') && SIBA_LEADS_MAINTENANCE_MODE === true;
}

/**
 * Replace leads UI with the maintenance view while SIBA_LEADS_MAINTENANCE_MODE is on.
 * Does not affect the public/API lead create endpoints.
 */
function siba_leads_guard_maintenance_mode(): void
{
    if (!siba_leads_is_maintenance_mode()) {
        return;
    }

    // Admins can still open leads pages while maintenance is on.
    if (function_exists('is_admin') && is_admin()) {
        return;
    }

    $CI = &get_instance();
    $class = strtolower((string) $CI->router->fetch_class());
    if (!in_array($class, ['leads', 'siba_leads'], true)) {
        return;
    }

    $message = function_exists('_l') ? _l('siba_leads_maintenance_title') : 'Leads are under maintenance';

    if ($CI->input->is_ajax_request()) {
        $CI->output
            ->set_status_header(503)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode([
                'success' => false,
                'ok'      => false,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE));
        $CI->output->_display();
        exit;
    }

    $data = [
        'title'     => $message,
        'image_url' => module_dir_url(SIBA_LEADS_MODULE_NAME, 'assets/images/leads_maintenance.jpg'),
    ];

    echo $CI->load->view('siba_leads/maintenance', $data, true);
    exit;
}

/**
 * Can the current staff open/manage the Teams settings page?
 */
function siba_leads_can_manage_teams(): bool
{
    return is_admin() || staff_can('manage_teams', SIBA_LEADS_MODULE_NAME);
}

/**
 * Can the current staff open the Lead reports area (any report page)?
 */
function siba_leads_can_view_reports(): bool
{
    if (is_admin() || staff_can('view_reports', SIBA_LEADS_MODULE_NAME)) {
        return true;
    }

    if (!function_exists('siba_leads_reports_tabs')) {
        return false;
    }

    foreach (siba_leads_reports_tabs() as $tab) {
        if (staff_can('report_' . $tab, SIBA_LEADS_MODULE_NAME)) {
            return true;
        }
    }

    return false;
}

/**
 * Can the current staff view a specific report tab?
 *
 * @param string $report Tab key from siba_leads_reports_tabs()
 */
function siba_leads_can_view_report(string $report): bool
{
    if (is_admin() || staff_can('view_reports', SIBA_LEADS_MODULE_NAME)) {
        return true;
    }

    $report = strtolower(trim($report));
    if ($report === '') {
        return false;
    }

    return staff_can('report_' . $report, SIBA_LEADS_MODULE_NAME);
}

/**
 * Report tabs the current staff is allowed to open.
 *
 * @return string[]
 */
function siba_leads_allowed_report_tabs(): array
{
    $tabs = function_exists('siba_leads_reports_tabs') ? siba_leads_reports_tabs() : [];
    if (is_admin() || staff_can('view_reports', SIBA_LEADS_MODULE_NAME)) {
        return $tabs;
    }

    $allowed = [];
    foreach ($tabs as $tab) {
        if (staff_can('report_' . $tab, SIBA_LEADS_MODULE_NAME)) {
            $allowed[] = $tab;
        }
    }

    return $allowed;
}

/**
 * First allowed report tab (for menu / redirects).
 */
function siba_leads_reports_default_tab(): string
{
    $allowed = siba_leads_allowed_report_tabs();
    if (!empty($allowed)) {
        return (string) $allowed[0];
    }

    return 'sources';
}

/**
 * Build a kanban query scoped for the Siba board.
 */
function siba_leads_kanban_for_status($statusId): \modules\siba_leads\services\SibaLeadsKanban
{
    if (!class_exists(\modules\siba_leads\services\SibaLeadsKanban::class, false)) {
        require_once module_dir_path(SIBA_LEADS_MODULE_NAME, 'services/SibaLeadsKanban.php');
    }

    return new \modules\siba_leads\services\SibaLeadsKanban($statusId);
}

/**
 * Active Didar pipeline board id from session (falls back to first mapped pipeline).
 */
function siba_leads_active_pipeline_id(): string
{
    if (!function_exists('siba_leads_didar_list_pipeline_maps')) {
        return '';
    }

    $CI         = &get_instance();
    $pipelines  = siba_leads_didar_list_pipeline_maps();
    if ($pipelines === []) {
        return '';
    }

    $active = trim((string) $CI->session->userdata('siba_leads_active_pipeline_id'));
    if ($active !== '') {
        foreach ($pipelines as $row) {
            if (trim((string) ($row['didar_pipeline_id'] ?? '')) === $active) {
                return $active;
            }
        }
    }

    return trim((string) ($pipelines[0]['didar_pipeline_id'] ?? ''));
}

/**
 * @return array<string,mixed>|null
 */
function siba_leads_active_pipeline_map(): ?array
{
    $active = siba_leads_active_pipeline_id();
    if ($active === '' || !function_exists('siba_leads_didar_list_pipeline_maps')) {
        return null;
    }
    foreach (siba_leads_didar_list_pipeline_maps() as $row) {
        if (trim((string) ($row['didar_pipeline_id'] ?? '')) === $active) {
            return $row;
        }
    }

    return null;
}

/**
 * Lead statuses (columns) for the active pipeline board.
 * Falls back to all lead statuses when no Didar stage map exists yet.
 *
 * @return array<int,array<string,mixed>>
 */
function siba_leads_kanban_statuses(): array
{
    $CI = &get_instance();
    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    $pipelineId = siba_leads_active_pipeline_id();
    $statusIds  = ($pipelineId !== '' && function_exists('siba_leads_didar_get_pipeline_status_ids'))
        ? siba_leads_didar_get_pipeline_status_ids($pipelineId)
        : [];

    if ($statusIds === []) {
        return $CI->leads_model->get_status();
    }

    $byId = [];
    foreach ($CI->leads_model->get_status() as $status) {
        $sid = (int) ($status['id'] ?? 0);
        if ($sid > 0) {
            $byId[$sid] = $status;
        }
    }

    $ordered = [];
    foreach ($statusIds as $sid) {
        if (isset($byId[$sid]) && empty($byId[$sid]['isdefault'])) {
            $ordered[] = $byId[$sid];
        }
    }

    return $ordered !== [] ? $ordered : $CI->leads_model->get_status();
}

/**
 * Status summary totals/values, respecting Siba view_all permission.
 * Scoped to the active pipeline board (status columns + pipeline tag) when Didar stages are mapped.
 * Must use the same tag filter as kanban cards (siba_leads_effective_tag_filter).
 */
function siba_leads_get_summary(): array
{
    static $requestCache = null;
    if ($requestCache !== null) {
        return $requestCache;
    }

    $CI = &get_instance();
    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    $statuses   = function_exists('siba_leads_kanban_statuses')
        ? siba_leads_kanban_statuses()
        : $CI->leads_model->get_status();
    $canViewAll = siba_leads_can_view_all();
    $staffId    = (int) get_staff_user_id();
    $leadIdCol  = db_prefix() . 'leads.id';
    $tagSql     = function_exists('siba_leads_tag_filter_sql')
        ? siba_leads_tag_filter_sql($leadIdCol)
        : '';
    $sql        = '';

    $statuses[] = [
        'lost'  => true,
        'name'  => _l('lost_leads'),
        'color' => '#fc2d42',
    ];

    foreach ($statuses as $status) {
        $sql .= ' SELECT COUNT(*) as total';
        $sql .= ',SUM(lead_value) as value';
        $sql .= ' FROM ' . db_prefix() . 'leads';

        if (isset($status['lost'])) {
            $sql .= ' WHERE lost=1';
        } else {
            $sql .= ' WHERE status=' . (int) $status['id'];
            $sql .= ' AND ' . siba_leads_kanban_open_where_sql();
        }

        $sql .= $tagSql;

        if (!$canViewAll) {
            $sql .= ' AND assigned=' . $staffId;
        }

        $sql .= ' UNION ALL ';
    }

    $sql    = substr(trim($sql), 0, -10);
    $result = $CI->db->query($sql)->result();

    if (!$canViewAll) {
        $CI->db->where('assigned', $staffId);
    }
    if ($tagSql !== '' && function_exists('siba_leads_apply_tag_filter_where')) {
        $filter = siba_leads_effective_tag_filter();
        siba_leads_apply_tag_filter_where($CI->db, $leadIdCol, $filter['tag_ids'], $filter['tag_none']);
    }

    $total_leads = $CI->db->count_all_results(db_prefix() . 'leads');

    foreach ($statuses as $key => $status) {
        if (isset($status['lost']) || isset($status['junk'])) {
            $statuses[$key]['total']   = $result[$key]->total;
            $statuses[$key]['value']   = $result[$key]->value;
            $statuses[$key]['percent'] = ($total_leads > 0
                ? number_format(($result[$key]->total * 100) / $total_leads, 2)
                : 0);
        } else {
            $statuses[$key]['total'] = $result[$key]->total;
            $statuses[$key]['value'] = $result[$key]->value;
        }
    }

    $requestCache = $statuses;

    return $statuses;
}

/**
 * Ensure the current user may change this lead on the Siba board.
 */
function siba_leads_can_manage_lead($leadId): bool
{
    if (siba_leads_can_view_all()) {
        return true;
    }

    $CI = &get_instance();
    $CI->db->select(db_prefix() . 'leads.id');
    $CI->db->where(db_prefix() . 'leads.id', (int) $leadId);
    $CI->db->where('assigned', (int) get_staff_user_id());

    return $CI->db->count_all_results(db_prefix() . 'leads') > 0;
}

/**
 * Extra lead columns used instead of custom fields.
 *
 * @return array<string, string> column => SQL type
 */
function siba_leads_lead_extra_columns(): array
{
    return [
        'birth_date'                => "varchar(50) NULL DEFAULT ''",
        'form_identifier'           => "varchar(150) NULL DEFAULT ''",
        'form_source_link'          => "varchar(500) NULL DEFAULT ''",
        'form_source_page_title'    => "varchar(255) NULL DEFAULT ''",
        'sms_verification'          => "varchar(50) NULL DEFAULT ''",
        'demo_request_tracking_id'  => "varchar(150) NULL DEFAULT ''",
        'user_package_number'       => "varchar(150) NULL DEFAULT ''",
        'request_type'              => "varchar(150) NULL DEFAULT ''",
        'form_title'                => "varchar(255) NULL DEFAULT ''",
        'job_id'                    => 'INT(11) NOT NULL DEFAULT 0',
        'ref_code'                  => "varchar(150) NULL DEFAULT ''",
        // Marketing attribution (website / create-lead API)
        'cta_location'              => "varchar(150) NULL DEFAULT ''",
        'industry'                  => "varchar(150) NULL DEFAULT ''",
        'traffic_source'            => "varchar(150) NULL DEFAULT ''",
        'utm_source'                => "varchar(150) NULL DEFAULT ''",
        'utm_medium'                => "varchar(150) NULL DEFAULT ''",
        'utm_campaign'              => "varchar(255) NULL DEFAULT ''",
        'utm_content'               => "varchar(255) NULL DEFAULT ''",
        'utm_term'                  => "varchar(255) NULL DEFAULT ''",
        'gclid'                     => "varchar(500) NULL DEFAULT ''",
        'gbraid'                    => "varchar(500) NULL DEFAULT ''",
        'wbraid'                    => "varchar(500) NULL DEFAULT ''",
        'device_type'               => "varchar(50) NULL DEFAULT ''",
        'industry_plan'             => "varchar(150) NULL DEFAULT ''",
        'siba_outcome'              => "varchar(20) NULL DEFAULT NULL",
        'siba_outcome_at'           => 'DATETIME NULL DEFAULT NULL',
        'siba_outcome_by'           => 'INT(11) NULL DEFAULT NULL',
        'siba_outcome_how'          => "varchar(50) NULL DEFAULT NULL",
        'siba_failure_reason_id'    => 'INT(11) NULL DEFAULT NULL',
        'siba_failed_from_status'   => 'INT(11) NULL DEFAULT NULL',
        'siba_failure_description'  => 'TEXT NULL DEFAULT NULL',
    ];
}

/**
 * Marketing attribution columns accepted by the create/update lead API.
 *
 * @return string[]
 */
function siba_leads_attribution_columns(): array
{
    return [
        'cta_location',
        'industry',
        'industry_plan',
        'traffic_source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gclid',
        'gbraid',
        'wbraid',
        'device_type',
    ];
}

/**
 * Add extra lead columns when missing (install + already-activated sites).
 */
/**
 * Lookup table for lead failure / lost reasons (title + color).
 */
function siba_leads_ensure_failure_reasons_table(): void
{
    $CI = &get_instance();
    $table = db_prefix() . 'siba_leads_failure_reasons';
    if ($CI->db->table_exists($table)) {
        return;
    }

    $CI->db->query('CREATE TABLE `' . $table . "` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(191) NOT NULL,
        `color` varchar(20) NOT NULL DEFAULT '#6b7280',
        `datecreated` datetime NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

function siba_leads_ensure_lead_columns(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    // Schema version: bump when additive lead columns are introduced (e.g. industry_plan).
    $schemaVersion = 2;
    $currentVersion = (int) get_option('siba_leads_schema_version');
    if ($currentVersion >= $schemaVersion && get_option('siba_leads_columns_ready') === '1') {
        $done = true;

        return;
    }

    $CI = &get_instance();
    $table = db_prefix() . 'leads';
    if (!$CI->db->table_exists($table)) {
        return;
    }

    foreach (siba_leads_lead_extra_columns() as $column => $definition) {
        if (!$CI->db->field_exists($column, $table)) {
            $CI->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    if (function_exists('siba_leads_location_columns')) {
        foreach (siba_leads_location_columns() as $column => $definition) {
            if (!$CI->db->field_exists($column, $table)) {
                $CI->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
            }
        }
    }

    if (function_exists('siba_leads_profile_columns')) {
        foreach (siba_leads_profile_columns() as $column => $definition) {
            if (!$CI->db->field_exists($column, $table)) {
                $CI->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
            }
        }
    }

    if (get_option('siba_leads_columns_ready') === false) {
        add_option('siba_leads_columns_ready', '1');
    } else {
        update_option('siba_leads_columns_ready', '1');
    }
    if (get_option('siba_leads_schema_version') === false) {
        add_option('siba_leads_schema_version', (string) $schemaVersion);
    } else {
        update_option('siba_leads_schema_version', (string) $schemaVersion);
    }
    $done = true;
}

/**
 * Kanban SELECT fragments for the latest unapproved lead order.
 *
 * Active = linked via order_lead_id and not financially approved yet.
 */
function siba_leads_kanban_active_order_select_sql(): string
{
    $CI     = &get_instance();
    $prefix = db_prefix();
    $orders = $prefix . 'siba_orders';
    $fin    = $prefix . 'siba_fin_approve_req';
    $leads  = $prefix . 'leads';

    if (!$CI->db->table_exists($orders)) {
        return ',0 as active_order_id,0 as has_pending_fin_req,0 as existing_order_id,0 as lead_invoice_id,0 as lead_invoice_status,\'\' as lead_invoice_hash,0 as lead_order_paid,0 as lead_order_expired,0 as lead_order_pending_invoice';
    }

    $activeOrder = '(SELECT o.order_id FROM ' . $orders . ' o'
        . ' WHERE o.order_lead_id = ' . $leads . '.id'
        . ' AND o.order_financial_approval = 0'
        . ' ORDER BY o.order_id DESC LIMIT 1)';

    $anyOrder = '(SELECT o.order_id FROM ' . $orders . ' o'
        . ' WHERE o.order_lead_id = ' . $leads . '.id'
        . ' OR (o.order_customer > 0 AND o.order_customer = (SELECT userid FROM ' . $prefix . 'clients c WHERE c.leadid = ' . $leads . '.id LIMIT 1))'
        . ' ORDER BY o.order_id DESC LIMIT 1)';

    $invoiceId = '(SELECT o.order_invoice_id FROM ' . $orders . ' o'
        . ' WHERE o.order_id = ' . $activeOrder
        . ' LIMIT 1)';

    $invoiceStatus = '0';
    $invoiceHash = "''";
    if ($CI->db->table_exists($prefix . 'invoices')) {
        $invoiceStatus = '(SELECT i.status FROM ' . $prefix . 'invoices i'
            . ' INNER JOIN ' . $orders . ' o ON o.order_invoice_id = i.id'
            . ' WHERE o.order_id = ' . $activeOrder
            . ' LIMIT 1)';
        $invoiceHash = '(SELECT i.hash FROM ' . $prefix . 'invoices i'
            . ' INNER JOIN ' . $orders . ' o ON o.order_invoice_id = i.id'
            . ' WHERE o.order_id = ' . $activeOrder
            . ' LIMIT 1)';
    }

    $orderPaid = '0';
    if ($CI->db->field_exists('order_paid', $orders)) {
        $orderPaid = '(SELECT o.order_paid FROM ' . $orders . ' o'
            . ' WHERE o.order_id = ' . $activeOrder
            . ' LIMIT 1)';
    }

    $orderExpired = '0';
    if ($CI->db->field_exists('order_factor_expired', $orders)) {
        $orderExpired = '(SELECT o.order_factor_expired FROM ' . $orders . ' o'
            . ' WHERE o.order_id = ' . $activeOrder
            . ' LIMIT 1)';
    }

    $pendingInvoice = '0';
    if ($CI->db->field_exists('order_pending_invoice', $orders)) {
        $pendingInvoice = '(SELECT o.order_pending_invoice FROM ' . $orders . ' o'
            . ' WHERE o.order_id = ' . $activeOrder
            . ' LIMIT 1)';
    }

    $pending = '0';
    if ($CI->db->table_exists($fin)) {
        $pending = '(SELECT 1 FROM ' . $fin . ' r'
            . ' WHERE r.fin_approve_order = ' . $activeOrder
            . ' AND (r.fin_approve_answer_staff IS NULL OR r.fin_approve_answer_staff = 0) LIMIT 1)';
    }

    return ',' . $activeOrder . ' as active_order_id,' . $pending . ' as has_pending_fin_req,' . $anyOrder . ' as existing_order_id,' . $invoiceId . ' as lead_invoice_id,' . $invoiceStatus . ' as lead_invoice_status,' . $invoiceHash . ' as lead_invoice_hash,' . $orderPaid . ' as lead_order_paid,' . $orderExpired . ' as lead_order_expired,' . $pendingInvoice . ' as lead_order_pending_invoice';
}

/**
 * How labels for lead outcome audit.
 *
 * @return array<string, string>
 */
function siba_leads_outcome_how_labels(): array
{
    return [
        'financial_approval' => _l('siba_leads_outcome_how_financial_approval'),
        'manual_fail'        => _l('siba_leads_outcome_how_manual_fail'),
        'manual_success'     => _l('siba_leads_outcome_how_manual_success'),
    ];
}

/**
 * Human-readable outcome how.
 */
function siba_leads_outcome_how_label($how): string
{
    $how = trim((string) $how);
    $labels = siba_leads_outcome_how_labels();

    return $labels[$how] ?? ($how !== '' ? $how : '—');
}

/**
 * Persist lead success/failed outcome + audit fields.
 *
 * @param array{
 *   failure_reason_id?:int,
 *   staff_id?:int,
 *   only_if_empty?:bool,
 *   skip_activity?:bool
 * } $extra
 */
function siba_leads_set_outcome($lead_id, $outcome, $how, array $extra = []): bool
{
    $lead_id = (int) $lead_id;
    $outcome = strtolower(trim((string) $outcome));
    $how     = trim((string) $how);

    if ($lead_id < 1 || !in_array($outcome, ['success', 'failed'], true) || $how === '') {
        return false;
    }

    if (function_exists('siba_leads_ensure_lead_columns')) {
        siba_leads_ensure_lead_columns();
    }

    $CI = &get_instance();
    $table = db_prefix() . 'leads';
    if (!$CI->db->table_exists($table) || !$CI->db->field_exists('siba_outcome', $table)) {
        return false;
    }

    $lead = $CI->db->select('id, siba_outcome, lost, junk, status')
        ->from($table)
        ->where('id', $lead_id)
        ->get()
        ->row_array();
    if (!$lead) {
        return false;
    }

    if (!empty($extra['only_if_empty']) && !empty($lead['siba_outcome'])) {
        return true;
    }

    $staffId = isset($extra['staff_id']) ? (int) $extra['staff_id'] : (int) get_staff_user_id();
    if ($staffId < 1) {
        $staffId = 0;
    }

    $payload = [
        'siba_outcome'     => $outcome,
        'siba_outcome_at'  => date('Y-m-d H:i:s'),
        'siba_outcome_by'  => $staffId > 0 ? $staffId : null,
        'siba_outcome_how' => $how,
    ];

    if ($outcome === 'failed') {
        $reasonId = (int) ($extra['failure_reason_id'] ?? 0);
        if ($reasonId < 1) {
            return false;
        }
        $fromStatus = (int) ($lead['status'] ?? 0);
        // Keep the kanban stage the lead was in when marked failed (status is cleared below).
        if ($fromStatus > 0) {
            $payload['siba_failed_from_status'] = $fromStatus;
        } elseif ($CI->db->field_exists('siba_failed_from_status', $table)) {
            // Don't overwrite a previously stored stage with 0.
            // (re-mark / repair paths)
        }
        $payload['siba_failure_reason_id'] = $reasonId;
        $failureDesc = isset($extra['failure_description']) ? trim((string) $extra['failure_description']) : null;
        if ($failureDesc === '') {
            $failureDesc = null;
        }
        $payload['siba_failure_description'] = $failureDesc;
        $payload['lost']                     = 1;
        $payload['junk']                     = 0;
        $payload['status']                   = 0;
        // So core unmark_as_lost can restore a real pipeline status.
        if ($fromStatus > 0) {
            $payload['last_lead_status'] = $fromStatus;
        }
    } else {
        $payload['siba_failure_reason_id']   = null;
        $payload['siba_failed_from_status']  = null;
        $payload['siba_failure_description'] = null;
        $payload['lost']                     = 0;
        $payload['junk']                     = 0;
    }

    $CI->db->where('id', $lead_id)->update($table, $payload);
    if ($CI->db->affected_rows() < 0) {
        return false;
    }

    if (empty($extra['skip_activity'])) {
        if (!class_exists('leads_model', false)) {
            $CI->load->model('leads_model');
        }
        $who = $staffId > 0 ? get_staff_full_name($staffId) : _l('system_default_string');
        if ($outcome === 'failed') {
            $reasonTitle = '';
            $reasonsTable = db_prefix() . 'siba_leads_failure_reasons';
            if ($CI->db->table_exists($reasonsTable)) {
                $reason = $CI->db->select('title')
                    ->from($reasonsTable)
                    ->where('id', (int) ($extra['failure_reason_id'] ?? 0))
                    ->get()
                    ->row();
                $reasonTitle = $reason->title ?? '';
            }
            if (!empty($failureDesc)) {
                $CI->leads_model->log_lead_activity(
                    $lead_id,
                    'siba_leads_activity_marked_failed_with_desc',
                    false,
                    serialize([$who, $reasonTitle !== '' ? $reasonTitle : '#', siba_leads_outcome_how_label($how), $failureDesc])
                );
            } else {
                $CI->leads_model->log_lead_activity(
                    $lead_id,
                    'siba_leads_activity_marked_failed',
                    false,
                    serialize([$who, $reasonTitle !== '' ? $reasonTitle : '#', siba_leads_outcome_how_label($how)])
                );
            }
        } else {
            $CI->leads_model->log_lead_activity(
                $lead_id,
                'siba_leads_activity_marked_success',
                false,
                serialize([$who, siba_leads_outcome_how_label($how)])
            );
        }
    }

    return true;
}

/**
 * Restore a failed/lost lead back onto the open pipeline (kanban + normal leads).
 * Clears Siba failed outcome fields and restores a real status column.
 */
function siba_leads_restore_from_failed($lead_id): bool
{
    $lead_id = (int) $lead_id;
    if ($lead_id < 1) {
        return false;
    }

    if (function_exists('siba_leads_ensure_lead_columns')) {
        siba_leads_ensure_lead_columns();
    }

    $CI = &get_instance();
    $table = db_prefix() . 'leads';
    if (!$CI->db->table_exists($table)) {
        return false;
    }

    $select = 'id, lost, junk, status, last_lead_status';
    if ($CI->db->field_exists('siba_failed_from_status', $table)) {
        $select .= ', siba_failed_from_status';
    }
    if ($CI->db->field_exists('siba_outcome', $table)) {
        $select .= ', siba_outcome';
    }

    $lead = $CI->db->select($select)
        ->from($table)
        ->where('id', $lead_id)
        ->get()
        ->row_array();
    if (!$lead) {
        return false;
    }

    $restoreStatus = 0;
    if (!empty($lead['siba_failed_from_status'])) {
        $restoreStatus = (int) $lead['siba_failed_from_status'];
    }
    if ($restoreStatus < 1 && !empty($lead['last_lead_status'])) {
        $restoreStatus = (int) $lead['last_lead_status'];
    }
    if ($restoreStatus < 1 && !empty($lead['status'])) {
        $restoreStatus = (int) $lead['status'];
    }
    if ($restoreStatus < 1) {
        if (!class_exists('leads_model', false)) {
            $CI->load->model('leads_model');
        }
        $defaults = $CI->leads_model->get_status('', ['isdefault' => 1]);
        if (!empty($defaults[0]['id'])) {
            $restoreStatus = (int) $defaults[0]['id'];
        } else {
            $any = $CI->leads_model->get_status();
            if (!empty($any[0]['id'])) {
                $restoreStatus = (int) $any[0]['id'];
            }
        }
    }
    if ($restoreStatus < 1) {
        return false;
    }

    $payload = [
        'lost'               => 0,
        'junk'               => 0,
        'status'             => $restoreStatus,
        'last_status_change' => date('Y-m-d H:i:s'),
        'last_lead_status'   => $restoreStatus,
    ];

    if ($CI->db->field_exists('siba_outcome', $table)) {
        $payload['siba_outcome'] = null;
    }
    if ($CI->db->field_exists('siba_outcome_at', $table)) {
        $payload['siba_outcome_at'] = null;
    }
    if ($CI->db->field_exists('siba_outcome_by', $table)) {
        $payload['siba_outcome_by'] = null;
    }
    if ($CI->db->field_exists('siba_outcome_how', $table)) {
        $payload['siba_outcome_how'] = null;
    }
    if ($CI->db->field_exists('siba_failure_reason_id', $table)) {
        $payload['siba_failure_reason_id'] = null;
    }
    if ($CI->db->field_exists('siba_failure_description', $table)) {
        $payload['siba_failure_description'] = null;
    }
    if ($CI->db->field_exists('siba_failed_from_status', $table)) {
        $payload['siba_failed_from_status'] = null;
    }

    $CI->db->where('id', $lead_id)->update($table, $payload);

    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }
    $who = get_staff_user_id() > 0 ? get_staff_full_name(get_staff_user_id()) : _l('system_default_string');
    $CI->leads_model->log_lead_activity(
        $lead_id,
        'siba_leads_activity_restored_from_failed',
        false,
        serialize([$who])
    );
    log_activity('Lead Restored From Failed/Lost [ID: ' . $lead_id . ']');

    hooks()->do_action('siba_leads_restored_from_failed', $lead_id);

    return true;
}

/**
 * SQL fragment: open leads only (not lost/junk/converted).
 * Avoid comparing DATETIME to '' — MySQL 8+ throws "Incorrect DATETIME value: ''".
 */
function siba_leads_kanban_open_where_sql(string $alias = ''): string
{
    $col = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    // Treat NULL / zero-date legacy values as "not converted" without empty-string compare.
    return $col . 'lost = 0 AND ' . $col . 'junk = 0'
        . ' AND (' . $col . 'date_converted IS NULL OR ' . $col . "date_converted < '1971-01-01 00:00:00')";
}

/**
 * Batch-enrich kanban lead rows (clients, counts, tags, tasks, orders, phones).
 * Replaces per-row correlated subqueries + N+1 helpers with a few IN() queries.
 *
 * @param array<int, array<string, mixed>> $leads
 * @return array<int, array<string, mixed>>
 */
function siba_leads_kanban_batch_enrich_leads(array $leads): array
{
    if ($leads === []) {
        return $leads;
    }

    $CI     = &get_instance();
    $prefix = db_prefix();
    $ids    = [];
    foreach ($leads as $lead) {
        $id = (int) ($lead['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $ids = array_values(array_unique($ids));
    if ($ids === []) {
        return $leads;
    }

    $idList = implode(',', $ids);

    // Request-scoped schema cache — avoid repeated information_schema hits per column load.
    static $schemaCache = [];
    $tableOk = static function (string $table) use ($CI, &$schemaCache): bool {
        if (!array_key_exists($table, $schemaCache)) {
            $schemaCache[$table] = (bool) $CI->db->table_exists($table);
        }

        return $schemaCache[$table];
    };
    $fieldOk = static function (string $field, string $table) use ($CI, &$schemaCache): bool {
        $key = $table . '.' . $field;
        if (!array_key_exists($key, $schemaCache)) {
            $schemaCache[$key] = (bool) $CI->db->field_exists($field, $table);
        }

        return $schemaCache[$key];
    };

    // Formal client conversion
    $clientByLead = [];
    if ($tableOk($prefix . 'clients')) {
        $rows = $CI->db->query(
            'SELECT leadid, userid FROM ' . $prefix . 'clients WHERE leadid IN (' . $idList . ')'
        )->result_array();
        foreach ($rows as $row) {
            $lid = (int) $row['leadid'];
            if ($lid > 0 && !isset($clientByLead[$lid])) {
                $clientByLead[$lid] = (int) $row['userid'];
            }
        }
    }

    // Files / notes counts
    $filesByLead = [];
    $notesByLead = [];
    if ($tableOk($prefix . 'files')) {
        $rows = $CI->db->query(
            'SELECT rel_id, COUNT(id) AS total FROM ' . $prefix . 'files'
            . ' WHERE rel_type = "lead" AND rel_id IN (' . $idList . ') GROUP BY rel_id'
        )->result_array();
        foreach ($rows as $row) {
            $filesByLead[(int) $row['rel_id']] = (int) $row['total'];
        }
    }
    if ($tableOk($prefix . 'notes')) {
        $rows = $CI->db->query(
            'SELECT rel_id, COUNT(id) AS total FROM ' . $prefix . 'notes'
            . ' WHERE rel_type = "lead" AND rel_id IN (' . $idList . ') GROUP BY rel_id'
        )->result_array();
        foreach ($rows as $row) {
            $notesByLead[(int) $row['rel_id']] = (int) $row['total'];
        }
    }

    // Tags
    $tagsByLead = [];
    if ($tableOk($prefix . 'taggables') && $tableOk($prefix . 'tags')) {
        $rows = $CI->db->query(
            'SELECT tg.rel_id, GROUP_CONCAT(t.name ORDER BY tg.tag_order ASC SEPARATOR ",") AS tags'
            . ' FROM ' . $prefix . 'taggables tg'
            . ' INNER JOIN ' . $prefix . 'tags t ON t.id = tg.tag_id'
            . ' WHERE tg.rel_type = "lead" AND tg.rel_id IN (' . $idList . ')'
            . ' GROUP BY tg.rel_id'
        )->result_array();
        foreach ($rows as $row) {
            $tagsByLead[(int) $row['rel_id']] = (string) ($row['tags'] ?? '');
        }
    }

    // My active tasks
    $tasksByLead = [];
    $staffId = (int) get_staff_user_id();
    if ($staffId > 0 && $tableOk($prefix . 'tasks') && $tableOk($prefix . 'task_assigned')) {
        $rows = $CI->db->query(
            'SELECT t.rel_id, COUNT(t.id) AS total'
            . ' FROM ' . $prefix . 'tasks t'
            . ' INNER JOIN ' . $prefix . 'task_assigned ta ON ta.taskid = t.id'
            . ' WHERE t.rel_type = "lead" AND t.rel_id IN (' . $idList . ')'
            . ' AND ta.staffid = ' . $staffId
            . ' AND t.status != 5'
            . ' GROUP BY t.rel_id'
        )->result_array();
        foreach ($rows as $row) {
            $tasksByLead[(int) $row['rel_id']] = (int) $row['total'];
        }
    }

    // Due/overdue reminders on lead + on lead-related tasks (today or past).
    $reminderByLead = [];
    if ($tableOk($prefix . 'reminders')) {
        $todayEndSql = "DATE_ADD(CURDATE(), INTERVAL 1 DAY)";

        $leadReminderRows = $CI->db->query(
            'SELECT rel_id, COUNT(id) AS total, MIN(date) AS earliest'
            . ' FROM ' . $prefix . 'reminders'
            . ' WHERE rel_type = "lead" AND rel_id IN (' . $idList . ')'
            . ' AND date < ' . $todayEndSql
            . ' GROUP BY rel_id'
        )->result_array();
        foreach ($leadReminderRows as $row) {
            $lid = (int) ($row['rel_id'] ?? 0);
            if ($lid < 1) {
                continue;
            }
            $reminderByLead[$lid] = [
                'count'    => (int) ($row['total'] ?? 0),
                'earliest' => (string) ($row['earliest'] ?? ''),
                'on_lead'  => true,
                'on_task'  => false,
            ];
        }

        if ($tableOk($prefix . 'tasks')) {
            $taskReminderRows = $CI->db->query(
                'SELECT t.rel_id AS lead_id, COUNT(r.id) AS total, MIN(r.date) AS earliest'
                . ' FROM ' . $prefix . 'reminders r'
                . ' INNER JOIN ' . $prefix . 'tasks t ON t.id = r.rel_id AND t.rel_type = "lead"'
                . ' WHERE r.rel_type = "task" AND t.rel_id IN (' . $idList . ')'
                . ' AND r.date < ' . $todayEndSql
                . ' GROUP BY t.rel_id'
            )->result_array();
            foreach ($taskReminderRows as $row) {
                $lid = (int) ($row['lead_id'] ?? 0);
                if ($lid < 1) {
                    continue;
                }
                $count    = (int) ($row['total'] ?? 0);
                $earliest = (string) ($row['earliest'] ?? '');
                if (!isset($reminderByLead[$lid])) {
                    $reminderByLead[$lid] = [
                        'count'    => $count,
                        'earliest' => $earliest,
                        'on_lead'  => false,
                        'on_task'  => true,
                    ];
                    continue;
                }
                $reminderByLead[$lid]['count'] += $count;
                $reminderByLead[$lid]['on_task'] = true;
                $prev = (string) ($reminderByLead[$lid]['earliest'] ?? '');
                if ($earliest !== '' && ($prev === '' || strcmp($earliest, $prev) < 0)) {
                    $reminderByLead[$lid]['earliest'] = $earliest;
                }
            }
        }
    }

    // Orders (active + any) + invoice + pending fin
    $activeByLead   = [];
    $anyOrderByLead = [];
    $ordersTable    = $prefix . 'siba_orders';
    if ($tableOk($ordersTable)) {
        $hasPaid     = $fieldOk('order_paid', $ordersTable);
        $hasExpired  = $fieldOk('order_factor_expired', $ordersTable);
        $hasPendingI = $fieldOk('order_pending_invoice', $ordersTable);

        $selectExtra = '';
        if ($hasPaid) {
            $selectExtra .= ', order_paid';
        }
        if ($hasExpired) {
            $selectExtra .= ', order_factor_expired';
        }
        if ($hasPendingI) {
            $selectExtra .= ', order_pending_invoice';
        }

        $clientIds = array_values(array_filter(array_map('intval', $clientByLead)));
        $orderWhere = 'order_lead_id IN (' . $idList . ')';
        if ($clientIds !== []) {
            $orderWhere .= ' OR (order_customer > 0 AND order_customer IN (' . implode(',', $clientIds) . '))';
        }

        $orderRows = $CI->db->query(
            'SELECT order_id, order_lead_id, order_customer, order_invoice_id, order_financial_approval'
            . $selectExtra
            . ' FROM ' . $ordersTable
            . ' WHERE ' . $orderWhere
            . ' ORDER BY order_id DESC'
        )->result_array();

        $clientToLead = array_flip($clientByLead); // userid => leadid
        foreach ($orderRows as $row) {
            $orderId   = (int) $row['order_id'];
            $leadIdOrd = (int) ($row['order_lead_id'] ?? 0);
            $custId    = (int) ($row['order_customer'] ?? 0);
            $targets   = [];
            if ($leadIdOrd > 0 && in_array($leadIdOrd, $ids, true)) {
                $targets[] = $leadIdOrd;
            }
            if ($custId > 0 && isset($clientToLead[$custId])) {
                $targets[] = (int) $clientToLead[$custId];
            }
            $targets = array_values(array_unique($targets));
            foreach ($targets as $lid) {
                if (!isset($anyOrderByLead[$lid])) {
                    $anyOrderByLead[$lid] = $orderId;
                }
                if ((int) ($row['order_financial_approval'] ?? 0) === 0
                    && $leadIdOrd === $lid
                    && !isset($activeByLead[$lid])) {
                    $activeByLead[$lid] = [
                        'order_id'            => $orderId,
                        'order_invoice_id'    => (int) ($row['order_invoice_id'] ?? 0),
                        'order_paid'          => $hasPaid ? (int) ($row['order_paid'] ?? 0) : 0,
                        'order_factor_expired' => $hasExpired ? (int) ($row['order_factor_expired'] ?? 0) : 0,
                        'order_pending_invoice' => $hasPendingI ? (int) ($row['order_pending_invoice'] ?? 0) : 0,
                    ];
                }
            }
        }
    }

    $invoiceById = [];
    $invoiceIds  = [];
    foreach ($activeByLead as $info) {
        $iid = (int) ($info['order_invoice_id'] ?? 0);
        if ($iid > 0) {
            $invoiceIds[] = $iid;
        }
    }
    $invoiceIds = array_values(array_unique($invoiceIds));
    if ($invoiceIds !== [] && $tableOk($prefix . 'invoices')) {
        $rows = $CI->db->query(
            'SELECT id, status, hash FROM ' . $prefix . 'invoices WHERE id IN (' . implode(',', $invoiceIds) . ')'
        )->result_array();
        foreach ($rows as $row) {
            $invoiceById[(int) $row['id']] = [
                'status' => (int) ($row['status'] ?? 0),
                'hash'   => (string) ($row['hash'] ?? ''),
            ];
        }
    }

    $pendingFinByOrder = [];
    $activeOrderIds = [];
    foreach ($activeByLead as $info) {
        $oid = (int) ($info['order_id'] ?? 0);
        if ($oid > 0) {
            $activeOrderIds[] = $oid;
        }
    }
    $activeOrderIds = array_values(array_unique($activeOrderIds));
    $finTable = $prefix . 'siba_fin_approve_req';
    if ($activeOrderIds !== [] && $tableOk($finTable)) {
        $rows = $CI->db->query(
            'SELECT fin_approve_order FROM ' . $finTable
            . ' WHERE fin_approve_order IN (' . implode(',', $activeOrderIds) . ')'
            . ' AND (fin_approve_answer_staff IS NULL OR fin_approve_answer_staff = 0)'
        )->result_array();
        foreach ($rows as $row) {
            $pendingFinByOrder[(int) $row['fin_approve_order']] = 1;
        }
    }

    foreach ($leads as $idx => $lead) {
        $id = (int) ($lead['id'] ?? 0);
        $clientUserId = (int) ($clientByLead[$id] ?? 0);
        $leads[$idx]['is_lead_client'] = $clientUserId > 0 ? '1' : '0';
        $leads[$idx]['client_userid']  = $clientUserId;
        $leads[$idx]['total_files']    = (int) ($filesByLead[$id] ?? 0);
        $leads[$idx]['total_notes']    = (int) ($notesByLead[$id] ?? 0);
        $leads[$idx]['tags']           = (string) ($tagsByLead[$id] ?? '');
        $leads[$idx]['my_active_tasks'] = (int) ($tasksByLead[$id] ?? 0);

        $reminderInfo = $reminderByLead[$id] ?? null;
        if ($reminderInfo && (int) ($reminderInfo['count'] ?? 0) > 0) {
            $earliest = (string) ($reminderInfo['earliest'] ?? '');
            $earliestDay = $earliest !== '' ? substr($earliest, 0, 10) : '';
            $today = date('Y-m-d');
            $status = ($earliestDay !== '' && $earliestDay < $today) ? 'overdue' : 'today';
            $leads[$idx]['reminder_due_count']  = (int) $reminderInfo['count'];
            $leads[$idx]['reminder_due_status'] = $status;
            $leads[$idx]['reminder_due_date']   = $earliest;
            $leads[$idx]['reminder_on_lead']    = !empty($reminderInfo['on_lead']) ? 1 : 0;
            $leads[$idx]['reminder_on_task']    = !empty($reminderInfo['on_task']) ? 1 : 0;
        } else {
            $leads[$idx]['reminder_due_count']  = 0;
            $leads[$idx]['reminder_due_status'] = '';
            $leads[$idx]['reminder_due_date']   = '';
            $leads[$idx]['reminder_on_lead']    = 0;
            $leads[$idx]['reminder_on_task']    = 0;
        }

        $active = $activeByLead[$id] ?? null;
        if ($active) {
            $invoiceId = (int) ($active['order_invoice_id'] ?? 0);
            $inv       = $invoiceById[$invoiceId] ?? ['status' => 0, 'hash' => ''];
            $leads[$idx]['active_order_id']            = (int) $active['order_id'];
            $leads[$idx]['has_pending_fin_req']        = !empty($pendingFinByOrder[(int) $active['order_id']]) ? 1 : 0;
            $leads[$idx]['lead_invoice_id']            = $invoiceId;
            $leads[$idx]['lead_invoice_status']        = (int) $inv['status'];
            $leads[$idx]['lead_invoice_hash']          = (string) $inv['hash'];
            $leads[$idx]['lead_order_paid']            = (int) ($active['order_paid'] ?? 0);
            $leads[$idx]['lead_order_expired']         = (int) ($active['order_factor_expired'] ?? 0);
            $leads[$idx]['lead_order_pending_invoice'] = (int) ($active['order_pending_invoice'] ?? 0);
        } else {
            $leads[$idx]['active_order_id']            = 0;
            $leads[$idx]['has_pending_fin_req']        = 0;
            $leads[$idx]['lead_invoice_id']            = 0;
            $leads[$idx]['lead_invoice_status']        = 0;
            $leads[$idx]['lead_invoice_hash']          = '';
            $leads[$idx]['lead_order_paid']            = 0;
            $leads[$idx]['lead_order_expired']         = 0;
            $leads[$idx]['lead_order_pending_invoice'] = 0;
        }
        $leads[$idx]['existing_order_id'] = (int) ($anyOrderByLead[$id] ?? 0);
    }

    if (function_exists('siba_leads_annotate_phone_duplicates')) {
        $leads = siba_leads_annotate_phone_duplicates($leads);
    }
    if (function_exists('siba_leads_annotate_related_customers')) {
        $leads = siba_leads_annotate_related_customers($leads);
    }

    return $leads;
}

/**
 * Outcome banner data for a lead row/object.
 *
 * @param object|array $lead
 * @return array<string,mixed>|null
 */
function siba_leads_outcome_banner_data($lead): ?array
{
    if (is_object($lead)) {
        $lead = (array) $lead;
    }
    if (!is_array($lead)) {
        return null;
    }

    $outcome = strtolower(trim((string) ($lead['siba_outcome'] ?? '')));
    if ($outcome === '' && !empty($lead['lost'])) {
        $outcome = 'failed';
    }
    if ($outcome === '' && !empty($lead['date_converted'])
        && $lead['date_converted'] !== '0000-00-00 00:00:00') {
        $outcome = 'success';
    }
    if (!in_array($outcome, ['success', 'failed'], true)) {
        return null;
    }

    $CI = &get_instance();
    $reasonTitle = '';
    $reasonColor = '';
    $reasonId = (int) ($lead['siba_failure_reason_id'] ?? 0);
    if ($outcome === 'failed' && $reasonId > 0) {
        $table = db_prefix() . 'siba_leads_failure_reasons';
        if ($CI->db->table_exists($table)) {
            $row = $CI->db->where('id', $reasonId)->get($table)->row_array();
            if ($row) {
                $reasonTitle = (string) ($row['title'] ?? '');
                $reasonColor = (string) ($row['color'] ?? '');
            }
        }
    }

    $byId = (int) ($lead['siba_outcome_by'] ?? 0);
    $at   = (string) ($lead['siba_outcome_at'] ?? '');
    if ($at === '' && $outcome === 'success') {
        $at = (string) ($lead['date_converted'] ?? '');
    }

    $failureDesc = (string) ($lead['siba_failure_description'] ?? '');

    return [
        'outcome'             => $outcome,
        'how'                 => (string) ($lead['siba_outcome_how'] ?? ''),
        'how_label'           => siba_leads_outcome_how_label($lead['siba_outcome_how'] ?? ''),
        'at'                  => $at,
        'by'                  => $byId,
        'by_name'             => $byId > 0 ? get_staff_full_name($byId) : _l('system_default_string'),
        'reason_id'           => $reasonId,
        'reason_title'        => $reasonTitle,
        'reason_color'        => $reasonColor,
        'failure_description' => $failureDesc,
        'label'               => $outcome === 'success'
            ? _l('siba_leads_outcome_success')
            : _l('siba_leads_outcome_failed'),
    ];
}

/**
 * Render outcome banner into lead modal (full-width, above tab panes).
 *
 * @param object|array|int|null $lead
 */
function siba_leads_lead_modal_outcome_banner($lead = null): void
{
    if ($lead === null || $lead === '' || $lead === 0) {
        return;
    }

    $CI = &get_instance();

    if (is_numeric($lead)) {
        $leadId = (int) $lead;
        if ($leadId < 1) {
            return;
        }
        if (!class_exists('leads_model', false)) {
            $CI->load->model('leads_model');
        }
        $lead = $CI->leads_model->get($leadId);
    }

    if (!$lead) {
        return;
    }

    // Ensure audit columns are present on the lead object for older rows.
    if (is_object($lead) && !isset($lead->siba_outcome) && !empty($lead->id)
        && $CI->db->field_exists('siba_outcome', db_prefix() . 'leads')) {
        $row = $CI->db->select('siba_outcome, siba_outcome_at, siba_outcome_by, siba_outcome_how, siba_failure_reason_id, siba_failure_description')
            ->from(db_prefix() . 'leads')
            ->where('id', (int) $lead->id)
            ->get()
            ->row_array();
        if ($row) {
            foreach ($row as $k => $v) {
                $lead->{$k} = $v;
            }
        }
    }

    $banner = siba_leads_outcome_banner_data($lead);
    if (!$banner) {
        return;
    }

    $CI->load->view('siba_leads/partials/outcome_banner', ['banner' => $banner]);
}

/**
 * Build lead → order action meta for modal/card reuse.
 *
 * @param object|array|int $lead
 * @return array{href:?string,label:string,icon:string,class:string,onclick:?string}|null
 */
function siba_leads_lead_order_action($lead)
{
    if (!staff_can('creat_order', 'siba_license')) {
        return null;
    }

    $CI = &get_instance();
    if (is_numeric($lead)) {
        $leadId = (int) $lead;
        if ($leadId < 1) {
            return null;
        }
        if (!class_exists('leads_model', false)) {
            $CI->load->model('leads_model');
        }
        $lead = $CI->leads_model->get($leadId);
    }

    if (!$lead) {
        return null;
    }

    $asArray = is_array($lead) ? $lead : (array) $lead;
    $leadId  = (int) ($asArray['id'] ?? 0);
    if ($leadId < 1) {
        return null;
    }

    $leadIsClient = false;
    $clientUserId = 0;
    $clientRow = $CI->db->select('userid')
        ->from(db_prefix() . 'clients')
        ->where('leadid', $leadId)
        ->limit(1)
        ->get()
        ->row();
    if ($clientRow) {
        $leadIsClient = true;
        $clientUserId = (int) $clientRow->userid;
    }

    if ($clientUserId < 1 && !empty($asArray['related_client_userid'])) {
        $clientUserId = (int) $asArray['related_client_userid'];
    }

    $activeOrderId = (int) ($asArray['active_order_id'] ?? 0);
    $existingOrderId = (int) ($asArray['existing_order_id'] ?? 0);
    $pendingFinReq = (int) ($asArray['has_pending_fin_req'] ?? 0) === 1;
    $invoiceId = (int) ($asArray['lead_invoice_id'] ?? 0);
    $invoiceStatus = (int) ($asArray['lead_invoice_status'] ?? 0);
    $orderExpiredFlag = (int) ($asArray['lead_order_expired'] ?? 0) === 1;

    // Modal lead object usually lacks kanban annotations — load from DB when needed.
    if ($activeOrderId < 1 && $CI->db->table_exists(db_prefix() . 'siba_orders')) {
        $orders = db_prefix() . 'siba_orders';
        $active = $CI->db->select('order_id, order_invoice_id, order_factor_expired')
            ->from($orders)
            ->where('order_lead_id', $leadId)
            ->where('order_financial_approval', 0)
            ->order_by('order_id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        if ($active) {
            $activeOrderId = (int) ($active['order_id'] ?? 0);
            if ($invoiceId < 1) {
                $invoiceId = (int) ($active['order_invoice_id'] ?? 0);
            }
            if (!$orderExpiredFlag && isset($active['order_factor_expired'])) {
                $orderExpiredFlag = (int) $active['order_factor_expired'] === 1;
            }
        }
        if ($existingOrderId < 1) {
            $CI->db->select('order_id')
                ->from($orders)
                ->group_start()
                    ->where('order_lead_id', $leadId);
            if ($clientUserId > 0) {
                $CI->db->or_where('order_customer', $clientUserId);
            }
            $anyRow = $CI->db->group_end()
                ->order_by('order_id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();
            $existingOrderId = (int) ($anyRow['order_id'] ?? 0);
        }
        if ($activeOrderId > 0 && $CI->db->table_exists(db_prefix() . 'siba_fin_approve_req')) {
            $pending = $CI->db->select('1', false)
                ->from(db_prefix() . 'siba_fin_approve_req')
                ->where('fin_approve_order', $activeOrderId)
                ->group_start()
                ->where('fin_approve_answer_staff IS NULL', null, false)
                ->or_where('fin_approve_answer_staff', 0)
                ->group_end()
                ->limit(1)
                ->get()
                ->row();
            $pendingFinReq = !empty($pending);
        }
        if ($invoiceId > 0 && $invoiceStatus < 1 && $CI->db->table_exists(db_prefix() . 'invoices')) {
            $inv = $CI->db->select('status')
                ->from(db_prefix() . 'invoices')
                ->where('id', $invoiceId)
                ->limit(1)
                ->get()
                ->row();
            $invoiceStatus = (int) ($inv->status ?? 0);
        }
    }

    if ($activeOrderId > 0) {
        $invoicePaid = $invoiceId > 0 && $invoiceStatus === 2;
        $invoiceExpired = $invoiceId > 0 && $invoiceStatus === 5;
        $orderExpired = $orderExpiredFlag || $invoiceExpired;
        $needsPayment = $invoiceId > 0 && !$invoicePaid && !$invoiceExpired;

        if ($orderExpired) {
            return [
                'href'    => null,
                'label'   => _l('siba_leads_card_invoice_expired'),
                'icon'    => 'fa fa-ban',
                'class'   => 'siba-lead-modal-order-tab is-expired',
                'onclick' => null,
            ];
        }
        if ($needsPayment) {
            return [
                'href'    => admin_url('invoices/list_invoices/' . $invoiceId),
                'label'   => _l('siba_leads_card_awaiting_payment'),
                'icon'    => 'fa fa-clock',
                'class'   => 'siba-lead-modal-order-tab is-awaiting',
                'onclick' => null,
            ];
        }

        $financeReady = true;
        $financeMissingMsg = '';
        if (!$leadIsClient && function_exists('siba_leads_lead_order_readiness')) {
            $readiness = siba_leads_lead_order_readiness(is_object($lead) ? $lead : $asArray);
            $financeReady = !empty($readiness['ok']);
            if (!$financeReady) {
                $missingText = implode('، ', $readiness['missing_labels'] ?? []);
                $financeMissingMsg = $missingText !== ''
                    ? _l('siba_leads_finance_incomplete', $missingText)
                    : _l('siba_leads_finance_incomplete_short');
            }
        }

        if (!$financeReady && !$pendingFinReq) {
            return [
                'href'    => '#',
                'label'   => _l('siba_leads_card_complete_lead'),
                'icon'    => 'fa fa-user-edit',
                'class'   => 'siba-lead-modal-order-tab is-incomplete',
                'onclick' => 'if (typeof siba_leads_prompt_complete_lead === \'function\') { siba_leads_prompt_complete_lead(' . $leadId . ', ' . json_encode($financeMissingMsg, JSON_UNESCAPED_UNICODE) . '); } else if (typeof init_lead === \'function\') { init_lead(' . $leadId . ', true); } return false;',
            ];
        }

        $finLabel = $pendingFinReq
            ? _l('siba_license_finan_approl_requested')
            : _l('siba_license_finan_approl_req');

        return [
            'href'    => '#',
            'label'   => $finLabel,
            'icon'    => $pendingFinReq ? 'fa fa-hourglass-half' : 'fa fa-file-invoice-dollar',
            'class'   => 'siba-lead-modal-order-tab ' . ($pendingFinReq ? 'is-requested' : 'is-finance'),
            'onclick' => 'siba_leads_add_fin_approve_req(' . $activeOrderId . '); return false;',
        ];
    }

    if ($leadIsClient && $existingOrderId > 0) {
        $hasOrderUrl = $clientUserId > 0
            ? admin_url('clients/client/' . $clientUserId . '?group=siba_license_reports')
            : admin_url('siba_license/manage_orders');

        return [
            'href'    => $hasOrderUrl,
            'label'   => _l('siba_leads_card_has_order'),
            'icon'    => 'fa fa-check',
            'class'   => 'siba-lead-modal-order-tab is-has-order',
            'onclick' => null,
        ];
    }

    $orderUrl = $clientUserId > 0
        ? admin_url('siba_license/show_add_orders/' . $clientUserId)
        : admin_url('siba_license/show_add_orders/' . $leadId . '/lead');

    return [
        'href'    => $orderUrl,
        'label'   => _l('siba_leads_card_add_order'),
        'icon'    => 'fa fa-cart-plus',
        'class'   => 'siba-lead-modal-order-tab is-add-order',
        'onclick' => null,
    ];
}

/**
 * Lead modal tab item: ثبت سفارش (same flows as kanban card button).
 *
 * @param object|array|null $lead
 */
function siba_leads_lead_modal_order_tab($lead = null): void
{
    if ($lead === null || $lead === '' || $lead === 0) {
        return;
    }

    $action = siba_leads_lead_order_action($lead);
    if ($action === null) {
        return;
    }

    $href = $action['href'] !== null && $action['href'] !== '' ? $action['href'] : '#';
    $onclick = !empty($action['onclick'])
        ? ' onclick="' . htmlspecialchars($action['onclick'], ENT_QUOTES, 'UTF-8') . '"'
        : '';

    echo '<li role="presentation" class="' . e($action['class']) . '">';
    if ($action['href'] === null) {
        echo '<span class="siba-lead-modal-order-tab__link is-disabled" title="' . e($action['label']) . '">';
        echo '<i class="' . e($action['icon']) . ' menu-icon"></i>';
        echo e($action['label']);
        echo '</span>';
    } else {
        echo '<a href="' . e($href) . '"' . $onclick . ' title="' . e($action['label']) . '">';
        echo '<i class="' . e($action['icon']) . ' menu-icon"></i>';
        echo e($action['label']);
        echo '</a>';
    }
    echo '</li>';
}

/**
 * Clear request-scoped Perfex tags cache after CRUD.
 */
function siba_leads_clear_tags_cache(): void
{
    $CI = &get_instance();
    if (isset($CI->app_object_cache) && method_exists($CI->app_object_cache, 'delete')) {
        $CI->app_object_cache->delete('db-tags-array');
    }
}

/**
 * Keep only tags that already exist in tbltags (no auto-create).
 * Returns the same type as input when possible (array or comma string).
 *
 * @param mixed $tags
 * @return mixed
 */
function siba_leads_restrict_tags_to_existing($tags)
{
    if ($tags === null || $tags === '' || $tags === false) {
        return $tags;
    }

    $returnArray = is_array($tags);
    if (!$returnArray) {
        $tags = explode(',', (string) $tags);
    }

    $existing = function_exists('get_tags_clean') ? get_tags_clean() : [];
    if (!is_array($existing) || $existing === []) {
        return $returnArray ? [] : '';
    }

    $map = [];
    foreach ($existing as $name) {
        $name = trim((string) $name);
        if ($name === '') {
            continue;
        }
        $map[function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name)] = $name;
    }

    $out = [];
    foreach ($tags as $tag) {
        $tag = trim((string) $tag);
        if ($tag === '') {
            continue;
        }
        $key = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
        if (isset($map[$key])) {
            $out[$map[$key]] = $map[$key];
        }
    }

    $out = array_values($out);

    return $returnArray ? $out : implode(',', $out);
}

/**
 * Resolve live tag name for a pipeline map row (prefer tbltags by id).
 *
 * @param array<string,mixed> $map
 * @return array{tag_id:int,tag_name:string,didar_pipeline_id:string}|null
 */
function siba_leads_normalize_pipeline_tag_map(?array $map): ?array
{
    if (!is_array($map)) {
        return null;
    }

    $tagId   = (int) ($map['tag_id'] ?? 0);
    $tagName = trim((string) ($map['tag_name'] ?? ''));
    $pipeId  = trim((string) ($map['didar_pipeline_id'] ?? ''));
    $CI      = &get_instance();
    $tagsTbl = db_prefix() . 'tags';

    if ($tagId > 0 && $CI->db->table_exists($tagsTbl)) {
        $row = $CI->db->select('id, name')->where('id', $tagId)->get($tagsTbl)->row();
        if ($row) {
            $tagId   = (int) $row->id;
            $tagName = trim((string) $row->name);
        } else {
            $tagId = 0;
        }
    }

    if ($tagId < 1 && $tagName !== '' && function_exists('siba_leads_didar_ensure_tag_row')) {
        $ensured = siba_leads_didar_ensure_tag_row($tagName);
        $tagId   = (int) ($ensured['id'] ?? 0);
        $tagName = trim((string) ($ensured['name'] ?? $tagName));
    }

    if ($tagId < 1 || $tagName === '') {
        return null;
    }

    return [
        'tag_id'            => $tagId,
        'tag_name'          => $tagName,
        'didar_pipeline_id' => $pipeId,
    ];
}

/**
 * Status id preferred for pipeline tagging (API may set this before RR rewrites status).
 */
function siba_leads_requested_pipeline_status(): int
{
    return (int) ($GLOBALS['siba_leads_requested_pipeline_status'] ?? 0);
}

/**
 * Remember the client-selected status for pipeline tagging (cleared after create).
 */
function siba_leads_set_requested_pipeline_status(int $statusId): void
{
    if ($statusId > 0) {
        $GLOBALS['siba_leads_requested_pipeline_status'] = $statusId;
    } else {
        unset($GLOBALS['siba_leads_requested_pipeline_status']);
    }
}

/**
 * Resolve which pipeline tag a new/manual/API lead should get.
 * Prefer status→pipeline map; else remembered API status; else active board; else first mapped pipeline.
 *
 * @return array{tag_id:int,tag_name:string,didar_pipeline_id:string}|null
 */
function siba_leads_resolve_pipeline_tag_for_lead(?int $statusId = null): ?array
{
    $candidates = [];
    if ($statusId !== null && $statusId > 0) {
        $candidates[] = $statusId;
    }
    $requested = siba_leads_requested_pipeline_status();
    if ($requested > 0 && !in_array($requested, $candidates, true)) {
        $candidates[] = $requested;
    }

    if (function_exists('siba_leads_didar_pipeline_map_for_status')) {
        foreach ($candidates as $sid) {
            $fromStatus = siba_leads_normalize_pipeline_tag_map(siba_leads_didar_pipeline_map_for_status($sid));
            if ($fromStatus !== null) {
                return $fromStatus;
            }
        }
    }

    if (function_exists('siba_leads_active_pipeline_map')) {
        $fromActive = siba_leads_normalize_pipeline_tag_map(siba_leads_active_pipeline_map());
        if ($fromActive !== null) {
            return $fromActive;
        }
    }

    if (function_exists('siba_leads_didar_list_pipeline_maps')) {
        foreach (siba_leads_didar_list_pipeline_maps() as $row) {
            $fromFirst = siba_leads_normalize_pipeline_tag_map(is_array($row) ? $row : null);
            if ($fromFirst !== null) {
                return $fromFirst;
            }
        }
    }

    return null;
}

/**
 * Merge the pipeline tag name for $statusId into a tags string/array.
 *
 * @param mixed $tags
 * @return mixed
 */
function siba_leads_merge_pipeline_tag_into_tags($tags, ?int $statusId = null)
{
    $resolved = siba_leads_resolve_pipeline_tag_for_lead($statusId);
    if ($resolved === null) {
        return $tags;
    }

    $tagName = trim((string) ($resolved['tag_name'] ?? ''));
    if ($tagName === '') {
        return $tags;
    }

    $returnArray = is_array($tags);
    $list        = $returnArray ? $tags : explode(',', (string) ($tags ?? ''));
    $merged      = [];
    $have        = false;
    $needle      = function_exists('mb_strtolower') ? mb_strtolower($tagName, 'UTF-8') : strtolower($tagName);

    foreach ($list as $name) {
        $name = trim((string) $name);
        if ($name === '') {
            continue;
        }
        $merged[] = $name;
        $cmp = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        if ($cmp === $needle) {
            $have = true;
        }
    }
    if (!$have) {
        $merged[] = $tagName;
    }

    return $returnArray ? $merged : implode(',', $merged);
}

/**
 * before_lead_added: ensure pipeline kanban tag is present based on status.
 * Runs after assignment may rewrite status; still honors remembered API status.
 *
 * @param mixed $data
 * @return mixed
 */
function siba_leads_filter_inject_pipeline_tag($data)
{
    if (!is_array($data) || !empty($GLOBALS['siba_leads_didar_import'])) {
        return $data;
    }

    $statusId = (int) ($data['status'] ?? 0);
    $data['tags'] = siba_leads_merge_pipeline_tag_into_tags($data['tags'] ?? '', $statusId > 0 ? $statusId : null);

    return $data;
}

/**
 * Attach pipeline tag by tag_id (board filters by id, not name).
 * Drops other scoped Didar pipeline tags on the lead.
 */
function siba_leads_ensure_lead_pipeline_tag(int $leadId, ?int $statusId = null): bool
{
    if ($leadId < 1 || !empty($GLOBALS['siba_leads_didar_import'])) {
        return false;
    }

    $CI = &get_instance();
    if ($statusId === null || $statusId < 1) {
        $row = $CI->db->select('status')->where('id', $leadId)->get(db_prefix() . 'leads')->row();
        $statusId = $row ? (int) $row->status : 0;
    }

    $resolved = siba_leads_resolve_pipeline_tag_for_lead($statusId > 0 ? $statusId : null);
    if ($resolved === null) {
        return false;
    }

    $tagId = (int) $resolved['tag_id'];
    if ($tagId < 1) {
        return false;
    }

    $taggables = db_prefix() . 'taggables';
    if (!$CI->db->table_exists($taggables)) {
        return false;
    }

    $pipelineTagIds = [];
    if (function_exists('siba_leads_didar_list_pipeline_maps')) {
        foreach (siba_leads_didar_list_pipeline_maps() as $row) {
            $pid = (int) ($row['tag_id'] ?? 0);
            if ($pid > 0) {
                $pipelineTagIds[] = $pid;
            }
        }
    }
    $pipelineTagIds = array_values(array_unique($pipelineTagIds));

    $changed = false;

    // Drop other pipeline tags so the lead belongs to one board.
    if ($pipelineTagIds !== []) {
        $others = array_values(array_filter($pipelineTagIds, static function ($id) use ($tagId) {
            return (int) $id !== $tagId;
        }));
        if ($others !== []) {
            $CI->db->where('rel_id', $leadId);
            $CI->db->where('rel_type', 'lead');
            $CI->db->where_in('tag_id', $others);
            $CI->db->delete($taggables);
            if ($CI->db->affected_rows() > 0) {
                $changed = true;
            }
        }
    }

    $exists = (int) total_rows($taggables, [
        'tag_id'   => $tagId,
        'rel_id'   => $leadId,
        'rel_type' => 'lead',
    ]);
    if ($exists < 1) {
        $maxOrder = 0;
        $orderRow = $CI->db->select_max('tag_order')
            ->where('rel_id', $leadId)
            ->where('rel_type', 'lead')
            ->get($taggables)
            ->row();
        if ($orderRow && isset($orderRow->tag_order)) {
            $maxOrder = (int) $orderRow->tag_order;
        }
        $CI->db->insert($taggables, [
            'tag_id'    => $tagId,
            'rel_id'    => $leadId,
            'rel_type'  => 'lead',
            'tag_order' => $maxOrder + 1,
        ]);
        if ($CI->db->affected_rows() > 0) {
            $changed = true;
        }
    }

    return $changed;
}

/**
 * lead_created — assign active/status pipeline tag for manual + API + web leads.
 *
 * @param int|array $payload
 */
function siba_leads_action_ensure_pipeline_tag($payload): void
{
    if (!empty($GLOBALS['siba_leads_didar_import'])) {
        return;
    }

    $leadId = is_array($payload) ? (int) ($payload['lead_id'] ?? 0) : (int) $payload;
    if ($leadId < 1) {
        return;
    }

    $statusId = siba_leads_requested_pipeline_status();
    siba_leads_ensure_lead_pipeline_tag($leadId, $statusId > 0 ? $statusId : null);
    unset($GLOBALS['siba_leads_requested_pipeline_status']);
}

/**
 * When a lead moves into a mapped pipeline column, keep its pipeline tag in sync.
 *
 * @param array<string,mixed> $data
 */
function siba_leads_action_lead_status_changed_pipeline_tag($data): void
{
    if (!is_array($data) || !empty($GLOBALS['siba_leads_didar_import'])) {
        return;
    }

    $leadId = (int) ($data['lead_id'] ?? 0);
    if ($leadId < 1) {
        return;
    }

    $CI  = &get_instance();
    $row = $CI->db->select('status')->where('id', $leadId)->get(db_prefix() . 'leads')->row();
    $statusId = $row ? (int) $row->status : 0;
    if ($statusId < 1) {
        return;
    }

    // Only switch tags when the new status belongs to a known pipeline board.
    if (!function_exists('siba_leads_didar_pipeline_map_for_status')
        || siba_leads_didar_pipeline_map_for_status($statusId) === null) {
        return;
    }

    siba_leads_ensure_lead_pipeline_tag($leadId, $statusId);
}

/**
 * before_lead_added / email-integration: drop unknown tags so App_tags won't create them.
 *
 * @param mixed $data
 * @return mixed
 */
function siba_leads_filter_lead_tags_no_autocreate($data)
{
    if (!is_array($data) || !array_key_exists('tags', $data)) {
        return $data;
    }
    $data['tags'] = siba_leads_restrict_tags_to_existing($data['tags']);

    return $data;
}

/**
 * Sanitize lead POST tags on create/update (covers update path with no before_update filter).
 */
function siba_leads_intercept_lead_tags_post(): void
{
    $CI = &get_instance();
    $class  = strtolower((string) $CI->router->fetch_class());
    $method = strtolower((string) $CI->router->fetch_method());
    if ($class !== 'leads' || $method !== 'lead') {
        return;
    }
    if (!$CI->input->post()) {
        return;
    }
    if (!array_key_exists('tags', $_POST)) {
        return;
    }
    $_POST['tags'] = siba_leads_restrict_tags_to_existing($_POST['tags']);
}

/**
 * Normalize raw tag filter input into positive integer IDs.
 *
 * @param mixed $raw
 * @return int[]
 */
function siba_leads_normalize_tag_ids($raw): array
{
    if ($raw === null || $raw === '' || $raw === false) {
        return [];
    }
    if (!is_array($raw)) {
        $raw = preg_split('/\s*,\s*/', (string) $raw) ?: [];
    }
    $ids = [];
    foreach ($raw as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

/**
 * Apply lead tag filter to the active Query Builder.
 *
 * @param object $db CI_DB_query_builder
 * @param string $leadIdColumn e.g. tblleads.id
 * @param int[]  $tagIds
 * @param bool   $tagNone only untagged leads
 */
function siba_leads_apply_tag_filter_where($db, string $leadIdColumn, array $tagIds = [], bool $tagNone = false): void
{
    $taggables = db_prefix() . 'taggables';
    if ($tagNone) {
        $db->where(
            $leadIdColumn . ' NOT IN (SELECT rel_id FROM ' . $taggables . " WHERE rel_type = 'lead')",
            null,
            false
        );

        return;
    }

    $tagIds = siba_leads_normalize_tag_ids($tagIds);
    if ($tagIds === []) {
        return;
    }

    $db->where(
        $leadIdColumn . ' IN (SELECT rel_id FROM ' . $taggables
        . " WHERE rel_type = 'lead' AND tag_id IN (" . implode(',', array_map('intval', $tagIds)) . '))',
        null,
        false
    );
}

/**
 * Read tag filter from current request (GET or POST).
 *
 * @return array{tag_ids:int[], tag_none:bool}
 */
function siba_leads_request_tag_filter(): array
{
    $CI = &get_instance();
    $rawTags = $CI->input->get_post('tags');
    if ($rawTags === null || $rawTags === '') {
        $rawTags = $CI->input->get_post('siba_leads_tags');
    }
    $tagNone = $CI->input->get_post('tag_none');
    if ($tagNone === null || $tagNone === '') {
        $tagNone = $CI->input->get_post('siba_leads_tag_none');
    }

    return [
        'tag_ids'  => siba_leads_normalize_tag_ids($rawTags),
        'tag_none' => ((string) $tagNone === '1' || (string) $tagNone === 'true'),
    ];
}

/**
 * Tag filter actually used by the board: request tags, or active pipeline tag when none set.
 * Shared by summary counts and kanban card queries so the header total matches visible cards.
 *
 * @return array{tag_ids:int[], tag_none:bool}
 */
function siba_leads_effective_tag_filter(): array
{
    $filter  = siba_leads_request_tag_filter();
    $tagIds  = $filter['tag_ids'];
    $tagNone = $filter['tag_none'];

    // Active pipeline board: lock to that pipeline tag unless the user overrides.
    if (!$tagNone && $tagIds === [] && function_exists('siba_leads_active_pipeline_map')) {
        $pipeline = siba_leads_active_pipeline_map();
        $tagId    = (int) ($pipeline['tag_id'] ?? 0);
        if ($tagId > 0) {
            $tagIds = [$tagId];
        }
    }

    return [
        'tag_ids'  => $tagIds,
        'tag_none' => $tagNone,
    ];
}

/**
 * Raw SQL AND-fragment for the effective tag filter (for UNION summaries).
 */
function siba_leads_tag_filter_sql(string $leadIdColumn = ''): string
{
    if ($leadIdColumn === '') {
        $leadIdColumn = db_prefix() . 'leads.id';
    }

    $filter    = siba_leads_effective_tag_filter();
    $taggables = db_prefix() . 'taggables';

    if ($filter['tag_none']) {
        return ' AND ' . $leadIdColumn . ' NOT IN (SELECT rel_id FROM ' . $taggables . " WHERE rel_type = 'lead')";
    }

    $tagIds = siba_leads_normalize_tag_ids($filter['tag_ids']);
    if ($tagIds === []) {
        return '';
    }

    return ' AND ' . $leadIdColumn . ' IN (SELECT rel_id FROM ' . $taggables
        . " WHERE rel_type = 'lead' AND tag_id IN (" . implode(',', array_map('intval', $tagIds)) . '))';
}

/**
 * Attach tag filter to a Siba kanban instance via tapQuery.
 *
 * @param \modules\siba_leads\services\SibaLeadsKanban $kanBan
 * @return \modules\siba_leads\services\SibaLeadsKanban
 */
function siba_leads_kanban_apply_tag_filter($kanBan)
{
    $filter  = siba_leads_effective_tag_filter();
    $tagIds  = $filter['tag_ids'];
    $tagNone = $filter['tag_none'];

    if (!$tagNone && $tagIds === []) {
        return $kanBan;
    }

    $leadCol = db_prefix() . 'leads.id';

    return $kanBan->tapQuery(static function ($status, $ci) use ($leadCol, $tagIds, $tagNone) {
        siba_leads_apply_tag_filter_where($ci->db, $leadCol, $tagIds, $tagNone);
    });
}

