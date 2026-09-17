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
        $payload['lost']                   = 1;
        $payload['junk']                   = 0;
        $payload['status']                 = 0;
    } else {
        $payload['siba_failure_reason_id']  = null;
        $payload['siba_failed_from_status'] = null;
        $payload['lost']                    = 0;
        $payload['junk']                    = 0;
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
            $CI->leads_model->log_lead_activity(
                $lead_id,
                'siba_leads_activity_marked_failed',
                false,
                serialize([$who, $reasonTitle !== '' ? $reasonTitle : '#', siba_leads_outcome_how_label($how)])
            );
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

    return [
        'outcome'       => $outcome,
        'how'           => (string) ($lead['siba_outcome_how'] ?? ''),
        'how_label'     => siba_leads_outcome_how_label($lead['siba_outcome_how'] ?? ''),
        'at'            => $at,
        'by'            => $byId,
        'by_name'       => $byId > 0 ? get_staff_full_name($byId) : _l('system_default_string'),
        'reason_id'     => $reasonId,
        'reason_title'  => $reasonTitle,
        'reason_color'  => $reasonColor,
        'label'         => $outcome === 'success'
            ? _l('siba_leads_outcome_success')
            : _l('siba_leads_outcome_failed'),
    ];
}

/**
 * Render outcome banner into lead modal (after_lead_lead_tabs passes lead object).
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
        $row = $CI->db->select('siba_outcome, siba_outcome_at, siba_outcome_by, siba_outcome_how, siba_failure_reason_id')
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
