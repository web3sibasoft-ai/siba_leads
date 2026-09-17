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
    'incoming'   => 'siba_leads_reports_incoming',
    'sources'    => 'siba_leads_reports_sources',
    'failures'   => 'siba_leads_reports_failures',
    'conversion' => 'siba_leads_reports_conversion',
    'pipeline'   => 'siba_leads_reports_pipeline',
    'speed'      => 'siba_leads_reports_speed',
    'sales'      => 'siba_leads_reports_sales',
    'analysis'   => 'siba_leads_reports_analysis',
];

$reportTitle = _l($tabLang[$report] ?? 'siba_leads_reports');

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
                                <?php if ($isFailures) { ?>
                                    <a href="<?= e($axisUrl('time')); ?>" class="siba-axis-tab <?= $axis === 'time' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_time')); ?></a>
                                    <a href="<?= e($axisUrl('reason')); ?>" class="siba-axis-tab <?= $axis === 'reason' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_reason')); ?></a>
                                    <a href="<?= e($axisUrl('users')); ?>" class="siba-axis-tab <?= $axis === 'users' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_users')); ?></a>
                                    <a href="<?= e($axisUrl('status')); ?>" class="siba-axis-tab <?= $axis === 'status' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_pipeline')); ?></a>
                                <?php } elseif ($report === 'sources') { ?>
                                    <a href="<?= e($axisUrl('time')); ?>" class="siba-axis-tab <?= $axis === 'time' ? 'is-active' : ''; ?>"><?= e(_l('siba_leads_reports_axis_time')); ?></a>
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

                        <div class="form-group" style="min-width:110px;">
                            <select name="date_bucket" id="date_bucket" class="selectpicker" data-width="100%">
                                <option value="day" <?= ($filters['date_bucket'] ?? '') === 'day' ? 'selected' : ''; ?>><?= e(_l('siba_leads_reports_bucket_day')); ?></option>
                                <option value="week" <?= ($filters['date_bucket'] ?? (($report === 'sources' || $report === 'failures') ? 'week' : 'day')) === 'week' ? 'selected' : ''; ?>><?= e(_l('siba_leads_reports_bucket_week')); ?></option>
                                <option value="month" <?= ($filters['date_bucket'] ?? '') === 'month' ? 'selected' : ''; ?>><?= e(_l('siba_leads_reports_bucket_month')); ?></option>
                                <option value="quarter" <?= ($filters['date_bucket'] ?? '') === 'quarter' ? 'selected' : ''; ?>><?= e(_l('siba_leads_reports_bucket_quarter')); ?></option>
                            </select>
                        </div>

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
                            <?php if ($isSources || ($isFailures && $axis !== 'reason')) { ?>
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
                                            <?php } else { ?>
                                                <th><?= e(_l('siba_leads_reports_col_leads')); ?></th>
                                            <?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $row) { ?>
                                            <tr>
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
                                                <?php } else { ?>
                                                    <td><?= (int) ($row['lead_count'] ?? 0); ?></td>
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
