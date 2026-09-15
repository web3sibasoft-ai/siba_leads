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
 *   title:string,
 *   birth_date:string
 * }
 */
function siba_leads_resolve_lead_profile($lead): array
{
    $national_code = '';
    $job_group_id  = 0;
    $position_id   = 0;
    $title         = '';
    $birth_date    = '';

    if (is_object($lead)) {
        $national_code = trim((string) ($lead->national_code ?? ''));
        $job_group_id  = (int) ($lead->job_group_id ?? 0);
        $position_id   = (int) ($lead->position_id ?? 0);
        $title         = trim((string) ($lead->title ?? ''));
        $birth_date    = trim((string) ($lead->birth_date ?? ''));
    } elseif (is_array($lead)) {
        $national_code = trim((string) ($lead['national_code'] ?? ''));
        $job_group_id  = (int) ($lead['job_group_id'] ?? 0);
        $position_id   = (int) ($lead['position_id'] ?? 0);
        $title         = trim((string) ($lead['title'] ?? ''));
        $birth_date    = trim((string) ($lead['birth_date'] ?? ''));
    }

    if ($birth_date === '0000-00-00' || $birth_date === '0000-00-00 00:00:00') {
        $birth_date = '';
    }

    if ($birth_date !== '' && function_exists('to_view_date_custom')) {
        $viewDate = to_view_date_custom($birth_date);
        if (!empty($viewDate)) {
            $birth_date = $viewDate;
        }
    } elseif ($birth_date !== '' && function_exists('to_view_date')) {
        $viewDate = to_view_date($birth_date);
        if (!empty($viewDate)) {
            $birth_date = $viewDate;
        }
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
        'birth_date'     => $birth_date,
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
    $data['birth_date']    = trim((string) ($data['birth_date'] ?? ''));

    if ($data['birth_date'] === '0000-00-00' || $data['birth_date'] === '0000-00-00 00:00:00') {
        $data['birth_date'] = '';
    }

    if ($data['birth_date'] !== '') {
        if (function_exists('to_sql_date_custom')) {
            $sqlDate = to_sql_date_custom($data['birth_date']);
            if (!empty($sqlDate)) {
                $data['birth_date'] = $sqlDate;
            }
        } elseif (function_exists('to_sql_date')) {
            $sqlDate = to_sql_date($data['birth_date']);
            if (!empty($sqlDate)) {
                $data['birth_date'] = $sqlDate;
            }
        }
    }

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
        'birth_date'    => (string) ($data['birth_date'] ?? ''),
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

/**
 * Customer-profile fields a lead must have before an order can be placed.
 * Mirrors the new-customer form (Perfex + Siba extras) that map onto leads.
 *
 * @return array<string, array{label:string,type:string}>
 */
function siba_leads_order_required_field_defs(): array
{
    $fields = [
        'name' => [
            'label' => _l('lead_add_edit_name'),
            'type'  => 'text',
        ],
        'phonenumber' => [
            'label' => _l('lead_add_edit_phonenumber'),
            'type'  => 'text',
        ],
        'address' => [
            'label' => _l('lead_address'),
            'type'  => 'text',
        ],
        'province_id' => [
            'label' => _l('siba_leads_province'),
            'type'  => 'id',
        ],
        'city_id' => [
            'label' => _l('siba_leads_city'),
            'type'  => 'id',
        ],
        'job_group_id' => [
            'label' => _l('siba_leads_job_group'),
            'type'  => 'id',
        ],
        'position_id' => [
            'label' => _l('siba_leads_position'),
            'type'  => 'position',
        ],
    ];

    if (get_option('company_is_required') == 1) {
        $fields = array_merge([
            'company' => [
                'label' => _l('lead_company'),
                'type'  => 'text',
            ],
        ], $fields);
    }

    return $fields;
}

/**
 * @param object|array|null $lead
 * @return array{ok:bool,missing:array<int,array{key:string,label:string}>,missing_labels:array<int,string>}
 */
function siba_leads_lead_order_readiness($lead): array
{
    $empty = [
        'ok'             => false,
        'missing'        => [],
        'missing_labels' => [],
    ];

    if ($lead === null) {
        return $empty;
    }

    $get = static function ($key) use ($lead) {
        if (is_array($lead)) {
            return $lead[$key] ?? null;
        }

        return $lead->{$key} ?? null;
    };

    $location = function_exists('siba_leads_resolve_lead_location')
        ? siba_leads_resolve_lead_location($lead)
        : [
            'province_id' => (int) $get('province_id'),
            'city_id'     => (int) $get('city_id'),
        ];

    $values = [
        'name'         => trim((string) ($get('name') ?? $get('lead_name') ?? '')),
        'company'      => trim((string) ($get('company') ?? '')),
        'phonenumber'  => trim((string) ($get('phonenumber') ?? '')),
        'address'      => trim((string) ($get('address') ?? '')),
        'province_id'  => (int) ($location['province_id'] ?? 0),
        'city_id'      => (int) ($location['city_id'] ?? 0),
        'job_group_id' => (int) ($get('job_group_id') ?? 0),
        'position_id'  => (int) ($get('position_id') ?? 0),
        'title'        => trim((string) ($get('title') ?? '')),
    ];

    $missing = [];
    foreach (siba_leads_order_required_field_defs() as $key => $def) {
        $type  = $def['type'];
        $label = $def['label'];
        $ok    = true;

        if ($type === 'id') {
            $ok = ((int) ($values[$key] ?? 0)) > 0;
        } elseif ($type === 'position') {
            $ok = ((int) $values['position_id'] > 0) || $values['title'] !== '';
        } else {
            $ok = trim((string) ($values[$key] ?? '')) !== '';
        }

        if (!$ok) {
            $missing[] = [
                'key'   => $key,
                'label' => $label,
            ];
        }
    }

    $labels = array_values(array_map(static function ($row) {
        return $row['label'];
    }, $missing));

    return [
        'ok'             => $missing === [],
        'missing'        => $missing,
        'missing_labels' => $labels,
    ];
}

/**
 * @param int|string $lead_id
 * @return array{ok:bool,missing:array<int,array{key:string,label:string}>,missing_labels:array<int,string>}
 */
function siba_leads_lead_order_readiness_by_id($lead_id): array
{
    $lead_id = (int) $lead_id;
    if ($lead_id < 1) {
        return [
            'ok'             => false,
            'missing'        => [],
            'missing_labels' => [],
        ];
    }

    $CI = &get_instance();
    if (!class_exists('leads_model', false)) {
        $CI->load->model('leads_model');
    }

    return siba_leads_lead_order_readiness($CI->leads_model->get($lead_id));
}
