<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-mb-4">
                    <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= e($title); ?></h4>
                    <p class="text-muted tw-mb-0 tw-mt-1"><?= e(_l('siba_leads_failed_desc')); ?></p>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <?= form_open(admin_url('siba_leads/failed'), ['method' => 'get', 'id' => 'siba-leads-failed-filters']); ?>
                        <div class="row">
                            <div class="col-md-3">
                                <?= render_select(
                                    'reason_id',
                                    $reasons ?? [],
                                    ['id', 'title'],
                                    'siba_leads_failure_reason',
                                    $filters['reason_id'] ?? '',
                                    [],
                                    [],
                                    '',
                                    '',
                                    false
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <?= render_select(
                                    'outcome_by',
                                    $staff ?? [],
                                    ['staffid', ['firstname', 'lastname']],
                                    'siba_leads_outcome_by',
                                    $filters['outcome_by'] ?? '',
                                    [],
                                    [],
                                    '',
                                    '',
                                    false
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <?= render_select(
                                    'assigned',
                                    $staff ?? [],
                                    ['staffid', ['firstname', 'lastname']],
                                    'leads_dt_assigned',
                                    $filters['assigned'] ?? '',
                                    [],
                                    [],
                                    '',
                                    '',
                                    false
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_from" class="control-label"><?= e(_l('siba_leads_failed_date_from')); ?></label>
                                    <div class="input-group date">
                                        <input type="text"
                                               id="date_from"
                                               name="date_from"
                                               class="form-control siba-jalali-datepicker"
                                               value="<?= e($filters['date_from'] ?? ''); ?>"
                                               autocomplete="off">
                                        <div class="input-group-addon">
                                            <i class="fa-regular fa-calendar calendar-icon"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_to" class="control-label"><?= e(_l('siba_leads_failed_date_to')); ?></label>
                                    <div class="input-group date">
                                        <input type="text"
                                               id="date_to"
                                               name="date_to"
                                               class="form-control siba-jalali-datepicker"
                                               value="<?= e($filters['date_to'] ?? ''); ?>"
                                               autocomplete="off">
                                        <div class="input-group-addon">
                                            <i class="fa-regular fa-calendar calendar-icon"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <?= render_input('q', 'search', $filters['q'] ?? '', 'search'); ?>
                            </div>
                            <div class="col-md-6 tw-flex tw-items-end tw-gap-2" style="padding-bottom:15px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-filter"></i> <?= _l('filter'); ?>
                                </button>
                                <a href="<?= admin_url('siba_leads/failed'); ?>" class="btn btn-default">
                                    <?= _l('clear'); ?>
                                </a>
                                <a href="<?= admin_url('siba_leads'); ?>" class="btn btn-default">
                                    <i class="fa-solid fa-table-columns"></i>
                                    <?= _l('siba_leads_kanban'); ?>
                                </a>
                            </div>
                        </div>
                        <?= form_close(); ?>

                        <hr />

                        <?php if (empty($leads)) { ?>
                            <p class="text-center text-muted tw-py-8 tw-mb-0">
                                <?= e(_l('siba_leads_failed_empty')); ?>
                            </p>
                        <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-siba-failed-leads" id="siba-failed-leads-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= _l('leads_dt_name'); ?></th>
                                        <th><?= _l('lead_add_edit_phonenumber'); ?></th>
                                        <th><?= _l('leads_dt_assigned'); ?></th>
                                        <th><?= _l('siba_leads_failure_reason'); ?></th>
                                        <th><?= _l('siba_leads_outcome_how'); ?></th>
                                        <th><?= _l('siba_leads_outcome_by'); ?></th>
                                        <th><?= _l('siba_leads_outcome_at'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leads as $row) { ?>
                                            <tr>
                                                <td>
                                                    <a href="<?= admin_url('leads/index/' . (int) $row['id']); ?>"
                                                       onclick="init_lead(<?= (int) $row['id']; ?>); return false;">
                                                        #<?= (int) $row['id']; ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="<?= admin_url('leads/index/' . (int) $row['id']); ?>"
                                                       onclick="init_lead(<?= (int) $row['id']; ?>); return false;">
                                                        <?= e($row['name'] ?? ''); ?>
                                                    </a>
                                                    <?php if (!empty($row['company'])) { ?>
                                                        <div class="text-muted"><?= e($row['company']); ?></div>
                                                    <?php } ?>
                                                </td>
                                                <td dir="ltr"><?= e($row['phonenumber'] ?? '—'); ?></td>
                                                <td>
                                                    <?php
                                                    $assignedId = (int) ($row['assigned'] ?? 0);
                                                    echo $assignedId > 0 ? e(get_staff_full_name($assignedId)) : e(_l('siba_leads_unassigned'));
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['reason_title'])) { ?>
                                                        <span class="label"
                                                              style="background:<?= e($row['reason_color'] ?: '#6b7280'); ?>;">
                                                            <?= e($row['reason_title']); ?>
                                                        </span>
                                                    <?php } else { ?>
                                                        —
                                                    <?php } ?>
                                                </td>
                                                <td><?= e(siba_leads_outcome_how_label($row['siba_outcome_how'] ?? '')); ?></td>
                                                <td>
                                                    <?php
                                                    $byId = (int) ($row['siba_outcome_by'] ?? 0);
                                                    echo $byId > 0 ? e(get_staff_full_name($byId)) : e(_l('system_default_string'));
                                                    ?>
                                                </td>
                                                <td dir="ltr">
                                                    <?php
                                                    $at = (string) ($row['siba_outcome_at'] ?? '');
                                                    $jalaliAt = function_exists('siba_leads_jalali_datetime')
                                                        ? siba_leads_jalali_datetime($at)
                                                        : '';
                                                    echo $jalaliAt !== '' ? e($jalaliAt) : '—';
                                                    ?>
                                                </td>
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
<script>
$(function () {
    if (typeof window.siba_leads_init_failed_jalali_filters === 'function') {
        window.siba_leads_init_failed_jalali_filters();
    }
    var $table = $('#siba-failed-leads-table');
    if ($table.length && $.fn.DataTable && !$.fn.DataTable.isDataTable($table)) {
        initDataTableInline($table);
    }
});
</script>
