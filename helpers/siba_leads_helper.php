<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/siba_leads_teams_helper.php';
require_once __DIR__ . '/siba_leads_assignment_helper.php';
require_once __DIR__ . '/siba_leads_phone_helper.php';
require_once __DIR__ . '/siba_leads_location_helper.php';
require_once __DIR__ . '/siba_leads_profile_helper.php';
require_once __DIR__ . '/siba_leads_website_payload_helper.php';
require_once __DIR__ . '/siba_leads_reports_helper.php';

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
 * Status summary totals/values, respecting Siba view_all permission.
 */
function siba_leads_get_summary(): array
{
    $CI = &get_instance();
    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    $statuses   = $CI->leads_model->get_status();
    $canViewAll = siba_leads_can_view_all();
    $staffId    = (int) get_staff_user_id();
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

