<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Allowed report tabs (sidebar order).
 *
 * @return string[]
 */
function siba_leads_reports_tabs(): array
{
    return ['incoming', 'sources', 'failures', 'conversion'];
}

/**
 * Normalize group-by dimensions from request.
 *
 * @param mixed $raw
 * @return string[] subset of date|assigned|source|status|location|product
 */
function siba_leads_reports_normalize_group_by($raw): array
{
    if (!is_array($raw)) {
        $raw = $raw !== null && $raw !== '' ? [(string) $raw] : [];
    }

    $allowed = ['date', 'assigned', 'source', 'status', 'location', 'product'];
    $out     = [];
    foreach ($raw as $item) {
        $key = strtolower(trim((string) $item));
        if ($key === 'province') {
            $key = 'location';
        }
        if ($key === 'job_group') {
            $key = 'product';
        }
        if (in_array($key, $allowed, true) && !in_array($key, $out, true)) {
            $out[] = $key;
        }
    }

    return $out;
}

/**
 * Normalize a list of positive integer IDs from request (single or array).
 *
 * @param mixed $raw
 * @return int[]
 */
function siba_leads_reports_normalize_id_list($raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }
    if (!is_array($raw)) {
        $raw = [$raw];
    }

    $out = [];
    foreach ($raw as $item) {
        $id = (int) $item;
        if ($id > 0 && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * Parse GET/POST filters for lead reports (Jalali-aware dates).
 *
 * @param array|object|null $input CI input object or assoc array
 * @return array{
 *   report:string,
 *   assigned:int[],
 *   source:int[],
 *   date_from:string,
 *   date_to:string,
 *   date_from_sql:string,
 *   date_to_sql:string,
 *   date_bucket:string,
 *   group_by:string[]
 * }
 */
function siba_leads_reports_parse_filters($input = null): array
{
    $CI = &get_instance();
    if ($input === null) {
        $input = $CI->input;
    }

    $get = static function ($key, $default = '') use ($input) {
        if (is_array($input)) {
            return $input[$key] ?? $default;
        }

        return $input->get($key) ?? $default;
    };

    $report = strtolower(trim((string) $get('report', 'incoming')));
    // Backward-compatible aliases from older tabs.
    if ($report === 'registered' || $report === 'analysis') {
        $report = 'incoming';
    }
    if (!in_array($report, siba_leads_reports_tabs(), true)) {
        $report = 'incoming';
    }

    $dateBucket = strtolower(trim((string) $get('date_bucket', 'day')));
    if (!in_array($dateBucket, ['day', 'week', 'month', 'quarter'], true)) {
        $dateBucket = 'day';
    }

    $dateFromView = trim((string) $get('date_from', ''));
    $dateToView   = trim((string) $get('date_to', ''));
    $dateFromSql  = '';
    $dateToSql    = '';

    if (!function_exists('to_sql_date_custom') || !function_exists('to_view_date_custom')) {
        if (function_exists('siba_leads_ensure_jalali_helpers')) {
            siba_leads_ensure_jalali_helpers();
        } else {
            $CI->load->helper('siba_license/siba_license');
        }
    }

    // Always Latinize view values first (query string may contain ۱۴۰۵/…).
    if (function_exists('siba_license_to_latin_digits')) {
        $dateFromView = siba_license_to_latin_digits($dateFromView);
        $dateToView   = siba_license_to_latin_digits($dateToView);
    }

    if ($dateFromView !== '') {
        $from = function_exists('to_sql_date_custom')
            ? to_sql_date_custom($dateFromView)
            : to_sql_date($dateFromView);
        if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $dateFromSql = $from;
            if (function_exists('to_view_date_custom')) {
                $view = to_view_date_custom($from);
                if ($view) {
                    $dateFromView = $view;
                }
            }
        } else {
            // Unparseable → clear so MySQL never sees Jalali/Persian strings.
            $dateFromView = '';
        }
    }

    if ($dateToView !== '') {
        $to = function_exists('to_sql_date_custom')
            ? to_sql_date_custom($dateToView)
            : to_sql_date($dateToView);
        if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $dateToSql = $to;
            if (function_exists('to_view_date_custom')) {
                $view = to_view_date_custom($to);
                if ($view) {
                    $dateToView = $view;
                }
            }
        } else {
            $dateToView = '';
        }
    }

    $groupByRaw = is_array($input) ? ($input['group_by'] ?? []) : $input->get('group_by');
    $groupBy    = siba_leads_reports_normalize_group_by($groupByRaw);

    $axis = strtolower(trim((string) $get('axis', '')));
    // Counts only — amount / FX metrics removed from reports UI.
    $metric = 'count';

    // Default weekly buckets for sources chart.
    if ($report === 'sources') {
        if ((string) $get('date_bucket', '') === '') {
            $dateBucket = 'week';
        }
    }

    if ($report === 'conversion') {
        if (!in_array($axis, ['time', 'users'], true)) {
            $axis = in_array('assigned', $groupBy, true) && !in_array('date', $groupBy, true)
                ? 'users'
                : 'time';
        }
        $groupBy = ($axis === 'users') ? ['assigned'] : ['date'];
    } elseif ($report === 'sources') {
        if (!in_array($axis, ['time', 'source'], true)) {
            $axis = in_array('source', $groupBy, true) && !in_array('date', $groupBy, true)
                ? 'source'
                : 'time';
        }
        $groupBy = ($axis === 'source') ? ['source'] : ['date', 'source'];
    } elseif ($report === 'failures') {
        // Default weekly buckets for failure reasons chart.
        if ((string) $get('date_bucket', '') === '') {
            $dateBucket = 'week';
        }
        $allowedAxes = ['time', 'reason', 'users', 'status'];
        if (!in_array($axis, $allowedAxes, true)) {
            $axis = 'time';
        }
        if ($axis === 'reason') {
            $groupBy = [];
        } elseif ($axis === 'users') {
            $groupBy = ['assigned'];
        } elseif ($axis === 'status') {
            $groupBy = ['status'];
        } else {
            $groupBy = ['date'];
            $axis    = 'time';
        }
    } else {
        // Incoming stacked chart: time or staff.
        if (!in_array($axis, ['time', 'users'], true)) {
            // Legacy bookmark: location → users.
            if ($axis === 'location' || (in_array('assigned', $groupBy, true) && !in_array('date', $groupBy, true))) {
                $axis = 'users';
            } else {
                $axis = 'time';
            }
        }
        $groupBy = ($axis === 'users') ? ['assigned'] : ['date'];
        if ($axis !== 'users') {
            $axis = 'time';
        }
    }

    $tagNone = false;
    $teams = [];

    return [
        'report'         => $report,
        'assigned'       => siba_leads_reports_normalize_id_list($get('assigned', [])),
        'source'         => siba_leads_reports_normalize_id_list($get('source', [])),
        'tags'           => [],
        'tag_none'       => $tagNone,
        'teams'          => $teams,
        'job_groups'     => siba_leads_reports_normalize_id_list($get('job_groups', [])),
        'org_units'      => siba_leads_reports_normalize_id_list($get('org_units', [])),
        'axis'           => $axis,
        'metric'         => 'count',
        'fx_rate'        => 1.0,
        'date_from'      => $dateFromView,
        'date_to'        => $dateToView,
        'date_from_sql'  => $dateFromSql,
        'date_to_sql'    => $dateToSql,
        'date_bucket'    => $dateBucket,
        'group_by'       => $groupBy,
    ];
}

/**
 * SQL expression for conversion timestamp.
 */
function siba_leads_reports_sale_date_sql(string $leadsAlias = 'l'): string
{
    $a = $leadsAlias;

    return 'CASE'
        . ' WHEN ' . $a . ".siba_outcome = 'success'"
        . ' AND ' . $a . '.siba_outcome_at IS NOT NULL'
        . ' AND ' . $a . ".siba_outcome_at > '1971-01-01 00:00:00'"
        . ' THEN ' . $a . '.siba_outcome_at'
        . ' WHEN ' . $a . '.date_converted IS NOT NULL'
        . ' AND ' . $a . ".date_converted > '1971-01-01 00:00:00'"
        . ' THEN ' . $a . '.date_converted'
        . ' ELSE NULL END';
}

/**
 * WHERE fragment: lead counts as a sale / conversion.
 * NULL-safe: `col = 'x'` is UNKNOWN when col IS NULL, which breaks NOT(...) open math.
 */
function siba_leads_reports_is_sale_sql(string $leadsAlias = 'l'): string
{
    $a = $leadsAlias;

    return '('
        . 'COALESCE(' . $a . ".siba_outcome, '') = 'success'"
        . ' OR ('
        . $a . '.date_converted IS NOT NULL'
        . ' AND ' . $a . ".date_converted > '1971-01-01 00:00:00')"
        . ')';
}

/**
 * WHERE fragment: failed / lost lead.
 * NULL-safe for siba_outcome (see is_sale_sql).
 */
function siba_leads_reports_is_failed_sql(string $leadsAlias = 'l'): string
{
    $a = $leadsAlias;

    return '(' . $a . '.lost = 1 OR COALESCE(' . $a . ".siba_outcome, '') = 'failed')";
}

/**
 * SQL expression for failure event date.
 */
function siba_leads_reports_failure_date_sql(string $leadsAlias = 'l'): string
{
    $a = $leadsAlias;

    return 'CASE'
        . ' WHEN ' . $a . '.siba_outcome_at IS NOT NULL'
        . ' AND ' . $a . ".siba_outcome_at > '1971-01-01 00:00:00'"
        . ' THEN ' . $a . '.siba_outcome_at'
        . ' ELSE ' . $a . '.dateadded END';
}

/**
 * @param string[] $groupBy
 * @param array{status_column?:string} $options
 * @return array{select:string[], group:string[], labels:string[]}
 */
function siba_leads_reports_dimension_sql(
    array $groupBy,
    string $dateExpr,
    string $dateBucket,
    string $leadsAlias = 'l',
    array $options = []
): array
{
    $select = [];
    $group  = [];
    $labels = [];

    if (in_array('date', $groupBy, true)) {
        if ($dateBucket === 'month') {
            $bucket = 'DATE_FORMAT(' . $dateExpr . ", '%Y-%m')";
        } elseif ($dateBucket === 'week') {
            // Monday start of ISO-like week as Y-m-d key.
            $bucket = 'DATE(DATE_SUB(' . $dateExpr . ', INTERVAL WEEKDAY(' . $dateExpr . ') DAY))';
        } elseif ($dateBucket === 'quarter') {
            // 3-month Gregorian quarter: 2026-Q1
            $bucket = 'CONCAT(YEAR(' . $dateExpr . "), '-Q', QUARTER(" . $dateExpr . '))';
        } else {
            $bucket = 'DATE(' . $dateExpr . ')';
        }
        $select[] = $bucket . ' AS dim_date';
        $group[]  = $bucket;
        $labels[] = 'dim_date';
    }

    if (in_array('assigned', $groupBy, true)) {
        $select[] = $leadsAlias . '.assigned AS dim_assigned';
        $group[]  = $leadsAlias . '.assigned';
        $labels[] = 'dim_assigned';
    }

    if (in_array('source', $groupBy, true)) {
        $select[] = $leadsAlias . '.source AS dim_source';
        $group[]  = $leadsAlias . '.source';
        $labels[] = 'dim_source';
    }

    if (in_array('status', $groupBy, true)) {
        // Failures report uses the stage captured at mark-failed time.
        $statusCol = $leadsAlias . '.status';
        if (!empty($options['status_column'])) {
            $statusCol = $leadsAlias . '.' . preg_replace('/[^a-z0-9_]/i', '', (string) $options['status_column']);
        }
        $select[] = $statusCol . ' AS dim_status';
        $group[]  = $statusCol;
        $labels[] = 'dim_status';
    }

    if (in_array('location', $groupBy, true)) {
        $select[] = $leadsAlias . '.province_id AS dim_location';
        $group[]  = $leadsAlias . '.province_id';
        $labels[] = 'dim_location';
    }

    if (in_array('product', $groupBy, true)) {
        $select[] = $leadsAlias . '.job_group_id AS dim_product';
        $group[]  = $leadsAlias . '.job_group_id';
        $labels[] = 'dim_product';
    }

    return [
        'select' => $select,
        'group'  => $group,
        'labels' => $labels,
    ];
}

/**
 * Apply shared filters to CI query builder for leads alias `l`.
 */
function siba_leads_reports_apply_common_filters($CI, array $filters, string $dateExpr): void
{
    if (!siba_leads_can_view_all()) {
        $CI->db->where('l.assigned', (int) get_staff_user_id());
    } else {
        $assignedIds = !empty($filters['assigned'])
            ? siba_leads_reports_normalize_id_list($filters['assigned'])
            : [];

        // Team filter → staff IDs (intersect with explicit assigned filter when both set).
        if (!empty($filters['teams']) && function_exists('siba_leads_get_team_member_ids')) {
            $teamStaff = [];
            foreach ((array) $filters['teams'] as $teamKey) {
                $teamStaff = array_merge($teamStaff, siba_leads_get_team_member_ids((string) $teamKey, true));
            }
            $teamStaff = array_values(array_unique(array_map('intval', $teamStaff)));
            if ($teamStaff === []) {
                $CI->db->where('1 = 0', null, false);
            } elseif ($assignedIds !== []) {
                $assignedIds = array_values(array_intersect($assignedIds, $teamStaff));
                if ($assignedIds === []) {
                    $CI->db->where('1 = 0', null, false);
                }
            } else {
                $assignedIds = $teamStaff;
            }
        }

        // Org-unit filter → staff in those units (incl. descendants); intersect with above.
        if (!empty($filters['org_units']) && function_exists('siba_webkit_org_get_unit_members')) {
            $unitStaff = [];
            foreach (siba_leads_reports_normalize_id_list($filters['org_units']) as $unitId) {
                foreach (siba_webkit_org_get_unit_members((int) $unitId, true, true) as $member) {
                    $sid = (int) ($member['staffid'] ?? $member['staff_id'] ?? 0);
                    if ($sid > 0) {
                        $unitStaff[$sid] = $sid;
                    }
                }
            }
            $unitStaff = array_values($unitStaff);
            if ($unitStaff === []) {
                $CI->db->where('1 = 0', null, false);
            } elseif ($assignedIds !== []) {
                $assignedIds = array_values(array_intersect($assignedIds, $unitStaff));
                if ($assignedIds === []) {
                    $CI->db->where('1 = 0', null, false);
                }
            } else {
                $assignedIds = $unitStaff;
            }
        }

        if (count($assignedIds) === 1) {
            $CI->db->where('l.assigned', $assignedIds[0]);
        } elseif ($assignedIds !== []) {
            $CI->db->where_in('l.assigned', $assignedIds);
        }
    }

    if (!empty($filters['source'])) {
        $sourceIds = siba_leads_reports_normalize_id_list($filters['source']);
        if (count($sourceIds) === 1) {
            $CI->db->where('l.source', $sourceIds[0]);
        } elseif ($sourceIds !== []) {
            $CI->db->where_in('l.source', $sourceIds);
        }
    }

    if (!empty($filters['job_groups'])) {
        $jobGroupIds = siba_leads_reports_normalize_id_list($filters['job_groups']);
        if (count($jobGroupIds) === 1) {
            $CI->db->where('l.job_group_id', $jobGroupIds[0]);
        } elseif ($jobGroupIds !== []) {
            $CI->db->where_in('l.job_group_id', $jobGroupIds);
        }
    }

    if (!empty($filters['date_from_sql'])) {
        $CI->db->where(
            $dateExpr . ' >= ' . $CI->db->escape($filters['date_from_sql'] . ' 00:00:00'),
            null,
            false
        );
    }
    if (!empty($filters['date_to_sql'])) {
        $CI->db->where(
            $dateExpr . ' <= ' . $CI->db->escape($filters['date_to_sql'] . ' 23:59:59'),
            null,
            false
        );
    }

    $taggables = db_prefix() . 'taggables';
    if (!empty($filters['tag_none'])) {
        $CI->db->where(
            'l.id NOT IN (SELECT rel_id FROM ' . $taggables . " WHERE rel_type = 'lead')",
            null,
            false
        );
    } elseif (!empty($filters['tags'])) {
        $tagIds = siba_leads_reports_normalize_id_list($filters['tags']);
        if ($tagIds !== []) {
            $CI->db->where(
                'l.id IN (SELECT rel_id FROM ' . $taggables
                . " WHERE rel_type = 'lead' AND tag_id IN (" . implode(',', array_map('intval', $tagIds)) . '))',
                null,
                false
            );
        }
    }
}

/**
 * Human labels for dimension values on a row.
 *
 * @param string $dateBucket day|week|month|quarter
 */
function siba_leads_reports_enrich_row(array $row, array $groupBy, string $dateBucket = 'day'): array
{
    if (in_array('assigned', $groupBy, true)) {
        $aid = (int) ($row['dim_assigned'] ?? 0);
        $row['dim_assigned_label'] = $aid > 0
            ? get_staff_full_name($aid)
            : _l('siba_leads_unassigned');
    }

    if (in_array('source', $groupBy, true)) {
        $sid = (int) ($row['dim_source'] ?? 0);
        if ($sid > 0) {
            $CI = &get_instance();
            $src = $CI->db->select('name')
                ->where('id', $sid)
                ->get(db_prefix() . 'leads_sources')
                ->row();
            $row['dim_source_label'] = $src->name ?? ('#' . $sid);
        } else {
            $row['dim_source_label'] = _l('siba_leads_reports_source_none');
        }
    }

    if (in_array('status', $groupBy, true)) {
        $stid = (int) ($row['dim_status'] ?? 0);
        $lost = (int) ($row['dim_lost'] ?? ($row['lost'] ?? 0));
        if ($lost === 1 || (!empty($row['is_lost_bucket']))) {
            $row['dim_status_label'] = _l('lost_leads');
            $row['dim_status_color'] = '#fc2d42';
        } elseif ($stid > 0) {
            $CI = &get_instance();
            $st = $CI->db->select('name, color')
                ->where('id', $stid)
                ->get(db_prefix() . 'leads_status')
                ->row();
            $row['dim_status_label'] = $st->name ?? ('#' . $stid);
            $row['dim_status_color'] = $st->color ?? '#6b7280';
        } else {
            $row['dim_status_label'] = _l('siba_leads_reports_status_none');
            $row['dim_status_color'] = '#9ca3af';
        }
    }

    if (in_array('location', $groupBy, true)) {
        $pid = (int) ($row['dim_location'] ?? 0);
        if ($pid > 0 && function_exists('siba_leads_get_province_name')) {
            $name = siba_leads_get_province_name($pid);
            $row['dim_location_label'] = $name !== '' ? $name : ('#' . $pid);
        } else {
            $row['dim_location_label'] = _l('siba_leads_reports_location_none');
        }
    }

    if (in_array('product', $groupBy, true)) {
        $jid = (int) ($row['dim_product'] ?? 0);
        if ($jid > 0 && function_exists('siba_leads_get_job_group_name')) {
            $name = siba_leads_get_job_group_name($jid);
            $row['dim_product_label'] = $name !== '' ? $name : ('#' . $jid);
        } else {
            $row['dim_product_label'] = _l('siba_leads_reports_product_none');
        }
    }

    if (in_array('date', $groupBy, true)) {
        $raw = trim((string) ($row['dim_date'] ?? ''));
        $dateBucket = strtolower(trim($dateBucket));
        if ($raw === '') {
            $row['dim_date_label'] = '—';
        } elseif (preg_match('/^\d{4}-Q[1-4]$/i', $raw) || $dateBucket === 'quarter') {
            $row['dim_date_label'] = siba_leads_reports_quarter_label($raw);
        } elseif ($dateBucket === 'week' && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            $row['dim_date_label'] = siba_leads_reports_week_label(substr($raw, 0, 10));
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            $row['dim_date_label'] = siba_leads_jalali_date(substr($raw, 0, 10)) ?: $raw;
        } elseif (preg_match('/^\d{4}-\d{2}$/', $raw) || $dateBucket === 'month') {
            $row['dim_date_label'] = siba_leads_jalali_month($raw) ?: $raw;
        } else {
            $row['dim_date_label'] = siba_leads_jalali_date($raw) ?: $raw;
        }
    }

    return $row;
}

/**
 * Label for week bucket key (Monday Y-m-d) → «هفته چهارم تیر ۱۴۰۵».
 */
function siba_leads_reports_week_label(string $mondayYmd): string
{
    $mondayYmd = substr(trim($mondayYmd), 0, 10);
    $ts = strtotime($mondayYmd . ' 12:00:00');
    if (!$ts) {
        return $mondayYmd;
    }

    siba_leads_ensure_jalali_helpers();
    if (function_exists('siba_license_ensure_jdf_helper')) {
        siba_license_ensure_jdf_helper();
    }
    if (!function_exists('jdate')) {
        $start = siba_leads_jalali_date($mondayYmd);
        $endTs = strtotime($mondayYmd . ' +6 days');
        $end   = $endTs ? siba_leads_jalali_date(date('Y-m-d', $endTs)) : '';
        if ($start !== '' && $end !== '') {
            return _l('siba_leads_reports_week_range', $start . ' – ' . $end);
        }

        return $start !== '' ? _l('siba_leads_reports_week_of', $start) : $mondayYmd;
    }

    // Mid-week keeps Jalali month stable across Gregorian week boundaries.
    $mid = strtotime($mondayYmd . ' +3 days 12:00:00') ?: $ts;
    $jy  = (string) jdate('Y', $mid, '', 'Asia/Tehran', 'en');
    $jd  = (int) jdate('j', $mid, '', 'Asia/Tehran', 'en');
    $monthName = (string) jdate('F', $mid, '', 'Asia/Tehran', 'fa');
    $weekOfMonth = max(1, (int) ceil($jd / 7));
    $ordinals = [
        1 => _l('siba_leads_reports_week_ord_1'),
        2 => _l('siba_leads_reports_week_ord_2'),
        3 => _l('siba_leads_reports_week_ord_3'),
        4 => _l('siba_leads_reports_week_ord_4'),
        5 => _l('siba_leads_reports_week_ord_5'),
        6 => _l('siba_leads_reports_week_ord_5'),
    ];
    $ord = $ordinals[$weekOfMonth] ?? (string) $weekOfMonth;

    return sprintf(_l('siba_leads_reports_week_jalali'), $ord, $monthName, $jy);
}

/**
 * Label for quarter key YYYY-Qn.
 */
function siba_leads_reports_quarter_label(string $key): string
{
    $key = trim($key);
    if (!preg_match('/^(\d{4})-Q([1-4])$/i', $key, $m)) {
        return $key;
    }
    $year = (int) $m[1];
    $q    = (int) $m[2];
    $startMonth = ($q - 1) * 3 + 1;
    $start = sprintf('%04d-%02d-01', $year, $startMonth);
    $endMonth = $startMonth + 2;
    $endDay = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $endMonth)));
    $end = sprintf('%04d-%02d-%02d', $year, $endMonth, $endDay);
    $jStart = siba_leads_jalali_date($start);
    $jEnd   = siba_leads_jalali_date($end);
    if ($jStart !== '' && $jEnd !== '') {
        return _l('siba_leads_reports_quarter_range', $q . ' (' . $jStart . ' – ' . $jEnd . ')');
    }

    return _l('siba_leads_reports_quarter_n', (string) $q) . ' ' . $year;
}

/**
 * Registered / incoming leads: stacked open / success / failed by dimension.
 *
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_registered(array $filters, array $groupBy): array
{
    return siba_leads_reports_analysis($filters, $groupBy);
}

/**
 * Failure reasons report.
 *
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_failures(array $filters, array $groupBy): array
{
    $CI      = &get_instance();
    $leadsT  = db_prefix() . 'leads';
    $reasonT = db_prefix() . 'siba_leads_failure_reasons';
    // Screenshot axis title: «تاریخ ایجاد معامله» → bucket by lead creation date.
    $dateExpr = 'l.dateadded';
    $dims    = siba_leads_reports_dimension_sql(
        $groupBy,
        $dateExpr,
        $filters['date_bucket'] ?? 'week',
        'l',
        [
            // Current status is cleared to 0 on mark-failed; use captured stage.
            'status_column' => 'siba_failed_from_status',
        ]
    );

    $select = $dims['select'];
    $select[] = 'l.siba_failure_reason_id AS reason_id';
    $select[] = 'MAX(r.title) AS reason_title';
    $select[] = 'MAX(r.color) AS reason_color';
    $select[] = 'COUNT(*) AS fail_count';
    $select[] = 'SUM(COALESCE(l.lead_value, 0)) AS lead_value';

    $CI->db->select(implode(', ', $select), false);
    $CI->db->from($leadsT . ' l');
    $CI->db->join($reasonT . ' r', 'r.id = l.siba_failure_reason_id', 'left');
    $CI->db->group_start();
    $CI->db->where('l.lost', 1);
    $CI->db->or_where('l.siba_outcome', 'failed');
    $CI->db->group_end();
    siba_leads_reports_apply_common_filters($CI, $filters, $dateExpr);

    $groupCols = $dims['group'];
    $groupCols[] = 'l.siba_failure_reason_id';
    $CI->db->group_by($groupCols);

    if (in_array('date', $groupBy, true)) {
        $CI->db->order_by('dim_date', 'ASC');
    } else {
        $CI->db->order_by('fail_count', 'DESC');
    }

    $rows = $CI->db->get()->result_array();
    $total = 0;
    $totalValue = 0.0;
    foreach ($rows as &$row) {
        $row = siba_leads_reports_enrich_row($row, $groupBy, $filters['date_bucket'] ?? 'week');
        if (empty($row['reason_title'])) {
            $row['reason_title'] = _l('siba_leads_reports_reason_unknown');
            $row['reason_color'] = '#9ca3af';
        }
        $row['fail_count'] = (int) ($row['fail_count'] ?? 0);
        $row['lead_value'] = (float) ($row['lead_value'] ?? 0);
        $total += $row['fail_count'];
        $totalValue += $row['lead_value'];
    }
    unset($row);

    return [
        'rows'    => $rows,
        'summary' => [
            'fail_count' => $total,
            'lead_value' => $totalValue,
        ],
    ];
}

/**
 * Shared Highcharts shell (CRM-style full chart pane).
 */
function siba_leads_reports_hc_shell(string $type = 'column', bool $showLegend = true): array
{
    return [
        'chart' => [
            'type'            => $type,
            'height'          => 550,
            'backgroundColor' => 'transparent',
            'spacing'         => [16, 16, 16, 16],
            'style'           => ['fontFamily' => 'Tahoma, Vazirmatn, sans-serif'],
        ],
        'title'    => ['text' => null],
        'subtitle' => ['text' => null],
        'credits'  => ['enabled' => false],
        'exporting'=> ['enabled' => false],
        'legend'   => [
            'enabled'       => $showLegend,
            'align'         => 'center',
            'verticalAlign' => 'bottom',
            'layout'        => 'horizontal',
            'itemStyle'     => ['fontWeight' => 'normal', 'fontSize' => '12px'],
        ],
        'tooltip' => [
            'shared'      => true,
            'useHTML'     => true,
            'borderWidth' => 0,
            'shadow'      => true,
            'backgroundColor' => 'rgba(255,255,255,0.96)',
        ],
        'plotOptions' => [
            'column' => [
                'borderWidth'  => 0,
                'borderRadius' => 3,
                'groupPadding' => 0.12,
                'pointPadding' => 0.08,
            ],
            'bar' => [
                'borderWidth'  => 0,
                'borderRadius' => 3,
            ],
            'pie' => [
                'allowPointSelect' => true,
                'cursor'           => 'pointer',
                'innerSize'        => '52%',
                'dataLabels'       => [
                    'enabled' => true,
                    'format'  => '{point.name}: {point.y}',
                    'style'   => ['fontSize' => '11px', 'fontWeight' => 'normal', 'textOutline' => 'none'],
                ],
                'showInLegend' => true,
            ],
            'series' => [
                'animation' => ['duration' => 450],
            ],
        ],
        'xAxis' => [
            'crosshair'   => true,
            'lineColor'   => '#e5e7eb',
            'tickColor'   => '#e5e7eb',
            'labels'      => [
                'style'        => ['fontSize' => '11px', 'color' => '#6b7280'],
                'autoRotation' => [-35, -45],
            ],
        ],
        'yAxis' => [
            'title'      => ['text' => null],
            'min'        => 0,
            'gridLineColor' => '#f3f4f6',
            'lineWidth'  => 0,
            'labels'     => ['style' => ['fontSize' => '11px', 'color' => '#6b7280']],
            'allowDecimals' => false,
        ],
    ];
}

/**
 * Column series with optional per-point colors.
 *
 * @param float[]|int[] $values
 * @param string[]|null $colors
 */
function siba_leads_reports_hc_column_points(array $values, ?array $colors = null): array
{
    $points = [];
    foreach (array_values($values) as $i => $v) {
        $point = ['y' => is_numeric($v) ? (0 + $v) : 0];
        if (is_array($colors) && isset($colors[$i]) && $colors[$i] !== '') {
            $point['color'] = $colors[$i];
        }
        $points[] = $point;
    }

    return $points;
}

/**
 * Build Highcharts options from report rows (CRM-style large pane).
 *
 * @param string   $report
 * @param array    $rows
 * @param string[] $groupBy
 * @param array    $filters optional (metric, axis, …)
 * @return array|null Highcharts.Options
 */
function siba_leads_reports_chart_payload(string $report, array $rows, array $groupBy, array $filters = []): ?array
{
    if ($rows === []) {
        return null;
    }

    $metric = 'count';
    $valueKey = 'lead_count';
    $yTitle   = _l('siba_leads_reports_metric_count');

    $palette = [
        '#7dd3fc', '#4b5563', '#86efac', '#fb923c', '#c084fc',
        '#f472b6', '#facc15', '#14b8a6', '#f87171', '#a5f3fc',
        '#0f766e', '#1d4ed8', '#b45309', '#be123c', '#7c3aed',
    ];

    $labelFor = static function (array $row) use ($groupBy): string {
        $parts = [];
        if (in_array('date', $groupBy, true)) {
            $parts[] = (string) ($row['dim_date_label'] ?? $row['dim_date'] ?? '');
        }
        if (in_array('location', $groupBy, true)) {
            $parts[] = (string) ($row['dim_location_label'] ?? '');
        }
        if (in_array('assigned', $groupBy, true)) {
            $parts[] = (string) ($row['dim_assigned_label'] ?? '');
        }
        if (in_array('source', $groupBy, true)) {
            $parts[] = (string) ($row['dim_source_label'] ?? '');
        }
        if (in_array('status', $groupBy, true)) {
            $parts[] = (string) ($row['dim_status_label'] ?? '');
        }
        if (in_array('product', $groupBy, true)) {
            $parts[] = (string) ($row['dim_product_label'] ?? '');
        }
        $parts = array_values(array_filter($parts, static function ($p) {
            return $p !== '' && $p !== '—';
        }));

        return $parts !== [] ? implode(' · ', $parts) : _l('siba_leads_reports_chart_total');
    };

    $hcPie = static function (array $labels, array $values, array $colors) {
        $data = [];
        foreach ($labels as $i => $name) {
            $data[] = [
                'name'  => (string) $name,
                'y'     => (float) ($values[$i] ?? 0),
                'color' => $colors[$i] ?? '#9ca3af',
            ];
        }
        $opts = siba_leads_reports_hc_shell('pie', true);
        unset($opts['xAxis'], $opts['yAxis']);
        $opts['series'] = [[
            'name' => _l('siba_leads_reports_chart_total'),
            'data' => $data,
        ]];

        return $opts;
    };

    $hcColumns = static function (
        array $categories,
        array $seriesDefs,
        bool $showLegend = true,
        ?array $yAxes = null
    ) {
        $opts = siba_leads_reports_hc_shell('column', $showLegend);
        $opts['xAxis']['categories'] = array_values($categories);
        if ($yAxes !== null) {
            $opts['yAxis'] = $yAxes;
        }
        $opts['series'] = $seriesDefs;

        return $opts;
    };

    if ($report === 'registered' || $report === 'incoming') {
        $categories = [];
        $openData   = [];
        $saleData   = [];
        $failData   = [];

        foreach ($rows as $row) {
            if (in_array('assigned', $groupBy, true)) {
                $cat = (string) ($row['dim_assigned_label'] ?? '');
            } else {
                $cat = (string) ($row['dim_date_label'] ?? $row['dim_date'] ?? '');
            }
            if ($cat === '') {
                $cat = '—';
            }
            $categories[] = $cat;
            $openData[]   = (int) ($row['open_count'] ?? 0);
            $saleData[]   = (int) ($row['sale_count'] ?? 0);
            $failData[]   = (int) ($row['fail_count'] ?? 0);
        }

        // Stack bottom→top: failed (red), success (green), open/current (gold).
        $opts = $hcColumns($categories, [
            [
                'name'  => _l('siba_leads_reports_stack_failed'),
                'data'  => $failData,
                'color' => '#ef4444',
                'stack' => 'status',
            ],
            [
                'name'  => _l('siba_leads_reports_stack_success'),
                'data'  => $saleData,
                'color' => '#22c55e',
                'stack' => 'status',
            ],
            [
                'name'  => _l('siba_leads_reports_stack_open'),
                'data'  => $openData,
                'color' => '#eab308',
                'stack' => 'status',
            ],
        ], true);

        $opts['plotOptions']['column']['stacking'] = 'normal';
        $opts['yAxis']['stackLabels'] = [
            'enabled' => true,
            'style'   => [
                'fontWeight'   => '600',
                'color'        => '#374151',
                'textOutline'  => 'none',
                'fontSize'     => '11px',
            ],
        ];
        $opts['xAxis']['title'] = [
            'text'  => in_array('assigned', $groupBy, true)
                ? _l('leads_dt_assigned')
                : _l('siba_leads_reports_date_axis_title'),
            'style' => ['fontSize' => '12px', 'color' => '#6b7280'],
        ];

        return $opts;
    }

    if ($report === 'sources') {
        $stackLabels = [
            'enabled' => true,
            'style'   => [
                'fontWeight'  => '600',
                'color'       => '#374151',
                'textOutline' => 'none',
                'fontSize'    => '11px',
            ],
        ];

        // Sources × time: stacked columns (one series per acquisition source).
        if (
            in_array('date', $groupBy, true)
            && in_array('source', $groupBy, true)
        ) {
            $categories = [];
            $catIndex   = [];
            $seriesMap  = [];

            foreach ($rows as $row) {
                $cat = (string) ($row['dim_date_label'] ?? $row['dim_date'] ?? '');
                if ($cat === '') {
                    $cat = '—';
                }
                if (!isset($catIndex[$cat])) {
                    $catIndex[$cat] = count($categories);
                    $categories[]   = $cat;
                }

                $src = trim((string) ($row['dim_source_label'] ?? ''));
                if ($src === '' || $src === '—') {
                    $src = _l('siba_leads_reports_source_none');
                }
                if (!isset($seriesMap[$src])) {
                    $seriesMap[$src] = [];
                }
                $idx = $catIndex[$cat];
                $seriesMap[$src][$idx] = ($seriesMap[$src][$idx] ?? 0)
                    + (float) ($row[$valueKey] ?? 0);
            }

            $seriesDefs = [];
            $si = 0;
            foreach ($seriesMap as $name => $vals) {
                $data = [];
                for ($i = 0, $n = count($categories); $i < $n; $i++) {
                    $v = (float) ($vals[$i] ?? 0);
                    $data[] = $metric === 'amount' ? round($v, 0) : (int) $v;
                }
                $seriesDefs[] = [
                    'name'  => $name,
                    'data'  => $data,
                    'color' => $palette[$si % count($palette)],
                    'stack' => 'sources',
                ];
                $si++;
            }

            $opts = $hcColumns($categories, $seriesDefs, true);
            $opts['chart']['height'] = 550;
            $opts['plotOptions']['column']['stacking'] = 'normal';
            $opts['yAxis']['title'] = [
                'text'  => $yTitle,
                'align' => 'high',
                'offset' => 0,
                'rotation' => 0,
                'y' => -10,
                'style' => ['fontSize' => '12px', 'color' => '#6b7280'],
            ];
            $opts['yAxis']['stackLabels'] = $stackLabels;
            $opts['yAxis']['allowDecimals'] = $metric === 'amount';
            $opts['xAxis']['title'] = [
                'text'  => _l('siba_leads_reports_date_axis_title'),
                'style' => ['fontSize' => '12px', 'color' => '#6b7280'],
            ];
            $opts['tooltip'] = [
                'shared' => false,
                'useHTML' => true,
                'headerFormat' => '<span style="font-size:12px">{point.key}</span><br/>',
                'pointFormat'  => '<b>{series.name}</b>: {point.y}<br/>',
            ];
            $opts['legend']['maxHeight'] = 80;

            return $opts;
        }

        // By acquisition source only.
        $labels = [];
        $data   = [];
        $colors = [];
        foreach ($rows as $i => $row) {
            $labels[] = (string) ($row['dim_source_label'] ?? $labelFor($row));
            $v = (float) ($row[$valueKey] ?? 0);
            $data[]   = $metric === 'amount' ? round($v, 0) : (int) $v;
            $colors[] = $palette[$i % count($palette)];
        }

        $opts = $hcColumns($labels, [[
            'name'         => $yTitle,
            'data'         => siba_leads_reports_hc_column_points($data, $colors),
            'colorByPoint' => true,
            'showInLegend' => false,
        ]], false);
        $opts['yAxis']['title'] = [
            'text'  => $yTitle,
            'align' => 'high',
            'offset' => 0,
            'rotation' => 0,
            'y' => -10,
            'style' => ['fontSize' => '12px', 'color' => '#6b7280'],
        ];
        $opts['plotOptions']['column']['dataLabels'] = ['enabled' => true];

        return $opts;
    }

    if ($report === 'failures') {
        $failValueKey = $metric === 'amount' ? 'lead_value' : 'fail_count';

        $stackLabels = [
            'enabled' => true,
            'style'   => [
                'fontWeight' => 'bold',
                'fontSize'   => '12px',
                'color'      => '#111827',
                'textOutline'=> 'none',
            ],
            'formatter' => null,
        ];

        // By reasons only → pie.
        if ($groupBy === [] || (($filters['axis'] ?? '') === 'reason')) {
            $byReason = [];
            foreach ($rows as $row) {
                $title = trim((string) ($row['reason_title'] ?? ''));
                if ($title === '') {
                    $title = _l('siba_leads_reports_reason_unknown');
                }
                $color = trim((string) ($row['reason_color'] ?? ''));
                if ($color === '') {
                    $color = '#9ca3af';
                }
                if (!isset($byReason[$title])) {
                    $byReason[$title] = ['value' => 0.0, 'color' => $color];
                }
                $byReason[$title]['value'] += (float) ($row[$failValueKey] ?? 0);
            }

            $labels = array_keys($byReason);
            $values = [];
            $colors = [];
            foreach ($byReason as $meta) {
                $values[] = $metric === 'amount' ? round($meta['value'], 0) : (int) $meta['value'];
                $colors[] = $meta['color'];
            }

            $opts = $hcPie($labels, $values, $colors);
            $opts['plotOptions']['pie']['dataLabels'] = [
                'enabled' => true,
                'format'  => '{point.name}: {point.y}',
            ];

            return $opts;
        }

        // Stacked columns: category axis × failure reasons.
        $categories = [];
        $catIndex   = [];
        $seriesMap  = [];
        $seriesColor = [];

        foreach ($rows as $row) {
            $cat = $labelFor($row);
            if ($cat === '' || $cat === _l('siba_leads_reports_chart_total')) {
                $cat = '—';
            }
            if (!isset($catIndex[$cat])) {
                $catIndex[$cat] = count($categories);
                $categories[]   = $cat;
            }

            $reason = trim((string) ($row['reason_title'] ?? ''));
            if ($reason === '') {
                $reason = _l('siba_leads_reports_reason_unknown');
            }
            $color = trim((string) ($row['reason_color'] ?? ''));
            if ($color === '') {
                $color = '#9ca3af';
            }
            if (!isset($seriesMap[$reason])) {
                $seriesMap[$reason] = [];
                $seriesColor[$reason] = $color;
            }
            $idx = $catIndex[$cat];
            $seriesMap[$reason][$idx] = ($seriesMap[$reason][$idx] ?? 0)
                + (float) ($row[$failValueKey] ?? 0);
        }

        $seriesDefs = [];
        $si = 0;
        foreach ($seriesMap as $name => $vals) {
            $data = [];
            for ($i = 0, $n = count($categories); $i < $n; $i++) {
                $v = (float) ($vals[$i] ?? 0);
                $data[] = $metric === 'amount' ? round($v, 0) : (int) $v;
            }
            $seriesDefs[] = [
                'name'  => $name,
                'data'  => $data,
                'color' => $seriesColor[$name] ?? $palette[$si % count($palette)],
                'stack' => 'failures',
            ];
            $si++;
        }

        $xTitle = _l('siba_leads_reports_date_axis_title');
        if (in_array('assigned', $groupBy, true)) {
            $xTitle = _l('leads_dt_assigned');
        } elseif (in_array('status', $groupBy, true)) {
            $xTitle = _l('siba_leads_reports_dim_status');
        } elseif (in_array('product', $groupBy, true)) {
            $xTitle = _l('siba_leads_reports_dim_product');
        }

        $opts = $hcColumns($categories, $seriesDefs, true);
        $opts['chart']['height'] = 550;
        $opts['plotOptions']['column']['stacking'] = 'normal';
        $opts['xAxis']['title'] = [
            'text'  => $xTitle,
            'style' => ['fontSize' => '12px', 'color' => '#6b7280'],
        ];
        $opts['yAxis']['title'] = [
            'text'     => $yTitle,
            'align'    => 'high',
            'offset'   => 0,
            'rotation' => 0,
            'y'        => -10,
            'style'    => ['fontSize' => '12px', 'color' => '#6b7280'],
        ];
        $opts['yAxis']['stackLabels'] = [
            'enabled' => true,
            'style'   => [
                'fontWeight'  => 'bold',
                'fontSize'    => '12px',
                'color'       => '#111827',
                'textOutline' => 'none',
            ],
        ];
        $opts['yAxis']['allowDecimals'] = $metric === 'amount';
        $opts['yAxis']['min'] = 0;
        $opts['yAxis']['gridLineColor'] = '#e5e7eb';
        $opts['legend'] = [
            'enabled'       => true,
            'align'         => 'center',
            'verticalAlign' => 'bottom',
            'layout'        => 'horizontal',
            'maxHeight'     => 120,
            'itemStyle'     => ['fontWeight' => 'normal', 'fontSize' => '12px'],
        ];
        $opts['tooltip'] = [
            'shared'      => true,
            'useHTML'     => true,
            'headerFormat'=> '<span style="font-size:12px">{point.key}</span><br/>',
            'pointFormat' => '<span style="color:{point.color}">●</span> {series.name}: <b>{point.y}</b><br/>',
        ];

        return $opts;
    }

    if ($report === 'conversion') {
        $categories = [];
        $rates      = [];
        foreach ($rows as $row) {
            if (in_array('assigned', $groupBy, true)) {
                $cat = (string) ($row['dim_assigned_label'] ?? '');
            } else {
                $cat = (string) ($row['dim_date_label'] ?? $row['dim_date'] ?? '');
            }
            if ($cat === '') {
                $cat = '—';
            }
            $categories[] = $cat;
            $rates[]      = (float) ($row['conversion_rate'] ?? 0);
        }

        $opts = siba_leads_reports_hc_shell('line', false);
        $opts['xAxis']['categories'] = array_values($categories);
        $opts['xAxis']['title'] = [
            'text'  => in_array('assigned', $groupBy, true)
                ? _l('leads_dt_assigned')
                : _l('siba_leads_reports_date_axis_title'),
            'style' => ['fontSize' => '12px', 'color' => '#6b7280'],
        ];
        $opts['yAxis'] = [
            'title' => [
                'text'  => _l('siba_leads_reports_conversion_rate'),
                'style' => ['fontSize' => '12px', 'color' => '#6b7280'],
            ],
            'min'           => 0,
            'gridLineColor' => '#e5e7eb',
            'lineWidth'     => 0,
            'labels'        => [
                'style' => ['fontSize' => '11px', 'color' => '#6b7280'],
            ],
            'allowDecimals' => true,
        ];
        $opts['plotOptions']['line'] = [
            'marker' => [
                'enabled' => true,
                'radius'  => 5,
                'symbol'  => 'circle',
                'fillColor' => '#7dd3fc',
                'lineWidth' => 0,
            ],
            'lineWidth' => 2,
            'color'     => '#7dd3fc',
        ];
        $opts['tooltip']['valueSuffix'] = '%';
        $opts['series'] = [[
            'name'  => _l('siba_leads_reports_conversion_rate'),
            'data'  => $rates,
            'color' => '#7dd3fc',
            'showInLegend' => false,
        ]];

        return $opts;
    }

    if ($report === 'pipeline') {
        $labels = [];
        $data   = [];
        $colors = [];
        foreach ($rows as $i => $row) {
            $labels[] = (string) ($row['dim_status_label'] ?? $labelFor($row));
            $data[]   = (int) ($row['lead_count'] ?? 0);
            $colors[] = !empty($row['dim_status_color'])
                ? $row['dim_status_color']
                : $palette[$i % count($palette)];
        }

        return $hcPie($labels, $data, $colors);
    }

    if ($report === 'speed') {
        $labels = [];
        $avgs   = [];
        foreach ($rows as $row) {
            $labels[] = $labelFor($row);
            $avgs[]   = (float) ($row['avg_days'] ?? 0);
        }

        return $hcColumns($labels, [[
            'name'  => _l('siba_leads_reports_col_avg_days'),
            'data'  => $avgs,
            'color' => '#0369a1',
            'showInLegend' => false,
        ]], false);
    }

    if ($report === 'sales') {
        $labels = [];
        $values = [];
        $counts = [];
        foreach ($rows as $row) {
            $labels[] = $labelFor($row);
            $values[] = (float) ($row['sale_value'] ?? 0);
            $counts[] = (int) ($row['sale_count'] ?? 0);
        }

        return $hcColumns($labels, [
            [
                'name'  => _l('siba_leads_reports_col_sale_value'),
                'data'  => $values,
                'color' => '#047857',
                'yAxis' => 0,
            ],
            [
                'name'  => _l('siba_leads_reports_col_sales'),
                'data'  => $counts,
                'color' => '#1d4ed8',
                'yAxis' => 1,
            ],
        ], true, [
            [
                'title'         => ['text' => null],
                'min'           => 0,
                'gridLineColor' => '#f3f4f6',
                'labels'        => ['style' => ['color' => '#6b7280']],
            ],
            [
                'title'         => ['text' => null],
                'opposite'      => true,
                'min'           => 0,
                'allowDecimals' => false,
                'gridLineWidth' => 0,
                'labels'        => ['style' => ['color' => '#1d4ed8']],
            ],
        ]);
    }

    if ($report === 'analysis') {
        $labels = [];
        $leads  = [];
        $sales  = [];
        $fails  = [];
        foreach ($rows as $row) {
            $labels[] = $labelFor($row);
            $leads[]  = (int) ($row['lead_count'] ?? 0);
            $sales[]  = (int) ($row['sale_count'] ?? 0);
            $fails[]  = (int) ($row['fail_count'] ?? 0);
        }

        return $hcColumns($labels, [
            [
                'name'  => _l('siba_leads_reports_col_leads'),
                'data'  => $leads,
                'color' => '#0f766e',
            ],
            [
                'name'  => _l('siba_leads_reports_col_sales'),
                'data'  => $sales,
                'color' => '#1d4ed8',
            ],
            [
                'name'  => _l('siba_leads_reports_col_failures'),
                'data'  => $fails,
                'color' => '#be123c',
            ],
        ], true);
    }

    return null;
}

/**
 * Build a map key from dimension values.
 */
function siba_leads_reports_row_key(array $row, array $groupBy): string
{
    $parts = [];
    foreach ($groupBy as $dim) {
        $parts[] = (string) ($row['dim_' . $dim] ?? '');
    }

    return implode('|', $parts);
}

/**
 * Conversion rate by registration cohort (same axes as incoming analysis).
 *
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_conversion(array $filters, array $groupBy): array
{
    $result = siba_leads_reports_analysis($filters, $groupBy);

    // Keep only conversion-focused summary fields.
    $result['summary'] = [
        'lead_count'      => (int) ($result['summary']['lead_count'] ?? 0),
        'sale_count'      => (int) ($result['summary']['sale_count'] ?? 0),
        'conversion_rate' => (float) ($result['summary']['conversion_rate'] ?? 0),
    ];

    return $result;
}

/**
 * Dispatch report runner by tab key.
 *
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_run(array $filters, array $groupBy): array
{
    $report = (string) ($filters['report'] ?? 'analysis');
    if ($report === 'registered') {
        $report = 'incoming';
    }

    switch ($report) {
        case 'failures':
            $result = siba_leads_reports_failures($filters, $groupBy);
            break;
        case 'conversion':
            $result = siba_leads_reports_conversion($filters, $groupBy);
            break;
        case 'pipeline':
            $result = siba_leads_reports_pipeline($filters, $groupBy);
            break;
        case 'speed':
            $result = siba_leads_reports_speed($filters, $groupBy);
            break;
        case 'sources':
            $result = siba_leads_reports_sources($filters, $groupBy);
            break;
        case 'sales':
            $result = siba_leads_reports_sales($filters, $groupBy);
            break;
        case 'analysis':
            $result = siba_leads_reports_analysis($filters, $groupBy);
            break;
        case 'incoming':
        default:
            $result = siba_leads_reports_registered($filters, $groupBy);
            break;
    }

    return $result;
}

/**
 * Multiply monetary fields by exchange rate (نرخ تسعیر).
 *
 * @param array{rows:array, summary:array} $result
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_apply_fx_rate(array $result, float $fxRate): array
{
    if ($fxRate <= 0 || abs($fxRate - 1.0) < 0.0000001) {
        return $result;
    }

    $moneyKeys = ['sale_value', 'lead_value', 'avg_value'];

    foreach ($result['rows'] as &$row) {
        foreach ($moneyKeys as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                $row[$key] = round((float) $row[$key] * $fxRate, 2);
            }
        }
    }
    unset($row);

    if (!empty($result['summary']) && is_array($result['summary'])) {
        foreach ($moneyKeys as $key) {
            if (isset($result['summary'][$key]) && is_numeric($result['summary'][$key])) {
                $result['summary'][$key] = round((float) $result['summary'][$key] * $fxRate, 2);
            }
        }
    }

    return $result;
}

/**
 * Deal analysis: leads / sales / failures / rate (+ optional value) by dimensions.
 *
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_analysis(array $filters, array $groupBy): array
{
    $CI      = &get_instance();
    $leadsT  = db_prefix() . 'leads';
    $bucket  = $filters['date_bucket'] ?? 'day';
    $dateExpr = 'l.dateadded';
    $dims    = siba_leads_reports_dimension_sql($groupBy, $dateExpr, $bucket, 'l');
    $saleSql = siba_leads_reports_is_sale_sql('l');
    $failSql = siba_leads_reports_is_failed_sql('l');

    $select = $dims['select'];
    $select[] = 'COUNT(*) AS lead_count';
    $select[] = 'SUM(CASE WHEN ' . $saleSql . ' THEN 1 ELSE 0 END) AS sale_count';
    $select[] = 'SUM(CASE WHEN NOT (' . $saleSql . ') AND ' . $failSql . ' THEN 1 ELSE 0 END) AS fail_count';
    $select[] = 'SUM(CASE WHEN ' . $saleSql . ' THEN COALESCE(l.lead_value, 0) ELSE 0 END) AS sale_value';
    $select[] = 'SUM(CASE WHEN NOT (' . $saleSql . ') AND NOT (' . $failSql . ') THEN 1 ELSE 0 END) AS open_count';

    $CI->db->select(implode(', ', $select), false);
    $CI->db->from($leadsT . ' l');
    $CI->db->where('l.junk', 0);
    siba_leads_reports_apply_common_filters($CI, $filters, $dateExpr);

    if (!empty($dims['group'])) {
        $CI->db->group_by($dims['group']);
        if (in_array('date', $groupBy, true)) {
            $CI->db->order_by('dim_date', 'ASC');
        } elseif (in_array('location', $groupBy, true)) {
            $CI->db->order_by('lead_count', 'DESC');
        } elseif (in_array('assigned', $groupBy, true)) {
            $CI->db->order_by('lead_count', 'DESC');
        }
    }

    $rows = $CI->db->get()->result_array();
    $sumLeads = 0;
    $sumSales = 0;
    $sumFails = 0;
    $sumOpen  = 0;
    $sumValue = 0.0;

    foreach ($rows as &$row) {
        $leads = (int) ($row['lead_count'] ?? 0);
        $sales = (int) ($row['sale_count'] ?? 0);
        $fails = (int) ($row['fail_count'] ?? 0);
        $open  = (int) ($row['open_count'] ?? 0);
        $value = (float) ($row['sale_value'] ?? 0);
        $row['lead_count'] = $leads;
        $row['sale_count'] = $sales;
        $row['fail_count'] = $fails;
        $row['open_count'] = $open;
        $row['sale_value'] = $value;
        $row['conversion_rate'] = $leads > 0 ? round(($sales / $leads) * 100, 2) : 0.0;
        $row = siba_leads_reports_enrich_row($row, $groupBy, $bucket);
        $sumLeads += $leads;
        $sumSales += $sales;
        $sumFails += $fails;
        $sumOpen  += $open;
        $sumValue += $value;
    }
    unset($row);

    if (empty($groupBy) && empty($rows)) {
        $rows = [[
            'lead_count' => 0, 'sale_count' => 0, 'fail_count' => 0,
            'open_count' => 0, 'sale_value' => 0, 'conversion_rate' => 0,
        ]];
    }

    return [
        'rows'    => $rows,
        'summary' => [
            'lead_count'      => $sumLeads,
            'sale_count'      => $sumSales,
            'fail_count'      => $sumFails,
            'open_count'      => $sumOpen,
            'sale_value'      => $sumValue,
            'conversion_rate' => $sumLeads > 0 ? round(($sumSales / $sumLeads) * 100, 2) : 0.0,
        ],
    ];
}

/**
 * Leads currently in each pipeline stage (plus lost).
 *
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_pipeline(array $filters, array $groupBy): array
{
    $CI     = &get_instance();
    $leadsT = db_prefix() . 'leads';
    $dateExpr = 'l.dateadded';

    // Always break down by stage; allow extra dims.
    if (!in_array('status', $groupBy, true)) {
        $groupBy[] = 'status';
    }

    $dims = siba_leads_reports_dimension_sql(
        $groupBy,
        $dateExpr,
        $filters['date_bucket'] ?? 'day',
        'l'
    );

    $select = $dims['select'];
    $select[] = 'MAX(l.lost) AS dim_lost';
    $select[] = 'COUNT(*) AS lead_count';
    $select[] = 'SUM(COALESCE(l.lead_value, 0)) AS lead_value';

    $CI->db->select(implode(', ', $select), false);
    $CI->db->from($leadsT . ' l');
    // Pipeline view: open stages + lost (exclude junk).
    $CI->db->where('l.junk', 0);
    siba_leads_reports_apply_common_filters($CI, $filters, $dateExpr);

    $groupCols = $dims['group'];
    $groupCols[] = 'l.lost';
    $CI->db->group_by($groupCols);
    $CI->db->order_by('lead_count', 'DESC');

    $rows = $CI->db->get()->result_array();
    $total = 0;
    $totalValue = 0.0;
    foreach ($rows as &$row) {
        if ((int) ($row['dim_lost'] ?? 0) === 1) {
            $row['is_lost_bucket'] = 1;
        }
        $row = siba_leads_reports_enrich_row($row, $groupBy, $filters['date_bucket'] ?? 'day');
        $total += (int) ($row['lead_count'] ?? 0);
        $totalValue += (float) ($row['lead_value'] ?? 0);
    }
    unset($row);

    return [
        'rows'    => $rows,
        'summary' => [
            'lead_count' => $total,
            'lead_value' => $totalValue,
        ],
    ];
}

/**
 * Incoming deals by acquisition source — stacked by time × source (or source-only).
 *
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_sources(array $filters, array $groupBy): array
{
    $CI       = &get_instance();
    $leadsT   = db_prefix() . 'leads';
    $dateExpr = 'l.dateadded';
    $bucket   = $filters['date_bucket'] ?? 'week';

    if (!in_array('source', $groupBy, true)) {
        $groupBy[] = 'source';
    }

    $dims   = siba_leads_reports_dimension_sql($groupBy, $dateExpr, $bucket, 'l');
    $select = $dims['select'];
    $select[] = 'COUNT(*) AS lead_count';
    $select[] = 'SUM(COALESCE(l.lead_value, 0)) AS lead_value';

    $CI->db->select(implode(', ', $select), false);
    $CI->db->from($leadsT . ' l');
    $CI->db->where('l.junk', 0);
    siba_leads_reports_apply_common_filters($CI, $filters, $dateExpr);

    if (!empty($dims['group'])) {
        $CI->db->group_by($dims['group']);
        if (in_array('date', $groupBy, true)) {
            $CI->db->order_by('dim_date', 'ASC');
        } else {
            $CI->db->order_by('lead_count', 'DESC');
        }
    }

    $rows = $CI->db->get()->result_array();
    $totalCount = 0;
    $totalValue = 0.0;
    foreach ($rows as &$row) {
        $row = siba_leads_reports_enrich_row($row, $groupBy, $bucket);
        $totalCount += (int) ($row['lead_count'] ?? 0);
        $totalValue += (float) ($row['lead_value'] ?? 0);
    }
    unset($row);

    return [
        'rows'    => $rows,
        'summary' => [
            'lead_count' => $totalCount,
            'lead_value' => $totalValue,
        ],
    ];
}

/**
 * Conversion speed: average days from dateadded to sale.
 *
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_speed(array $filters, array $groupBy): array
{
    $CI       = &get_instance();
    $leadsT   = db_prefix() . 'leads';
    $saleDate = siba_leads_reports_sale_date_sql('l');
    $bucket   = $filters['date_bucket'] ?? 'day';
    $dims     = siba_leads_reports_dimension_sql($groupBy, $saleDate, $bucket, 'l');

    $dayExpr = 'DATEDIFF(' . $saleDate . ', l.dateadded)';
    $select  = $dims['select'];
    $select[] = 'COUNT(*) AS sale_count';
    $select[] = 'AVG(' . $dayExpr . ') AS avg_days';
    $select[] = 'MIN(' . $dayExpr . ') AS min_days';
    $select[] = 'MAX(' . $dayExpr . ') AS max_days';

    $CI->db->select(implode(', ', $select), false);
    $CI->db->from($leadsT . ' l');
    $CI->db->where($saleDate . ' IS NOT NULL', null, false);
    $CI->db->where($dayExpr . ' IS NOT NULL', null, false);
    $CI->db->where($dayExpr . ' >= 0', null, false);
    siba_leads_reports_apply_common_filters($CI, $filters, $saleDate);

    if (!empty($dims['group'])) {
        $CI->db->group_by($dims['group']);
        if (in_array('date', $groupBy, true)) {
            $CI->db->order_by('dim_date', 'ASC');
        }
    }

    $rows = $CI->db->get()->result_array();
    $sumCount = 0;
    $weighted = 0.0;
    $minAll = null;
    $maxAll = null;

    foreach ($rows as &$row) {
        $count = (int) ($row['sale_count'] ?? 0);
        $avg   = round((float) ($row['avg_days'] ?? 0), 1);
        $min   = (int) ($row['min_days'] ?? 0);
        $max   = (int) ($row['max_days'] ?? 0);
        $row['sale_count'] = $count;
        $row['avg_days']   = $avg;
        $row['min_days']   = $min;
        $row['max_days']   = $max;
        $row = siba_leads_reports_enrich_row($row, $groupBy, $bucket);
        $sumCount += $count;
        $weighted += $avg * $count;
        $minAll = $minAll === null ? $min : min($minAll, $min);
        $maxAll = $maxAll === null ? $max : max($maxAll, $max);
    }
    unset($row);

    if (empty($groupBy) && empty($rows)) {
        $rows = [['sale_count' => 0, 'avg_days' => 0, 'min_days' => 0, 'max_days' => 0]];
    }

    return [
        'rows'    => $rows,
        'summary' => [
            'sale_count' => $sumCount,
            'avg_days'   => $sumCount > 0 ? round($weighted / $sumCount, 1) : 0.0,
            'min_days'   => $minAll !== null ? $minAll : 0,
            'max_days'   => $maxAll !== null ? $maxAll : 0,
        ],
    ];
}

/**
 * Sales analysis: converted lead count + lead_value.
 *
 * @return array{rows:array, summary:array}
 */
function siba_leads_reports_sales(array $filters, array $groupBy): array
{
    $CI       = &get_instance();
    $leadsT   = db_prefix() . 'leads';
    $saleDate = siba_leads_reports_sale_date_sql('l');
    $bucket   = $filters['date_bucket'] ?? 'day';
    $dims     = siba_leads_reports_dimension_sql($groupBy, $saleDate, $bucket, 'l');

    $select = $dims['select'];
    $select[] = 'COUNT(*) AS sale_count';
    $select[] = 'SUM(COALESCE(l.lead_value, 0)) AS sale_value';
    $select[] = 'AVG(COALESCE(l.lead_value, 0)) AS avg_value';

    $CI->db->select(implode(', ', $select), false);
    $CI->db->from($leadsT . ' l');
    $CI->db->where($saleDate . ' IS NOT NULL', null, false);
    siba_leads_reports_apply_common_filters($CI, $filters, $saleDate);
    if (!empty($dims['group'])) {
        $CI->db->group_by($dims['group']);
        if (in_array('date', $groupBy, true)) {
            $CI->db->order_by('dim_date', 'ASC');
        }
    }

    $rows = $CI->db->get()->result_array();
    $sumCount = 0;
    $sumValue = 0.0;
    foreach ($rows as &$row) {
        $count = (int) ($row['sale_count'] ?? 0);
        $value = (float) ($row['sale_value'] ?? 0);
        $row['sale_count'] = $count;
        $row['sale_value'] = $value;
        $row['avg_value']  = round((float) ($row['avg_value'] ?? 0), 0);
        $row = siba_leads_reports_enrich_row($row, $groupBy, $bucket);
        $sumCount += $count;
        $sumValue += $value;
    }
    unset($row);

    if (empty($groupBy) && empty($rows)) {
        $rows = [['sale_count' => 0, 'sale_value' => 0, 'avg_value' => 0]];
    }

    return [
        'rows'    => $rows,
        'summary' => [
            'sale_count' => $sumCount,
            'sale_value' => $sumValue,
            'avg_value'  => $sumCount > 0 ? round($sumValue / $sumCount, 0) : 0.0,
        ],
    ];
}
