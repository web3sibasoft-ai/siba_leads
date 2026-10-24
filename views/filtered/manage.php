<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-mb-4">
                    <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= e($title); ?></h4>
                    <p class="text-muted tw-mb-0 tw-mt-1"><?= e(_l('siba_leads_filtered_desc')); ?></p>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <?= form_open(admin_url('siba_leads/filtered'), ['method' => 'get', 'id' => 'siba-leads-filtered-filters']); ?>
                        <input type="hidden" name="filter" value="1">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_from" class="control-label"><?= e(_l('siba_leads_filtered_date_from')); ?></label>
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
                                    <label for="date_to" class="control-label"><?= e(_l('siba_leads_filtered_date_to')); ?></label>
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
                                <?= render_select(
                                    'source',
                                    $sources ?? [],
                                    ['id', 'name'],
                                    'leads_source',
                                    !empty($filters['source']) ? (int) $filters['source'] : '',
                                    [
                                        'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                        'data-live-search'        => true,
                                    ],
                                    [],
                                    '',
                                    '',
                                    true
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <?= render_select(
                                    'job_group_id',
                                    $job_groups ?? [],
                                    ['siba_job_group_id', 'siba_job_group_name'],
                                    'siba_leads_job_group',
                                    !empty($filters['job_group_id']) ? (int) $filters['job_group_id'] : '',
                                    [
                                        'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                        'data-live-search'        => true,
                                    ],
                                    [],
                                    '',
                                    '',
                                    true
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <?= render_select(
                                    'province_id',
                                    $provinces ?? [],
                                    ['province_id', 'province_name'],
                                    'siba_leads_province',
                                    !empty($filters['province_id']) ? (int) $filters['province_id'] : '',
                                    [
                                        'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                        'data-live-search'        => true,
                                        'id'                      => 'siba_filtered_province_id',
                                    ],
                                    [],
                                    '',
                                    '',
                                    true
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <?= render_select(
                                    'city_id',
                                    $cities ?? [],
                                    ['city_id', 'city_name'],
                                    'siba_leads_city',
                                    !empty($filters['city_id']) ? (int) $filters['city_id'] : '',
                                    [
                                        'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                        'data-live-search'        => true,
                                        'id'                      => 'siba_filtered_city_id',
                                    ],
                                    [],
                                    '',
                                    '',
                                    true
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <?= render_select(
                                    'status',
                                    $statuses ?? [],
                                    ['id', 'name'],
                                    'leads_dt_status',
                                    !empty($filters['status']) ? (int) $filters['status'] : '',
                                    [
                                        'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                        'data-live-search'        => true,
                                    ],
                                    [],
                                    '',
                                    '',
                                    true
                                ); ?>
                            </div>
                            <?php if (!empty($can_view_all)) { ?>
                            <div class="col-md-3">
                                <?= render_select(
                                    'assigned',
                                    $staff ?? [],
                                    ['staffid', ['firstname', 'lastname']],
                                    'leads_dt_assigned',
                                    $filters['assigned'] !== null ? (int) $filters['assigned'] : '',
                                    [
                                        'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                        'data-live-search'        => true,
                                    ],
                                    [],
                                    '',
                                    '',
                                    true
                                ); ?>
                            </div>
                            <?php } ?>
                            <div class="col-md-3">
                                <?= render_select(
                                    'tags[]',
                                    $tags ?? [],
                                    ['id', 'name'],
                                    'tags',
                                    !empty($filters['tags']) ? $filters['tags'] : '',
                                    [
                                        'multiple'                => true,
                                        'data-actions-box'        => true,
                                        'data-width'              => '100%',
                                        'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                        'data-live-search'        => true,
                                    ],
                                    [],
                                    '',
                                    '',
                                    false
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <?= render_select(
                                    'outcome',
                                    $outcome_options ?? [],
                                    ['id', 'name'],
                                    'siba_leads_filtered_outcome',
                                    !empty($filters['outcome']) ? $filters['outcome'] : '',
                                    ['data-none-selected-text' => _l('dropdown_non_selected_tex')],
                                    [],
                                    '',
                                    '',
                                    true
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <?= render_input(
                                    'q',
                                    'search',
                                    $filters['q'] ?? '',
                                    'search'
                                ); ?>
                                <p class="text-muted tw-text-sm">
                                    <?= e(_l('siba_leads_filtered_search_hint')); ?>
                                </p>
                            </div>
                            <div class="col-md-6 tw-flex tw-items-end tw-gap-2" style="padding-top:25px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-filter"></i> <?= _l('filter'); ?>
                                </button>
                                <a href="<?= admin_url('siba_leads/filtered'); ?>" class="btn btn-default">
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

                        <?php if (empty($has_filter_request)) { ?>
                            <p class="text-center text-muted tw-py-8 tw-mb-0">
                                <?= e(_l('siba_leads_filtered_apply_hint')); ?>
                            </p>
                        <?php } elseif (empty($leads)) { ?>
                            <p class="text-center text-muted tw-py-8 tw-mb-0">
                                <?= e(_l('siba_leads_filtered_empty')); ?>
                            </p>
                        <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-siba-filtered-leads" id="siba-filtered-leads-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= _l('leads_dt_name'); ?></th>
                                        <th><?= _l('lead_add_edit_phonenumber'); ?></th>
                                        <th><?= _l('leads_source'); ?></th>
                                        <th><?= _l('siba_leads_job_group'); ?></th>
                                        <th><?= _l('siba_leads_province'); ?></th>
                                        <th><?= _l('siba_leads_city'); ?></th>
                                        <th><?= _l('leads_dt_status'); ?></th>
                                        <th><?= _l('leads_dt_assigned'); ?></th>
                                        <th><?= _l('tags'); ?></th>
                                        <th><?= _l('leads_dt_datecreated'); ?></th>
                                        <th><?= _l('siba_leads_filtered_outcome'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leads as $row) {
                                        $leadId   = (int) ($row['id'] ?? 0);
                                        $leadHref = admin_url('siba_leads?open_lead=' . $leadId);
                                        $outcome  = strtolower(trim((string) ($row['siba_outcome'] ?? '')));
                                        $isFailed = ((int) ($row['lost'] ?? 0) === 1) || $outcome === 'failed';
                                        $isSuccess = $outcome === 'success'
                                            || (!empty($row['date_converted']) && $row['date_converted'] > '1971-01-01 00:00:00');
                                        if ($isFailed) {
                                            $outcomeLabel = _l('siba_leads_outcome_failed');
                                        } elseif ($isSuccess) {
                                            $outcomeLabel = _l('siba_leads_outcome_success');
                                        } else {
                                            $outcomeLabel = _l('siba_leads_filtered_outcome_open');
                                        }
                                        $color = (string) ($row['status_color'] ?? '');
                                        ?>
                                    <tr>
                                        <td>
                                            <a href="<?= e($leadHref); ?>"
                                               onclick="init_lead(<?= $leadId; ?>); return false;">
                                                #<?= $leadId; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="<?= e($leadHref); ?>"
                                               onclick="init_lead(<?= $leadId; ?>); return false;">
                                                <?= e($row['name'] ?? ''); ?>
                                            </a>
                                            <?php if (!empty($row['company'])) { ?>
                                                <div class="text-muted"><?= e($row['company']); ?></div>
                                            <?php } ?>
                                        </td>
                                        <td dir="ltr"><?= e($row['phonenumber'] ?? '—'); ?></td>
                                        <td><?= e($row['source_name'] ?? '—'); ?></td>
                                        <td><?= e($row['job_group_name'] ?? '—'); ?></td>
                                        <td><?= e($row['province_name'] ?? ($row['state'] ?? '—')); ?></td>
                                        <td><?= e($row['city_name'] ?? ($row['city'] ?? '—')); ?></td>
                                        <td>
                                            <?php if (!empty($row['status_name'])) { ?>
                                                <span class="label<?= $color === '' ? ' label-default' : ''; ?>"
                                                      style="<?= $color !== ''
                                                          ? 'color:' . e($color) . ';border:1px solid ' . e(adjust_hex_brightness($color, 0.4))
                                                            . ';background:' . e(adjust_hex_brightness($color, 0.04)) . ';'
                                                          : ''; ?>">
                                                    <?= e($row['status_name']); ?>
                                                </span>
                                            <?php } else { ?>
                                                —
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php
                                            $assignedId = (int) ($row['assigned'] ?? 0);
                                            if ($assignedId > 0) {
                                                $fullName = trim(($row['assigned_firstname'] ?? '') . ' ' . ($row['assigned_lastname'] ?? ''));
                                                echo e($fullName !== '' ? $fullName : get_staff_full_name($assignedId));
                                            } else {
                                                echo e(_l('siba_leads_unassigned'));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?= !empty($row['tags']) && function_exists('render_tags')
                                                ? render_tags($row['tags'])
                                                : '—'; ?>
                                        </td>
                                        <td dir="ltr">
                                            <?php
                                            $added = (string) ($row['dateadded'] ?? '');
                                            $jalaliAdded = function_exists('siba_leads_jalali_datetime')
                                                ? siba_leads_jalali_datetime($added)
                                                : '';
                                            echo $jalaliAdded !== '' ? e($jalaliAdded) : '—';
                                            ?>
                                        </td>
                                        <td><?= e($outcomeLabel); ?></td>
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

    var $form = $('#siba-leads-filtered-filters');
    var $province = $form.find('#siba_filtered_province_id, select[name="province_id"]').first();
    var $city = $form.find('#siba_filtered_city_id, select[name="city_id"]').first();
    var citiesUrl = (window.sibaLeadsCitiesUrl || (admin_url + 'siba_leads/get_cities'));
    var selectedCity = <?= json_encode((string) ((int) ($filters['city_id'] ?? 0))); ?>;
    var loadingLabel = <?= json_encode(_l('siba_leads_city_loading')); ?>;

    function refreshCitySelect(cities, keepSelected) {
        if (!$city.length) {
            return;
        }
        var html = '<option value=""></option>';
        (cities || []).forEach(function (city) {
            var id = String(city.city_id || '');
            var name = city.city_name || '';
            var sel = (keepSelected && selectedCity && selectedCity === id) ? ' selected' : '';
            html += '<option value="' + id + '"' + sel + '>' + $('<div/>').text(name).html() + '</option>';
        });
        $city.html(html);
        if ($city.hasClass('selectpicker')) {
            $city.selectpicker('refresh');
        }
    }

    function loadCitiesForProvince(provinceId, keepSelected) {
        provinceId = parseInt(provinceId, 10) || 0;
        if (provinceId < 1) {
            refreshCitySelect([], false);
            return;
        }
        if ($city.hasClass('selectpicker')) {
            $city.html('<option value="">' + loadingLabel + '</option>').selectpicker('refresh');
        } else {
            $city.html('<option value="">' + loadingLabel + '</option>');
        }
        $.getJSON(citiesUrl, { province_id: provinceId })
            .done(function (cities) {
                refreshCitySelect(Array.isArray(cities) ? cities : [], !!keepSelected);
            })
            .fail(function () {
                refreshCitySelect([], false);
            });
    }

    $province.off('changed.bs.select.sibaFiltered change.sibaFiltered')
        .on('changed.bs.select.sibaFiltered change.sibaFiltered', function () {
            selectedCity = '';
            loadCitiesForProvince($(this).val(), false);
        });

    var $table = $('#siba-filtered-leads-table');
    if ($table.length && $.fn.DataTable && !$.fn.DataTable.isDataTable($table)) {
        initDataTableInline($table);
    }
});
</script>
