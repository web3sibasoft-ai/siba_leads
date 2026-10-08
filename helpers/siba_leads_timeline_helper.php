<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Build a unified chronological timeline for a lead:
 * notes, lead activity, related tasks, and task comments/reports.
 *
 * @param int $leadId
 * @return array<int, array<string, mixed>>
 */
function siba_leads_build_lead_timeline($leadId): array
{
    $leadId = (int) $leadId;
    if ($leadId < 1) {
        return [];
    }

    $CI = &get_instance();
    $items = [];

    $items = array_merge($items, siba_leads_timeline_notes($leadId));
    $items = array_merge($items, siba_leads_timeline_activities($leadId));
    $items = array_merge($items, siba_leads_timeline_tasks_and_comments($leadId));

    usort($items, static function ($a, $b) {
        $ta = strtotime((string) ($a['date'] ?? '')) ?: 0;
        $tb = strtotime((string) ($b['date'] ?? '')) ?: 0;
        if ($ta === $tb) {
            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        }

        return $tb <=> $ta;
    });

    return $items;
}

/**
 * @param int $leadId
 * @return array<int, array<string, mixed>>
 */
function siba_leads_timeline_notes($leadId): array
{
    $CI = &get_instance();
    if (!class_exists('misc_model', false)) {
        $CI->load->model('misc_model');
    }

    $notes = $CI->misc_model->get_notes($leadId, 'lead');
    $out = [];

    foreach ($notes as $note) {
        $staffId = (int) ($note['addedfrom'] ?? 0);
        $out[] = [
            'id'         => (int) ($note['id'] ?? 0),
            'type'       => 'note',
            'date'       => (string) ($note['dateadded'] ?? ''),
            'staff_id'   => $staffId,
            'staff_name' => $staffId > 0 ? get_staff_full_name($staffId) : '',
            'title'      => _l('siba_leads_timeline_note'),
            'body'       => process_text_content_for_display($note['description'] ?? ''),
            'meta'       => [
                'date_contacted' => !empty($note['date_contacted']) ? (string) $note['date_contacted'] : '',
            ],
            'url'        => '',
            'icon'       => 'fa-regular fa-note-sticky',
            'badge'      => '',
            'badge_color'=> '',
        ];
    }

    return $out;
}

/**
 * @param int $leadId
 * @return array<int, array<string, mixed>>
 */
function siba_leads_timeline_activities($leadId): array
{
    $CI = &get_instance();
    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    $logs = $CI->leads_model->get_lead_activity_log($leadId);
    $out = [];

    foreach ($logs as $log) {
        $staffId = (int) ($log['staffid'] ?? 0);
        $body = siba_leads_timeline_format_activity_body($log);

        $out[] = [
            'id'         => (int) ($log['id'] ?? 0),
            'type'       => 'activity',
            'date'       => (string) ($log['date'] ?? ''),
            'staff_id'   => $staffId,
            'staff_name' => (string) ($log['full_name'] ?? ($staffId > 0 ? get_staff_full_name($staffId) : '')),
            'title'      => _l('siba_leads_timeline_activity'),
            'body'       => $body,
            'meta'       => [
                'custom_activity' => (int) ($log['custom_activity'] ?? 0),
            ],
            'url'        => '',
            'icon'       => 'fa-solid fa-bolt',
            'badge'      => '',
            'badge_color'=> '',
        ];
    }

    return $out;
}

/**
 * @param array $log
 * @return string
 */
function siba_leads_timeline_format_activity_body(array $log): string
{
    $description = (string) ($log['description'] ?? '');
    $staffName = (string) ($log['full_name'] ?? '');
    $staffId = (int) ($log['staffid'] ?? 0);
    $custom = (int) ($log['custom_activity'] ?? 0);

    $additional = '';
    if (!empty($log['additional_data'])) {
        $unserialized = @unserialize($log['additional_data']);
        if ($unserialized !== false) {
            $additional = $unserialized;
        }
    }

    if ($additional !== '' && $additional !== null) {
        $translated = _l($description, $additional);
        if ($staffId === 0) {
            return process_text_content_for_display($translated);
        }

        return e($staffName) . ' - ' . process_text_content_for_display($translated);
    }

    $prefix = $staffName !== '' ? (e($staffName) . ' - ') : '';
    if ($custom === 0) {
        return $prefix . e(_l($description));
    }

    return $prefix . process_text_content_for_display(_l($description, '', false));
}

/**
 * Related lead tasks + their comments/reports.
 *
 * @param int $leadId
 * @return array<int, array<string, mixed>>
 */
function siba_leads_timeline_tasks_and_comments($leadId): array
{
    $CI = &get_instance();
    $tasksTable = db_prefix() . 'tasks';
    $commentsTable = db_prefix() . 'task_comments';
    $typesTable = db_prefix() . 'task_comment_types';
    $staffTable = db_prefix() . 'staff';

    $CI->db->select($tasksTable . '.id, ' . $tasksTable . '.name, ' . $tasksTable . '.dateadded, ' . $tasksTable . '.addedfrom, ' . $tasksTable . '.status');
    $CI->db->from($tasksTable);
    $CI->db->where('rel_type', 'lead');
    $CI->db->where('rel_id', $leadId);
    $CI->db->order_by($tasksTable . '.dateadded', 'DESC');
    $tasks = $CI->db->get()->result_array();

    if (empty($tasks)) {
        return [];
    }

    $out = [];
    $taskIds = [];
    $taskMap = [];

    foreach ($tasks as $task) {
        $taskId = (int) $task['id'];
        $taskIds[] = $taskId;
        $taskMap[$taskId] = $task;
        $staffId = (int) ($task['addedfrom'] ?? 0);

        $out[] = [
            'id'         => $taskId,
            'type'       => 'task',
            'date'       => (string) ($task['dateadded'] ?? ''),
            'staff_id'   => $staffId,
            'staff_name' => $staffId > 0 ? get_staff_full_name($staffId) : '',
            'title'      => _l('siba_leads_timeline_task'),
            'body'       => e((string) ($task['name'] ?? '')),
            'meta'       => [
                'task_id'     => $taskId,
                'task_name'   => (string) ($task['name'] ?? ''),
                'task_status' => (int) ($task['status'] ?? 0),
            ],
            'url'        => admin_url('tasks/view/' . $taskId),
            'icon'       => 'fa-regular fa-circle-check',
            'badge'      => '',
            'badge_color'=> '',
        ];
    }

    $hasTypeCol = $CI->db->field_exists('task_comment_type_id', $commentsTable);
    $select = $commentsTable . '.id,'
        . $commentsTable . '.taskid,'
        . $commentsTable . '.content,'
        . $commentsTable . '.dateadded,'
        . $commentsTable . '.staffid,'
        . 'CONCAT(' . $staffTable . '.firstname, " ", ' . $staffTable . '.lastname) as staff_full_name';

    if ($hasTypeCol) {
        $select .= ',' . $commentsTable . '.task_comment_type_id,'
            . $typesTable . '.name as type_name,'
            . $typesTable . '.color as type_color';
    }

    $CI->db->select($select);
    $CI->db->from($commentsTable);
    $CI->db->join($staffTable, $staffTable . '.staffid = ' . $commentsTable . '.staffid', 'left');
    if ($hasTypeCol && $CI->db->table_exists($typesTable)) {
        $CI->db->join($typesTable, $typesTable . '.id = ' . $commentsTable . '.task_comment_type_id', 'left');
    }
    $CI->db->where_in($commentsTable . '.taskid', $taskIds);
    $CI->db->order_by($commentsTable . '.dateadded', 'DESC');
    $comments = $CI->db->get()->result_array();

    foreach ($comments as $comment) {
        $taskId = (int) ($comment['taskid'] ?? 0);
        $taskName = (string) ($taskMap[$taskId]['name'] ?? '');
        $staffId = (int) ($comment['staffid'] ?? 0);
        $typeName = (string) ($comment['type_name'] ?? '');
        $typeColor = (string) ($comment['type_color'] ?? '');
        $isReport = $typeName !== '';

        $title = $isReport
            ? _l('siba_leads_timeline_task_report')
            : _l('siba_leads_timeline_task_comment');

        $body = process_text_content_for_display($comment['content'] ?? '');
        if ($taskName !== '') {
            $body = '<div class="siba-lead-timeline__task-ref">'
                . e(_l('task')) . ': <strong>' . e($taskName) . '</strong>'
                . '</div>' . $body;
        }

        $out[] = [
            'id'         => (int) ($comment['id'] ?? 0),
            'type'       => $isReport ? 'task_report' : 'task_comment',
            'date'       => (string) ($comment['dateadded'] ?? ''),
            'staff_id'   => $staffId,
            'staff_name' => (string) ($comment['staff_full_name'] ?? ($staffId > 0 ? get_staff_full_name($staffId) : '')),
            'title'      => $title,
            'body'       => $body,
            'meta'       => [
                'task_id'   => $taskId,
                'task_name' => $taskName,
                'type_id'   => (int) ($comment['task_comment_type_id'] ?? 0),
            ],
            'url'        => admin_url('tasks/view/' . $taskId),
            'icon'       => $isReport ? 'fa-solid fa-clipboard-list' : 'fa-regular fa-comment',
            'badge'      => $typeName,
            'badge_color'=> $typeColor !== '' ? $typeColor : '#64748b',
        ];
    }

    return $out;
}

/**
 * Lead modal timeline tab link.
 *
 * @param object|array|int|null $lead
 */
function siba_leads_lead_modal_timeline_tab($lead = null): void
{
    if ($lead === null || $lead === '' || $lead === 0) {
        return;
    }

    $leadId = is_numeric($lead) ? (int) $lead : (int) (is_array($lead) ? ($lead['id'] ?? 0) : ($lead->id ?? 0));
    if ($leadId < 1) {
        return;
    }

    echo '<li role="presentation" class="siba-lead-modal-timeline-tab">';
    echo '<a href="#siba_lead_timeline" aria-controls="siba_lead_timeline" role="tab" data-toggle="tab"';
    echo ' data-lead-id="' . $leadId . '"';
    echo ' onclick="if (typeof siba_leads_load_lead_timeline === \'function\') { siba_leads_load_lead_timeline(' . $leadId . '); }">';
    echo '<i class="fa-solid fa-timeline menu-icon"></i>';
    echo e(_l('siba_leads_timeline_tab'));
    echo '</a>';
    echo '</li>';
}

/**
 * Lead modal timeline tab pane shell (content loaded via AJAX).
 *
 * @param object|array|int|null $lead
 */
function siba_leads_lead_modal_timeline_pane($lead = null): void
{
    if ($lead === null || $lead === '' || $lead === 0) {
        return;
    }

    $leadId = is_numeric($lead) ? (int) $lead : (int) (is_array($lead) ? ($lead['id'] ?? 0) : ($lead->id ?? 0));
    if ($leadId < 1) {
        return;
    }

    echo '<div role="tabpanel" class="tab-pane" id="siba_lead_timeline">';
    echo '<div class="siba-lead-timeline" data-lead-id="' . $leadId . '">';
    echo '<div class="siba-lead-timeline__loading tw-text-center tw-py-8 tw-text-neutral-500">';
    echo '<i class="fa fa-spinner fa-spin fa-2x"></i>';
    echo '<div class="tw-mt-2">' . e(_l('siba_leads_timeline_loading')) . '</div>';
    echo '</div>';
    echo '<div class="siba-lead-timeline__body hide"></div>';
    echo '</div>';
    echo '</div>';
}
