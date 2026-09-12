<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/siba_leads_teams_helper.php';
require_once __DIR__ . '/siba_leads_assignment_helper.php';
require_once __DIR__ . '/siba_leads_phone_helper.php';
require_once __DIR__ . '/siba_leads_location_helper.php';
require_once __DIR__ . '/siba_leads_profile_helper.php';
require_once __DIR__ . '/siba_leads_website_payload_helper.php';

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
    ];
}

/**
 * Add extra lead columns when missing (install + already-activated sites).
 */
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
