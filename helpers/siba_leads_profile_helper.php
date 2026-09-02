<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Extra lead columns for national code, position, and job group.
 *
 * @return array<string, string>
 */
function siba_leads_profile_columns(): array
{
    return [
        'national_code' => "varchar(20) NULL DEFAULT ''",
        'job_group_id'  => 'INT(11) NOT NULL DEFAULT 0',
        'position_id'   => 'INT(11) NOT NULL DEFAULT 0',
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function siba_leads_get_active_job_groups(): array
{
    $CI    = &get_instance();
    $table = db_prefix() . 'siba_job_groups';
    if (!$CI->db->table_exists($table)) {
        return [];
    }

    return $CI->db->where('siba_job_group_status', 1)
        ->order_by('siba_job_group_name', 'ASC')
        ->get($table)
        ->result_array();
}

/**
 * @return array<int, array<string, mixed>>
 */
function siba_leads_get_active_positions(): array
{
    $CI    = &get_instance();
    $table = db_prefix() . 'siba_positions';
    if (!$CI->db->table_exists($table)) {
        return [];
    }

    return $CI->db->where('siba_position_status', 1)
        ->order_by('siba_position_name', 'ASC')
        ->get($table)
        ->result_array();
}

function siba_leads_get_job_group_name($job_group_id): string
{
    $job_group_id = (int) $job_group_id;
    if ($job_group_id < 1) {
        return '';
    }

    $CI = &get_instance();
    $row = $CI->db->select('siba_job_group_name')
        ->where(['siba_job_group_id' => $job_group_id, 'siba_job_group_status' => 1])
        ->get(db_prefix() . 'siba_job_groups')
        ->row();

    return $row ? (string) $row->siba_job_group_name : '';
}

function siba_leads_get_position_name($position_id): string
{
    $position_id = (int) $position_id;
    if ($position_id < 1) {
        return '';
    }

    $CI = &get_instance();
    $row = $CI->db->select('siba_position_name')
        ->where(['siba_position_id' => $position_id, 'siba_position_status' => 1])
        ->get(db_prefix() . 'siba_positions')
        ->row();

    return $row ? (string) $row->siba_position_name : '';
}

/**
 * @param object|array|null $lead
 * @return array{
 *   national_code:string,
 *   job_group_id:int,
 *   job_group_name:string,
 *   position_id:int,
 *   position_name:string,
 *   title:string
 * }
 */
function siba_leads_resolve_lead_profile($lead): array
{
    $national_code = '';
    $job_group_id  = 0;
    $position_id   = 0;
    $title         = '';

    if (is_object($lead)) {
        $national_code = trim((string) ($lead->national_code ?? ''));
        $job_group_id  = (int) ($lead->job_group_id ?? 0);
        $position_id   = (int) ($lead->position_id ?? 0);
        $title         = trim((string) ($lead->title ?? ''));
    } elseif (is_array($lead)) {
        $national_code = trim((string) ($lead['national_code'] ?? ''));
        $job_group_id  = (int) ($lead['job_group_id'] ?? 0);
        $position_id   = (int) ($lead['position_id'] ?? 0);
        $title         = trim((string) ($lead['title'] ?? ''));
    }

    $position_name = $position_id > 0 ? siba_leads_get_position_name($position_id) : $title;
    if ($position_name === '' && $title !== '') {
        $position_name = $title;
    }

    return [
        'national_code'  => $national_code,
        'job_group_id'   => $job_group_id,
        'job_group_name' => $job_group_id > 0 ? siba_leads_get_job_group_name($job_group_id) : '',
        'position_id'    => $position_id,
        'position_name'  => $position_name,
        'title'          => $title,
    ];
}

/**
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function siba_leads_sync_profile_fields(array $data): array
{
    $data['national_code'] = trim((string) ($data['national_code'] ?? ''));
    $data['job_group_id']  = (int) ($data['job_group_id'] ?? 0);
    $data['position_id']   = (int) ($data['position_id'] ?? 0);

    if ($data['position_id'] > 0) {
        $position_name = siba_leads_get_position_name($data['position_id']);
        if ($position_name !== '') {
            $data['title'] = $position_name;
        }
    }

    return $data;
}

/**
 * @param int|string $lead_id
 */
function siba_leads_lead_modal_profile_seed($lead_id): void
{
    $lead_id = (int) $lead_id;
    if ($lead_id < 1) {
        echo '<script>window.sibaLeadProfileSeed = null;</script>';

        return;
    }

    $CI = &get_instance();
    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    $lead = $CI->leads_model->get($lead_id);
    if (!$lead) {
        echo '<script>window.sibaLeadProfileSeed = null;</script>';

        return;
    }

    $profile = siba_leads_resolve_lead_profile($lead);
    echo '<script>window.sibaLeadProfileSeed = ' . json_encode($profile, JSON_UNESCAPED_UNICODE) . ';</script>';
}

/**
 * @param int $lead_id
 */
function siba_leads_persist_profile_on_save($lead_id): void
{
    $lead_id = (int) $lead_id;
    if ($lead_id < 1) {
        return;
    }

    $CI = &get_instance();
    if (!$CI->input->post()) {
        return;
    }

    if (!$CI->db->field_exists('national_code', db_prefix() . 'leads')) {
        return;
    }

    $data = siba_leads_sync_profile_fields($CI->input->post(null, true));

    $CI->db->where('id', $lead_id)->update(db_prefix() . 'leads', [
        'national_code' => (string) ($data['national_code'] ?? ''),
        'job_group_id'  => (int) ($data['job_group_id'] ?? 0),
        'position_id'   => (int) ($data['position_id'] ?? 0),
        'title'         => (string) ($data['title'] ?? ''),
    ]);
}

/**
 * before_lead_added filter callback.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function siba_leads_filter_profile_fields($data)
{
    if (!is_array($data)) {
        return $data;
    }

    return siba_leads_sync_profile_fields($data);
}
