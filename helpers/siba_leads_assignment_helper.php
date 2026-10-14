<?php

defined('BASEPATH') or exit('No direct script access allowed');

define('SIBA_LEADS_BACKLOG_OPTION', 'siba_leads_backlog_status_id');
define('SIBA_LEADS_RR_OPTION', 'siba_leads_sales_rr_index');
define('SIBA_LEADS_AUTO_ASSIGN_OPTION', 'siba_leads_auto_assign_sales');

/**
 * Whether round-robin sales assignment is enabled.
 */
function siba_leads_auto_assign_enabled(): bool
{
    $val = get_option(SIBA_LEADS_AUTO_ASSIGN_OPTION);

    return $val === false || $val === '' || $val === '1';
}

/**
 * Ensure the Backlog lead status exists and return its id.
 */
function siba_leads_ensure_backlog_status(): int
{
    $CI = &get_instance();
    $CI->load->model('leads_model');

    $stored = (int) get_option(SIBA_LEADS_BACKLOG_OPTION);
    if ($stored > 0) {
        $existing = $CI->leads_model->get_status($stored);
        if ($existing) {
            return $stored;
        }
    }

    // Match by known names (EN/FA).
    $names = ['Backlog', 'backlog', 'بک‌لاگ', 'بک لاگ', 'بکلاگ'];
    foreach ($CI->leads_model->get_status() as $status) {
        if (in_array($status['name'], $names, true)) {
            update_option(SIBA_LEADS_BACKLOG_OPTION, (int) $status['id']);

            return (int) $status['id'];
        }
    }

    $id = (int) $CI->leads_model->add_status([
        'name'        => _l('siba_leads_backlog') ?: 'Backlog',
        'statusorder' => 0,
        'color'       => '#64748b',
        'isdefault'   => 0,
    ]);

    if ($id > 0) {
        update_option(SIBA_LEADS_BACKLOG_OPTION, $id);
        if (isset($CI->app_object_cache)) {
            // Status list is cached; clear so kanban picks up the new column.
            $CI->app_object_cache->delete('leads-all-statuses-' . md5(serialize([])));
        }
    }

    return $id;
}

/**
 * Pick next sales assignee (round-robin) or backlog when team is empty.
 *
 * Uses an in-request counter because Perfex get_option() reads a cached
 * App::$options map that update_option() does not refresh mid-request.
 * Without this, CSV batch imports assign every row to the same staff.
 *
 * @return array{assigned:int,status?:int,mode:string,staff_id?:int}
 */
function siba_leads_next_assignment(array $current = []): array
{
    $backlogId = siba_leads_ensure_backlog_status();
    $members   = siba_leads_get_team_member_ids('sales', true);

    if (empty($members)) {
        return [
            'assigned' => 0,
            'status'   => $backlogId,
            'mode'     => 'backlog',
        ];
    }

    if (!isset($GLOBALS['siba_leads_rr_index'])) {
        $idx = (int) get_option(SIBA_LEADS_RR_OPTION);
        $GLOBALS['siba_leads_rr_index'] = $idx < 0 ? 0 : $idx;
    }

    $idx  = (int) $GLOBALS['siba_leads_rr_index'];
    $pick = (int) $members[$idx % count($members)];
    $GLOBALS['siba_leads_rr_index'] = $idx + 1;
    update_option(SIBA_LEADS_RR_OPTION, (string) $GLOBALS['siba_leads_rr_index']);

    $result = [
        'assigned' => $pick,
        'mode'     => 'sales',
        'staff_id' => $pick,
    ];

    $currentStatus = isset($current['status']) ? (int) $current['status'] : 0;
    if ($currentStatus === $backlogId || $currentStatus === 0) {
        $default = (int) get_option('leads_default_status');
        if ($default > 0 && $default !== $backlogId) {
            $result['status'] = $default;
        }
    }

    return $result;
}

/**
 * If this lead's phone matches an earlier lead, reuse that lead's assignee.
 *
 * @param array $current Lead payload (needs phonenumber; optional id to exclude)
 * @return array{assigned:int,status?:int,mode:string,main_lead_id:int}|null
 */
function siba_leads_assignment_from_duplicate_phone(array $current = []): ?array
{
    $phone = trim((string) ($current['phonenumber'] ?? ''));
    if ($phone === '' || !function_exists('siba_leads_phone_duplicate_info')) {
        return null;
    }

    $excludeId = (int) ($current['id'] ?? 0);
    $info      = siba_leads_phone_duplicate_info($phone, $excludeId);
    $mainId    = (int) ($info['existing_id'] ?? 0);
    if (empty($info['is_duplicate']) || $mainId < 1) {
        return null;
    }

    $CI = &get_instance();
    $CI->db->select('id, assigned, status');
    $CI->db->where('id', $mainId);
    $main = $CI->db->get(db_prefix() . 'leads')->row();
    if (!$main || (int) $main->assigned < 1) {
        // Oldest sibling unassigned — try any older sibling with an assignee.
        $siblings = $info['sibling_ids'] ?? [];
        foreach ($siblings as $siblingId) {
            $siblingId = (int) $siblingId;
            if ($siblingId < 1 || $siblingId === $excludeId) {
                continue;
            }
            $CI->db->select('id, assigned, status');
            $CI->db->where('id', $siblingId);
            $row = $CI->db->get(db_prefix() . 'leads')->row();
            if ($row && (int) $row->assigned > 0) {
                $main = $row;
                $mainId = (int) $row->id;
                break;
            }
        }
    }

    if (!$main || (int) $main->assigned < 1) {
        return null;
    }

    $fields = [
        'assigned'     => (int) $main->assigned,
        'mode'         => 'duplicate',
        'main_lead_id' => $mainId,
    ];

    // Same status lift as round-robin: leave backlog / empty status for default column.
    $backlogId     = siba_leads_ensure_backlog_status();
    $currentStatus = isset($current['status']) ? (int) $current['status'] : 0;
    if ($currentStatus === $backlogId || $currentStatus === 0) {
        $default = (int) get_option('leads_default_status');
        if ($default > 0 && $default !== $backlogId) {
            $fields['status'] = $default;
        }
    }

    return $fields;
}

/**
 * Fields to merge into lead insert/update payload.
 * Duplicate phones inherit the main (oldest) lead's assignee before round-robin.
 *
 * @return array{assigned:int,status?:int,mode?:string,main_lead_id?:int}
 */
function siba_leads_assignment_fields(array $current = []): array
{
    $dup = siba_leads_assignment_from_duplicate_phone($current);
    if ($dup !== null) {
        $fields = ['assigned' => (int) $dup['assigned']];
        if (isset($dup['status'])) {
            $fields['status'] = (int) $dup['status'];
        }
        $fields['mode']         = 'duplicate';
        $fields['main_lead_id'] = (int) ($dup['main_lead_id'] ?? 0);
        $GLOBALS['siba_leads_assignment_from_duplicate'] = $fields['main_lead_id'];

        return $fields;
    }

    $next   = siba_leads_next_assignment($current);
    $fields = ['assigned' => (int) $next['assigned']];

    if (isset($next['status'])) {
        $fields['status'] = (int) $next['status'];
    }

    unset($GLOBALS['siba_leads_assignment_from_duplicate']);

    return $fields;
}

/**
 * Apply assignment to an existing lead row.
 */
function siba_leads_apply_assignment_to_lead(int $leadId, bool $notify = true): bool
{
    if ($leadId <= 0 || !siba_leads_auto_assign_enabled()) {
        return false;
    }

    $CI = &get_instance();
    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    $CI->db->where(db_prefix() . 'leads.id', $leadId);
    $lead = $CI->db->get(db_prefix() . 'leads')->row();
    if (!$lead) {
        return false;
    }

    $fields = siba_leads_assignment_fields([
        'id'          => $leadId,
        'status'      => $lead->status,
        'assigned'    => $lead->assigned,
        'phonenumber' => $lead->phonenumber ?? '',
    ]);

    // Only persist DB columns — strip helper metadata.
    $update = [
        'assigned' => (int) ($fields['assigned'] ?? 0),
    ];
    if (isset($fields['status'])) {
        $update['status'] = (int) $fields['status'];
    }
    if (!empty($update['assigned'])) {
        $update['dateassigned'] = date('Y-m-d');
    }

    $CI->db->where(db_prefix() . 'leads.id', $leadId);
    $CI->db->update(db_prefix() . 'leads', $update);

    $mainLeadId = (int) ($fields['main_lead_id'] ?? ($GLOBALS['siba_leads_assignment_from_duplicate'] ?? 0));
    unset($GLOBALS['siba_leads_assignment_from_duplicate']);

    if ($notify && !empty($update['assigned'])) {
        $CI->leads_model->lead_assigned_member_notification($leadId, $update['assigned'], true);
        if ($mainLeadId > 0) {
            $CI->leads_model->log_lead_activity(
                $leadId,
                'siba_leads_activity_assigned_from_duplicate',
                true,
                serialize([get_staff_full_name($update['assigned']), $mainLeadId])
            );
        } else {
            $CI->leads_model->log_lead_activity(
                $leadId,
                'siba_leads_activity_auto_assigned',
                true,
                serialize([get_staff_full_name($update['assigned'])])
            );
        }
    } else {
        $CI->leads_model->log_lead_activity($leadId, 'siba_leads_activity_sent_to_backlog', true);
    }

    return true;
}

/**
 * Move existing unassigned leads into the Backlog column (one-time / safe sync).
 */
function siba_leads_sync_unassigned_into_backlog(): void
{
    $backlogId = siba_leads_ensure_backlog_status();
    if ($backlogId <= 0) {
        return;
    }

    $CI = &get_instance();
    $CI->db->where('assigned', 0);
    $CI->db->where('status !=', $backlogId);
    $CI->db->where('lost', 0);
    $CI->db->where('junk', 0);
    $CI->db->group_start();
    $CI->db->where('date_converted IS NULL', null, false);
    $CI->db->or_where('date_converted', '0000-00-00 00:00:00');
    $CI->db->group_end();
    $CI->db->update(db_prefix() . 'leads', ['status' => $backlogId]);
}

/**
 * before_lead_added filter — used by leads_model->add().
 */
function siba_leads_filter_before_lead_added($data)
{
    if (!empty($GLOBALS['siba_leads_didar_import'])) {
        $GLOBALS['siba_leads_assignment_applied'] = true;

        return is_array($data) ? $data : [];
    }

    if (!is_array($data) || !siba_leads_auto_assign_enabled()) {
        return $data;
    }

    // Duplicate phone → always inherit main lead's assignee (even if form set another).
    $dup = siba_leads_assignment_from_duplicate_phone($data);
    if ($dup !== null) {
        $data['assigned'] = (int) $dup['assigned'];
        if (isset($dup['status'])) {
            $data['status'] = (int) $dup['status'];
        }
        $GLOBALS['siba_leads_assignment_applied']        = true;
        $GLOBALS['siba_leads_assignment_from_duplicate'] = (int) ($dup['main_lead_id'] ?? 0);

        return $data;
    }

    if ((int) ($data['assigned'] ?? 0) > 0) {
        $GLOBALS['siba_leads_assignment_applied'] = true;

        return $data;
    }

    $fields = siba_leads_assignment_fields($data);
    unset($fields['mode'], $fields['main_lead_id']);
    $data = array_merge($data, $fields);

    $GLOBALS['siba_leads_assignment_applied'] = true;

    return $data;
}

/**
 * before_insert_lead_from_email_integration filter.
 */
function siba_leads_filter_before_email_lead($data)
{
    if (!is_array($data) || !siba_leads_auto_assign_enabled()) {
        return $data;
    }

    $fields = siba_leads_assignment_fields($data);
    unset($fields['mode'], $fields['main_lead_id']);
    $data = array_merge($data, $fields);
    $GLOBALS['siba_leads_assignment_applied'] = true;
    $GLOBALS['siba_leads_from_email_filter']  = true;

    return $data;
}

/**
 * lead_created action — covers paths that skipped before_lead_added.
 * Web-to-lead is deferred to web_to_lead_form_submitted (runs after core notify).
 *
 * @param int|array $payload
 */
function siba_leads_action_lead_created($payload)
{
    if (!empty($GLOBALS['siba_leads_didar_import'])) {
        return;
    }

    $leadId = is_array($payload) ? (int) ($payload['lead_id'] ?? 0) : (int) $payload;
    if ($leadId > 0 && function_exists('siba_leads_remember_created_phone')) {
        $CI = &get_instance();
        $row = $CI->db->select('phonenumber')->where('id', $leadId)->get(db_prefix() . 'leads')->row();
        if ($row) {
            siba_leads_remember_created_phone($row->phonenumber ?? '', $leadId);
        }
    }

    if (!siba_leads_auto_assign_enabled()) {
        return;
    }

    if (is_array($payload) && !empty($payload['web_to_lead_form'])) {
        return;
    }

    if (!empty($GLOBALS['siba_leads_assignment_applied'])) {
        // Email integration fires lead_created then lead_created_from_email_integration.
        if (!empty($GLOBALS['siba_leads_from_email_filter'])) {
            return;
        }

        $leadId     = is_array($payload) ? (int) ($payload['lead_id'] ?? 0) : (int) $payload;
        $mainLeadId = (int) ($GLOBALS['siba_leads_assignment_from_duplicate'] ?? 0);
        unset($GLOBALS['siba_leads_assignment_applied'], $GLOBALS['siba_leads_assignment_from_duplicate']);

        // before_lead_added already set assignee; log when inherited from a duplicate.
        if ($leadId > 0 && $mainLeadId > 0) {
            siba_leads_log_duplicate_assignment($leadId, $mainLeadId);
        }

        return;
    }

    $leadId = is_array($payload) ? (int) ($payload['lead_id'] ?? 0) : (int) $payload;

    if ($leadId > 0) {
        siba_leads_apply_assignment_to_lead($leadId, true);
    }
}

/**
 * Activity + notification when a new lead inherits the main duplicate's assignee.
 */
function siba_leads_log_duplicate_assignment(int $leadId, int $mainLeadId): void
{
    if ($leadId < 1 || $mainLeadId < 1) {
        return;
    }

    $CI = &get_instance();
    $CI->db->select('assigned');
    $CI->db->where('id', $leadId);
    $lead = $CI->db->get(db_prefix() . 'leads')->row();
    $assigned = $lead ? (int) $lead->assigned : 0;
    if ($assigned < 1) {
        return;
    }

    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    $CI->leads_model->log_lead_activity(
        $leadId,
        'siba_leads_activity_assigned_from_duplicate',
        true,
        serialize([get_staff_full_name($assigned), $mainLeadId])
    );
}

/**
 * After web-to-lead finishes its own notifications, re-assign via sales RR.
 */
function siba_leads_action_web_to_lead_submitted($data)
{
    if (!siba_leads_auto_assign_enabled() || !is_array($data)) {
        return;
    }

    $leadId = (int) ($data['lead_id'] ?? 0);
    if ($leadId > 0) {
        siba_leads_apply_assignment_to_lead($leadId, true);
    }
}

/**
 * Email integration follow-up after core notifies mailbox responsible.
 */
function siba_leads_action_email_lead_created($leadId)
{
    if (!siba_leads_auto_assign_enabled()) {
        return;
    }

    $leadId = (int) $leadId;
    unset($GLOBALS['siba_leads_from_email_filter']);

    if ($leadId <= 0) {
        return;
    }

    if (!empty($GLOBALS['siba_leads_assignment_applied'])) {
        $mainLeadId = (int) ($GLOBALS['siba_leads_assignment_from_duplicate'] ?? 0);
        $GLOBALS['siba_leads_assignment_applied'] = false;
        unset($GLOBALS['siba_leads_assignment_from_duplicate']);

        $CI = &get_instance();
        $CI->db->select('assigned');
        $CI->db->where(db_prefix() . 'leads.id', $leadId);
        $lead = $CI->db->get(db_prefix() . 'leads')->row();
        if ($lead && (int) $lead->assigned > 0) {
            if (!class_exists('leads_model', false)) {
                $CI->load->model('leads_model');
            }
            $CI->leads_model->lead_assigned_member_notification($leadId, (int) $lead->assigned, true);
            if ($mainLeadId > 0) {
                siba_leads_log_duplicate_assignment($leadId, $mainLeadId);
            }
        }

        return;
    }

    siba_leads_apply_assignment_to_lead($leadId, true);
}

/**
 * Mark CSV import window (admin_init runs before controller import).
 */
function siba_leads_mark_csv_import_window()
{
    $CI = &get_instance();

    if (!isset($CI->router)) {
        return;
    }

    $class  = strtolower($CI->router->fetch_class());
    $method = strtolower($CI->router->fetch_method());

    // Support both core leads/import and siba_leads/import.
    if ($method !== 'import' || !in_array($class, ['leads', 'siba_leads'], true)) {
        return;
    }

    if (!$CI->input->post()
        || $CI->input->post('simulate')
        || $CI->input->post('download_sample') === 'true'
        || empty($_FILES['file_csv']['name'])) {
        return;
    }

    $GLOBALS['siba_leads_import_started_at'] = date('Y-m-d H:i:s');
    $GLOBALS['siba_leads_import_staff']      = (int) get_staff_user_id();
}

/**
 * After CSV import view renders, assign any leads inserted in this request.
 */
function siba_leads_process_csv_import_assignments()
{
    if (empty($GLOBALS['siba_leads_import_started_at']) || !siba_leads_auto_assign_enabled()) {
        return;
    }

    $CI        = &get_instance();
    $startedAt = $GLOBALS['siba_leads_import_started_at'];
    $staffId   = (int) ($GLOBALS['siba_leads_import_staff'] ?? 0);

    $CI->db->select('id');
    $CI->db->where('dateadded >=', $startedAt);
    if ($staffId > 0) {
        $CI->db->where('addedfrom', $staffId);
    }
    $CI->db->order_by('id', 'ASC');
    $rows = $CI->db->get(db_prefix() . 'leads')->result_array();

    foreach ($rows as $row) {
        siba_leads_apply_assignment_to_lead((int) $row['id'], true);
    }

    unset($GLOBALS['siba_leads_import_started_at'], $GLOBALS['siba_leads_import_staff']);
}

/**
 * Is this status the backlog column?
 */
function siba_leads_is_backlog_status($statusId): bool
{
    // IMPORTANT: keep this query-free because it can be called while another
    // Query Builder statement is being composed (e.g. kanban initiateQuery).
    $backlogId = (int) get_option(SIBA_LEADS_BACKLOG_OPTION);

    return $backlogId > 0 && (int) $statusId === $backlogId;
}
