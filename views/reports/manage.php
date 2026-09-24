<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$report   = $filters['report'] ?? 'incoming';
$groupBy  = $group_by ?? ($filters['group_by'] ?? ['date']);
$summary  = $summary ?? [];
$rows     = $rows ?? [];
$axis     = $filters['axis'] ?? 'time';
$baseUrl  = admin_url('siba_leads/reports');
$tabs     = $report_tabs ?? (function_exists('siba_leads_reports_tabs') ? siba_leads_reports_tabs() : ['incoming']);
$isSources = ($report === 'sources');
$isFailures = ($report === 'failures');

$tabLang = [
    'incoming'    => 'siba_leads_reports_incoming',
    'sources'     => 'siba_leads_reports_sources',
    'failures'    => 'siba_leads_reports_failures',
    'conversion'  => 'siba_leads_reports_conversion',
    'stage_time'  => 'siba_leads_reports_stage_time',
    'pipeline'    => 'siba_leads_reports_pipeline',
    'speed'       => 'siba_leads_reports_speed',
    'staff'       => 'siba_leads_reports_staff',
    'aging'       => 'siba_leads_reports_aging',
    'response'    => 'siba_leads_reports_response',
    'sales'       => 'siba_leads_reports_sales',
    'analysis'    => 'siba_leads_reports_analysis',
];

$reportTitle = _l($tabLang[$report] ?? 'siba_leads_reports');
$hideDateBucket = in_array($report, ['stage_time', 'pipeline', 'staff', 'aging'], true);

$buildQuery = static function (array $override = []) use ($filters, $report) {
    $q = array_merge([
        'report'      => $filters['report'] ?? $report,
        'date_from'   => $filters['date_from'] ?? '',
        'date_to'     => $filters['date_to'] ?? '',
        'date_bucket' => $filters['date_bucket'] ?? 'day',
        'axis'        => $filters['axis'] ?? 'time',
    ], $override);
    $q = array_filter($q, static function ($v) {
        return $v !== null && $v !== '';
    });
    if (!empty($filters['assigned']) && !isset($override['assigned'])) {
        $q['assigned'] = array_values(array_map('intval', $filters['assigned']));
    }
    if (!empty($filters['source']) && !isset($override['source'])) {
        $q['source'] = array_values(array_map('intval', $filters['source']));
    }

    return $q;
};

$tabUrl = static function ($tab) use ($buildQuery, $baseUrl) {
    $defaults = [
        'failures'   => ['axis' => 'time', 'date_bucket' => 'week'],
        'sources'    => ['axis' => 'time', 'date_bucket' => 'week'],
        'incoming'   => ['axis' => 'time'],
        'conversion' => ['axis' => 'time'],
        'stage_time' => ['axis' => 'status'],
        'pipeline'   => ['axis' => 'status'],
        'speed'      => ['axis' => 'time'],
        'staff'      => ['axis' => 'users'],
        'aging'      => ['axis' => 'status'],
        'response'   => ['axis' => 'users'],
    ];
    $override = array_merge(['report' => $tab], $defaults[$tab] ?? []);

    return $baseUrl . '?' . http_build_query($buildQuery($override));
};

$axisUrl = static function ($axisKey) use ($buildQuery, $baseUrl, $report) {
    $q = $buildQuery(['report' => $report, 'axis' => $axisKey]);

    return $baseUrl . '?' . http_build_query($q);
};

?>
<div id="wrapper" class="siba-leads-reports-page">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="siba-main-report-cntr with-rightbar">
                    <nav class="siba-report-idenav idenav no-print" aria-label="<?= e(_l('siba_leads_reports_menu')); ?>">
                        <div class="siba-idenav-title"><?= e(_l('siba_leads_reports_menu')); ?></div>
                        <ul class="h-100 scroll-y">
                            <?php foreach ($tabs as $tabKey) {
                                $langKey = $tabLang[$tabKey] ?? ('siba_leads_reports_' . $tabKey);
                                ?>
                                <li class="<?= $report === $tabKey ? 'active' : ''; ?>">
                                    <a href="<?= e($tabUrl($tabKey)); ?>"><?= e(_l($langKey)); ?></a>
                                </li>
                            <?php } ?>
                        </ul>
                    </nav>

                    <div class="siba-report-chart-cntr report-chart-cntr">
                        <div class="siba-report-toolbar">
                            <h3 class="siba-report-title"><?= e($reportTitle); ?></h3>

                            <div class="siba-report-axis-tabs">
                                <?php if (in_array($report, ['stage_time', 'pipeline', 'aging'], true)) { ?>
                                    <span class="siba-axis-tab is-active"><?= e(_l('siba_leads_reports_axis_pipeline')); ?></span>
                                <?php } elseif ($report === 'staff') { ?>
                                    <span class="siba-axis-tab is-active"><?= e(_l('siba_leads_reports_axis_users')); ?></span>
                                <?php } elseif ($isFailures) { ?>
                                    <a href="<?= e($axisUrl('time')); ?>" class="siba-axis-tab <?= $axis === 'time' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_time')); ?></a>
                                    <a href="<?= e($axisUrl('reason')); ?>" class="siba-axis-tab <?= $axis === 'reason' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_reason')); ?></a>
                                    <a href="<?= e($axisUrl('users')); ?>" class="siba-axis-tab <?= $axis === 'users' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_users')); ?></a>
                                    <a href="<?= e($axisUrl('status')); ?>" class="siba-axis-tab <?= $axis === 'status' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_pipeline')); ?></a>
                                <?php } elseif ($report === 'sources') { ?>
                                    <a href="<?= e($axisUrl('time')); ?>" class="siba-axis-tab <?= $axis === 'time' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_time')); ?></a>
                                    <a href="<?= e($axisUrl('source')); ?>" class="siba-axis-tab <?= $axis === 'source' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_source')); ?></a>
                                <?php } elseif ($report === 'speed' || $report === 'response') { ?>
                                    <a href="<?= e($axisUrl('time')); ?>" class="siba-axis-tab <?= $axis === 'time' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_time')); ?></a>
                                    <a href="<?= e($axisUrl('users')); ?>" class="siba-axis-tab <?= $axis === 'users' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_users')); ?></a>
                                    <a href="<?= e($axisUrl('source')); ?>" class="siba-axis-tab <?= $axis === 'source' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_source')); ?></a>
                                <?php } elseif ($report === 'conversion') { ?>
                                    <a href="<?= e($axisUrl('time')); ?>" class="siba-axis-tab <?= $axis === 'time' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_time')); ?></a>
                                    <a href="<?= e($axisUrl('users')); ?>" class="siba-axis-tab <?= $axis === 'users' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_users')); ?></a>
                                <?php } else { ?>
                                    <a href="<?= e($axisUrl('time')); ?>" class="siba-axis-tab <?= $axis === 'time' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_time')); ?></a>
                                    <a href="<?= e($axisUrl('users')); ?>" class="siba-axis-tab <?= $axis === 'users' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_users')); ?></a>
                                <?php } ?>
                            </div>

                            <div class="siba-report-toolbar-actions siba-no-print">
                                <button type="button" class="siba-tb-btn" onclick="window.print();">
                                    <i class="fa fa-print"></i> <?= e(_l('siba_leads_reports_print')); ?>
                                </button>
                            </div>
                        </div>

                        <?= form_open($baseUrl, ['method' => 'get', 'id' => 'siba-leads-reports-filters', 'class' => 'siba-report-filters-bar']); ?>
                        <input type="hidden" name="report" value="<?= e($report); ?>">
                        <input type="hidden" name="axis" value="<?= e($axis); ?>">

                        <?php if (!empty($can_view_all)) { ?>
                        <div class="form-group" style="min-width:150px;">
                            <?= render_select('assigned[]', $staff ?? [], ['staffid', ['firstname', 'lastname']], '',
                                !empty($filters['assigned']) ? $filters['assigned'] : [],
                                ['multiple' => true, 'data-actions-box' => true, 'data-live-search' => true, 'data-width' => '100%',
                                    'data-none-selected-text' => _l('siba_leads_reports_all_users')], [], '', '', false); ?>
                        </div>
                        <?php } ?>

                        <div class="form-group" style="min-width:160px;">
                            <?= render_select('source[]', $sources ?? [], ['id', 'name'], '',
                                !empty($filters['source']) ? $filters['source'] : [],
                                ['multiple' => true, 'data-actions-box' => true, 'data-live-search' => true, 'data-width' => '100%',
                                    'data-none-selected-text' => _l('siba_leads_reports_all_sources')], [], '', '', false); ?>
                        </div>

                        <?php if (!$hideDateBucket) { ?>
                        <div class="form-group" style="min-width:110px;">
                            <select name="date_bucket" id="date_bucket" class="selectpicker" data-width="100%">
                                <option value="day" <?= ($filters['date_bucket'] ?? '') === 'day' ? 'selected' : ''; ?>><?= e(_l('siba_leads_reports_bucket_day')); ?></option>
                                <option value="week" <?= ($filters['date_bucket'] ?? (($report === 'sources' || $report === 'failures') ? 'week' : 'day')) === 'week' ? 'selected' : ''; ?>><?= e(_l('siba_leads_reports_bucket_week')); ?></option>
                                <option value="month" <?= ($filters['date_bucket'] ?? '') === 'month' ? 'selected' : ''; ?>><?= e(_l('siba_leads_reports_bucket_month')); ?></option>
                                <option value="quarter" <?= ($filters['date_bucket'] ?? '') === 'quarter' ? 'selected' : ''; ?>><?= e(_l('siba_leads_reports_bucket_quarter')); ?></option>
                            </select>
                        </div>
                        <?php } ?>

                        <div class="form-group">
                            <div class="input-group date">
                                <input type="text" id="date_from" name="date_from" class="form-control siba-jalali-datepicker"
                                       value="<?= e($filters['date_from'] ?? ''); ?>"
                                       placeholder="<?= e(_l('siba_leads_reports_date_from')); ?>" autocomplete="off">
                                <div class="input-group-addon"><i class="fa-regular fa-calendar calendar-icon"></i></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-group date">
                                <input type="text" id="date_to" name="date_to" class="form-control siba-jalali-datepicker"
                                       value="<?= e($filters['date_to'] ?? ''); ?>"
                                       placeholder="<?= e(_l('siba_leads_reports_date_to')); ?>" autocomplete="off">
                                <div class="input-group-addon"><i class="fa-regular fa-calendar calendar-icon"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> <?= _l('filter'); ?></button>
                            <a href="<?= e($tabUrl($report)); ?>" class="btn btn-default"><?= _l('clear'); ?></a>
                        </div>
                        <?= form_close(); ?>

                        <div class="siba-report-summary-strip">
                            <?php if ($isFailures) { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_failures')); ?></div><div class="val"><?= (int) ($summary['fail_count'] ?? 0); ?></div></div>
                            <?php } elseif ($isSources) { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_leads')); ?></div><div class="val"><?= (int) ($summary['lead_count'] ?? 0); ?></div></div>
                            <?php } elseif ($report === 'conversion') { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_leads')); ?></div><div class="val"><?= (int) ($summary['lead_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_sales')); ?></div><div class="val"><?= (int) ($summary['sale_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_conversion_rate')); ?></div><div class="val" dir="ltr"><?= e(number_format((float) ($summary['conversion_rate'] ?? 0), 2)); ?>%</div></div>
                            <?php } elseif ($report === 'stage_time') { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_stage_open_cards')); ?></div><div class="val"><?= (int) ($summary['open_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_stage_completed_stays')); ?></div><div class="val"><?= (int) ($summary['done_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_col_avg_days_in_column')); ?></div><div class="val" dir="ltr"><?= e(number_format((float) ($summary['avg_days'] ?? 0), 1)); ?></div></div>
                            <?php } elseif ($report === 'pipeline') { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_open')); ?></div><div class="val"><?= (int) ($summary['lead_count'] ?? 0); ?></div></div>
                            <?php } elseif ($report === 'speed') { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_sales')); ?></div><div class="val"><?= (int) ($summary['sale_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_avg_days')); ?></div><div class="val" dir="ltr"><?= e(number_format((float) ($summary['avg_days'] ?? 0), 1)); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_min_days')); ?></div><div class="val" dir="ltr"><?= (int) ($summary['min_days'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_max_days')); ?></div><div class="val" dir="ltr"><?= (int) ($summary['max_days'] ?? 0); ?></div></div>
                            <?php } elseif ($report === 'staff') { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_leads')); ?></div><div class="val"><?= (int) ($summary['lead_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_sales')); ?></div><div class="val"><?= (int) ($summary['sale_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_conversion_rate')); ?></div><div class="val" dir="ltr"><?= e(number_format((float) ($summary['conversion_rate'] ?? 0), 2)); ?>%</div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_avg_days')); ?></div><div class="val" dir="ltr"><?= e(number_format((float) ($summary['avg_days'] ?? 0), 1)); ?></div></div>
                            <?php } elseif ($report === 'aging') { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_open')); ?></div><div class="val"><?= (int) ($summary['open_count'] ?? $summary['lead_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_col_avg_days')); ?></div><div class="val" dir="ltr"><?= e(number_format((float) ($summary['avg_days'] ?? 0), 1)); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_aging_31')); ?></div><div class="val"><?= (int) ($summary['b31'] ?? 0); ?></div></div>
                            <?php } elseif ($report === 'response') { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_leads')); ?></div><div class="val"><?= (int) ($summary['lead_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_col_contacted')); ?></div><div class="val"><?= (int) ($summary['contacted_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_col_contacted_rate')); ?></div><div class="val" dir="ltr"><?= e(number_format((float) ($summary['contacted_rate'] ?? 0), 1)); ?>%</div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_col_avg_response_days')); ?></div><div class="val" dir="ltr"><?= e(number_format((float) ($summary['avg_days'] ?? 0), 1)); ?></div></div>
                            <?php } else { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_total_leads')); ?></div><div class="val"><?= (int) ($summary['lead_count'] ?? 0); ?></div></div>
                                <?php if (isset($summary['open_count'])) { ?>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_stack_open')); ?></div><div class="val"><?= (int) ($summary['open_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_stack_success')); ?></div><div class="val"><?= (int) ($summary['sale_count'] ?? 0); ?></div></div>
                                <div class="siba-sum-card"><div class="lbl"><?= e(_l('siba_leads_reports_stack_failed')); ?></div><div class="val"><?= (int) ($summary['fail_count'] ?? 0); ?></div></div>
                                <?php } ?>
                            <?php } ?>
                        </div>

                        <?php if (!empty($chart) && !empty($rows)) { ?>
                            <div id="siba-leads-report-chart" class="siba-report-chart-wrap siba-leads-report-chart"></div>
                            <?php if ($isSources || ($isFailures && $axis !== 'reason') || $report === 'aging' || $report === 'staff') { ?>
                                <div class="text-center tw-mt-3 siba-no-print siba-report-legend-actions">
                                    <button type="button" id="siba-chart-select-all" class="btn btn-success btn-sm">
                                        <?= e(_l('siba_leads_reports_select_all')); ?>
                                    </button>
                                </div>
                            <?php } ?>
                        <?php } elseif (empty($rows)) { ?>
                            <p class="text-center text-muted tw-py-8"><?= e(_l('siba_leads_reports_empty')); ?></p>
                        <?php } ?>

                        <?php if (!empty($rows)) { ?>
                            <div class="siba-report-table-wrap table-responsive tw-mt-4">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <?php if ($report === 'stage_time') { ?>
                                                <th><?= e(_l('siba_leads_reports_dim_status')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_stage_open_cards')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_avg_open_days')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_stage_completed_stays')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_avg_done_days')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_avg_days_in_column')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_min_days')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_max_days')); ?></th>
                                            <?php } elseif ($report === 'pipeline') { ?>
                                                <th><?= e(_l('siba_leads_reports_dim_status')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_leads')); ?></th>
                                            <?php } elseif ($report === 'aging') { ?>
                                                <th><?= e(_l('siba_leads_reports_dim_status')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_aging_0_2')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_aging_3_7')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_aging_8_14')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_aging_15_30')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_aging_31')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_leads')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_avg_days')); ?></th>
                                            <?php } else { ?>
                                            <?php if (in_array('date', $groupBy, true)) { ?><th><?= e(_l('siba_leads_reports_dim_date')); ?></th><?php } ?>
                                            <?php if (in_array('assigned', $groupBy, true)) { ?><th><?= e(_l('leads_dt_assigned')); ?></th><?php } ?>
                                            <?php if (in_array('status', $groupBy, true)) { ?><th><?= e(_l('siba_leads_reports_dim_status')); ?></th><?php } ?>
                                            <?php if (in_array('product', $groupBy, true)) { ?><th><?= e(_l('siba_leads_reports_dim_product')); ?></th><?php } ?>
                                            <?php if (in_array('location', $groupBy, true)) { ?><th><?= e(_l('siba_leads_reports_dim_location')); ?></th><?php } ?>
                                            <?php if (in_array('source', $groupBy, true)) { ?><th><?= e(_l('lead_source')); ?></th><?php } ?>
                                            <?php if ($isFailures) { ?>
                                                <th><?= e(_l('siba_leads_failure_reason')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_failures')); ?></th>
                                            <?php } elseif ($isSources) { ?>
                                                <th><?= e(_l('siba_leads_reports_col_leads')); ?></th>
                                            <?php } elseif ($report === 'conversion') { ?>
                                                <th><?= e(_l('siba_leads_reports_col_leads')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_sales')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_conversion_rate')); ?></th>
                                            <?php } elseif ($report === 'incoming') { ?>
                                                <th><?= e(_l('siba_leads_reports_stack_open')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_stack_success')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_stack_failed')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_leads')); ?></th>
                                            <?php } elseif ($report === 'speed') { ?>
                                                <th><?= e(_l('siba_leads_reports_col_sales')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_avg_days')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_min_days')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_max_days')); ?></th>
                                            <?php } elseif ($report === 'staff') { ?>
                                                <th><?= e(_l('siba_leads_reports_col_leads')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_open')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_sales')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_failures')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_conversion_rate')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_avg_days')); ?></th>
                                            <?php } elseif ($report === 'response') { ?>
                                                <th><?= e(_l('siba_leads_reports_col_leads')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_contacted')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_contacted_rate')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_avg_response_days')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_min_days')); ?></th>
                                                <th><?= e(_l('siba_leads_reports_col_max_days')); ?></th>
                                            <?php } else { ?>
                                                <th><?= e(_l('siba_leads_reports_col_leads')); ?></th>
                                            <?php } ?>
                                            <?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $row) { ?>
                                            <tr>
                                                <?php if ($report === 'stage_time') { ?>
                                                    <td>
                                                        <span class="label" style="background:<?= e($row['status_color'] ?? '#9ca3af'); ?>;color:#fff;">
                                                            <?= e($row['status_name'] ?? '—'); ?>
                                                        </span>
                                                    </td>
                                                    <td><?= (int) ($row['open_count'] ?? 0); ?></td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['avg_open_days'] ?? 0), 1)); ?></td>
                                                    <td><?= (int) ($row['done_count'] ?? 0); ?></td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['avg_done_days'] ?? 0), 1)); ?></td>
                                                    <td dir="ltr"><strong><?= e(number_format((float) ($row['avg_days'] ?? 0), 1)); ?></strong></td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['min_days'] ?? 0), 1)); ?></td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['max_days'] ?? 0), 1)); ?></td>
                                                <?php } elseif ($report === 'pipeline') { ?>
                                                    <td>
                                                        <?php if (!empty($row['is_lost_bucket'])) { ?>
                                                            <?= e(_l('lead_lost')); ?>
                                                        <?php } else { ?>
                                                            <span class="label" style="background:<?= e($row['dim_status_color'] ?? '#9ca3af'); ?>;color:#fff;">
                                                                <?= e($row['dim_status_label'] ?? '—'); ?>
                                                            </span>
                                                        <?php } ?>
                                                    </td>
                                                    <td><?= (int) ($row['lead_count'] ?? 0); ?></td>
                                                <?php } elseif ($report === 'aging') { ?>
                                                    <td>
                                                        <span class="label" style="background:<?= e($row['status_color'] ?? '#9ca3af'); ?>;color:#fff;">
                                                            <?= e($row['status_name'] ?? $row['dim_status_label'] ?? '—'); ?>
                                                        </span>
                                                    </td>
                                                    <td><?= (int) ($row['b0_2'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['b3_7'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['b8_14'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['b15_30'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['b31'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['lead_count'] ?? 0); ?></td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['avg_days'] ?? 0), 1)); ?></td>
                                                <?php } else { ?>
                                                <?php if (in_array('date', $groupBy, true)) { ?><td dir="ltr"><?= e($row['dim_date_label'] ?? ($row['dim_date'] ?? '—')); ?></td><?php } ?>
                                                <?php if (in_array('assigned', $groupBy, true)) { ?><td><?= e($row['dim_assigned_label'] ?? '—'); ?></td><?php } ?>
                                                <?php if (in_array('status', $groupBy, true)) { ?><td><?= e($row['dim_status_label'] ?? '—'); ?></td><?php } ?>
                                                <?php if (in_array('product', $groupBy, true)) { ?><td><?= e($row['dim_product_label'] ?? '—'); ?></td><?php } ?>
                                                <?php if (in_array('location', $groupBy, true)) { ?><td><?= e($row['dim_location_label'] ?? '—'); ?></td><?php } ?>
                                                <?php if (in_array('source', $groupBy, true)) { ?><td><?= e($row['dim_source_label'] ?? '—'); ?></td><?php } ?>
                                                <?php if ($isFailures) { ?>
                                                    <td><span class="label" style="background:<?= e($row['reason_color'] ?? '#9ca3af'); ?>;color:#fff;"><?= e($row['reason_title'] ?? '—'); ?></span></td>
                                                    <td><?= (int) ($row['fail_count'] ?? 0); ?></td>
                                                <?php } elseif ($isSources) { ?>
                                                    <td><?= (int) ($row['lead_count'] ?? 0); ?></td>
                                                <?php } elseif ($report === 'conversion') { ?>
                                                    <td><?= (int) ($row['lead_count'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['sale_count'] ?? 0); ?></td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['conversion_rate'] ?? 0), 2)); ?>%</td>
                                                <?php } elseif ($report === 'incoming') { ?>
                                                    <td><?= (int) ($row['open_count'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['sale_count'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['fail_count'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['lead_count'] ?? 0); ?></td>
                                                <?php } elseif ($report === 'speed') { ?>
                                                    <td><?= (int) ($row['sale_count'] ?? 0); ?></td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['avg_days'] ?? 0), 1)); ?></td>
                                                    <td dir="ltr"><?= (int) ($row['min_days'] ?? 0); ?></td>
                                                    <td dir="ltr"><?= (int) ($row['max_days'] ?? 0); ?></td>
                                                <?php } elseif ($report === 'staff') { ?>
                                                    <td><?= (int) ($row['lead_count'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['open_count'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['sale_count'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['fail_count'] ?? 0); ?></td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['conversion_rate'] ?? 0), 2)); ?>%</td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['avg_days'] ?? 0), 1)); ?></td>
                                                <?php } elseif ($report === 'response') { ?>
                                                    <td><?= (int) ($row['lead_count'] ?? 0); ?></td>
                                                    <td><?= (int) ($row['contacted_count'] ?? 0); ?></td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['contacted_rate'] ?? 0), 1)); ?>%</td>
                                                    <td dir="ltr"><?= e(number_format((float) ($row['avg_days'] ?? 0), 1)); ?></td>
                                                    <td dir="ltr"><?= (int) ($row['min_days'] ?? 0); ?></td>
                                                    <td dir="ltr"><?= (int) ($row['max_days'] ?? 0); ?></td>
                                                <?php } else { ?>
                                                    <td><?= (int) ($row['lead_count'] ?? 0); ?></td>
                                                <?php } ?>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
$(function () {
    if (typeof window.siba_leads_init_reports_jalali_filters === 'function') {
        window.siba_leads_init_reports_jalali_filters();
    } else if (typeof window.siba_leads_init_failed_jalali_filters === 'function') {
        window.siba_leads_init_failed_jalali_filters();
    }

    var chartCfg = <?= json_encode($chart ?? null, JSON_UNESCAPED_UNICODE); ?>;
    var chart = null;
    var $el = $('#siba-leads-report-chart');
    if (chartCfg && $el.length && typeof Highcharts !== 'undefined') {
        Highcharts.setOptions({
            lang: { numericSymbols: ['k', 'M', 'G', 'T', 'P', 'E'], thousandsSep: ',', decimalPoint: '.' },
            chart: { style: { fontFamily: 'Tahoma, Vazirmatn, sans-serif' } }
        });
        chart = Highcharts.chart('siba-leads-report-chart', chartCfg);
    }

    var selectAllLabel = <?= json_encode(_l('siba_leads_reports_select_all'), JSON_UNESCAPED_UNICODE); ?>;
    $('#siba-chart-select-all').on('click', function () {
        if (!chart || !chart.series) {
            return;
        }
        chart.series.forEach(function (s) {
            s.setVisible(true, false);
        });
        chart.redraw();
        $(this).text(selectAllLabel);
    });
});
</script>
<style>
.siba-report-legend-actions { margin-top: 10px; }
@media print {
    .siba-no-print, .siba-report-idenav, .siba-report-filters-bar { display: none !important; }
}
</style>
