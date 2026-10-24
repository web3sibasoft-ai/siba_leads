<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12 siba-leads-workspace">
                <section class="siba-leads-hero">
                <div class="siba-leads-heading">
                    <span class="siba-leads-heading__icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
                    <div class="siba-leads-heading__text">
                    <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                        <?= e($title); ?>
                    </h4>
                    <p class="text-muted tw-mb-0 tw-mt-1">
                        <?= $isKanBan ? _l('siba_leads_kanban_desc') : _l('siba_leads_list_desc'); ?>
                        <?php if (empty($can_view_all)) { ?>
                            <span class="tw-block tw-mt-1"><?= _l('siba_leads_viewing_own_only'); ?></span>
                        <?php } ?>
                    </p>
                </div>

                    </div>
                <div class="_buttons siba-leads-toolbar">
                    <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-2">
                        <div class="tw-flex tw-items-center tw-gap-2">
                            <?php if (function_exists('siba_leads_can_create') ? siba_leads_can_create() : (is_admin() || staff_can('create', 'siba_leads'))) { ?>
                            <a href="#" onclick="init_lead(); return false;" class="btn btn-primary">
                                <i class="fa-regular fa-plus"></i>
                                <?= _l('new_lead'); ?>
                            </a>
                            <?php } ?>
                            <a href="<?= admin_url('siba_leads/switch_kanban/' . (int) $switch_kanban); ?>"
                                class="btn btn-default !tw-px-3"
                                data-toggle="tooltip"
                                data-placement="top"
                                data-title="<?= $isKanBan ? _l('switch_to_list_view') : _l('leads_switch_to_kanban'); ?>">
                                <?php if ($isKanBan) { ?>
                                <i class="fa-solid fa-table-list"></i>
                                <?php } else { ?>
                                <i class="fa-solid fa-grip-vertical"></i>
                                <?php } ?>
                            </a>
                            <a href="<?= admin_url('siba_leads/filtered'); ?>" class="btn btn-default">
                                <i class="fa-solid fa-filter"></i>
                                <?= _l('siba_leads_filtered'); ?>
                            </a>
                            <a href="<?= admin_url('siba_leads/failed'); ?>" class="btn btn-default">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <?= _l('siba_leads_failed'); ?>
                            </a>
                            <?php if (is_admin()) { ?>
                            <a href="<?= admin_url('siba_leads/teams'); ?>" class="btn btn-default">
                                <i class="fa-solid fa-users"></i>
                                <?= _l('siba_leads_teams'); ?>
                            </a>
                            <a href="<?= admin_url('siba_leads/tags'); ?>" class="btn btn-default">
                                <i class="fa-solid fa-tags"></i>
                                <?= _l('siba_leads_tags'); ?>
                            </a>
                            <?php } ?>
                        </div>
                        <?php if ($isKanBan) { ?>
                        <div class="leads-search siba-leads-search">
                            <div data-toggle="tooltip" data-placement="top"
                                data-title="<?= _l('search_by_tags'); ?>">
                                <?= render_input('search', '', '', 'search', [
                                    'data-name'   => 'search',
                                    'onkeyup'     => 'siba_leads_kanban_search();',
                                    'placeholder' => _l('leads_search'),
                                ], [], 'no-margin'); ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="siba-leads-filters">
                    <div class="siba-leads-filters__body">
                        <?php
                        $pipelines = isset($pipelines) && is_array($pipelines) ? $pipelines : [];
                        $activePipelineId = isset($active_pipeline_id) ? (string) $active_pipeline_id : '';
                        $activePipelineTagId = isset($active_pipeline_tag_id) ? (int) $active_pipeline_tag_id : 0;
                        $staffOptions = isset($staff) && is_array($staff) ? $staff : [];
                        $showAssigneeFilter = !empty($can_view_all) && $staffOptions !== [];
                        $filterGridClass = 'row siba-leads-filter-grid';
                        if ($pipelines !== []) {
                            $filterGridClass .= ' has-pipelines';
                        }
                        if ($showAssigneeFilter) {
                            $filterGridClass .= ' has-assignee';
                        }
                        ?>
                        <div class="<?= e($filterGridClass); ?>">
                            <?php if ($pipelines !== []) { ?>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="siba_leads_pipeline_board" class="control-label">
                                        <?= _l('siba_leads_pipeline_board'); ?>
                                    </label>
                                    <select id="siba_leads_pipeline_board"
                                            class="selectpicker"
                                            data-width="100%"
                                            data-none-selected-text="<?= e(_l('siba_leads_pipeline_board_select')); ?>"
                                            onchange="if (this.value) { window.location = '<?= admin_url('siba_leads/switch_pipeline/'); ?>' + encodeURIComponent(this.value); }">
                                        <?php foreach ($pipelines as $pipeline) {
                                            $pid = trim((string) ($pipeline['didar_pipeline_id'] ?? ''));
                                            if ($pid === '') {
                                                continue;
                                            }
                                            $label = trim((string) ($pipeline['title'] ?? $pipeline['tag_name'] ?? $pid));
                                            ?>
                                        <option value="<?= e($pid); ?>" <?= $pid === $activePipelineId ? 'selected' : ''; ?>>
                                            <?= e($label); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                    <p class="text-muted tw-text-sm tw-mb-0 tw-mt-1">
                                        <?= _l('siba_leads_pipeline_board_help'); ?>
                                    </p>
                                </div>
                            </div>
                            <?php } ?>
                            <div class="col-md-5">
                                <?php
                                $tagOptions = isset($tags) && is_array($tags) ? $tags : [];
                                $selectedTags = $activePipelineTagId > 0 ? [$activePipelineTagId] : '';
                                echo render_select(
                                    'siba_leads_tags[]',
                                    $tagOptions,
                                    ['id', 'name'],
                                    'siba_leads_filter_tags',
                                    $selectedTags,
                                    [
                                        'multiple'              => true,
                                        'data-actions-box'      => true,
                                        'data-width'            => '100%',
                                        'data-none-selected-text' => _l('siba_leads_filter_all_tags'),
                                        'onchange'              => $isKanBan
                                            ? 'siba_leads_kanban_filter_changed();'
                                            : 'siba_leads_list_filter_changed();',
                                    ],
                                    [],
                                    '',
                                    '',
                                    false
                                );
                                ?>
                            </div>
                            <?php if ($showAssigneeFilter) { ?>
                            <div class="col-md-4">
                                <?php
                                echo render_select(
                                    'siba_leads_assigned',
                                    $staffOptions,
                                    ['staffid', ['firstname', 'lastname']],
                                    'siba_leads_filter_assigned',
                                    '',
                                    [
                                        'data-width'              => '100%',
                                        'data-live-search'        => true,
                                        'data-none-selected-text' => _l('siba_leads_filter_all_assignees'),
                                        'onchange'                => $isKanBan
                                            ? 'siba_leads_kanban_filter_changed();'
                                            : 'siba_leads_list_filter_changed();',
                                    ],
                                    [],
                                    '',
                                    '',
                                    true
                                );
                                ?>
                            </div>
                            <?php } ?>
                            <div class="col-md-3">
                                <div class="checkbox checkbox-primary siba-leads-untagged">
                                    <input type="checkbox"
                                           id="siba_leads_tag_none"
                                           name="siba_leads_tag_none"
                                           value="1"
                                           onchange="<?= $isKanBan ? 'siba_leads_kanban_filter_changed();' : 'siba_leads_list_filter_changed();'; ?>">
                                    <label for="siba_leads_tag_none"><?= _l('siba_leads_filter_untagged'); ?></label>
                                </div>
                            </div>
                        </div>
                        <?php if ($isKanBan) { ?>
                        <div class="siba-leads-urgency" role="group" aria-label="<?= e(_l('siba_leads_filter_urgency')); ?>">
                            <span class="siba-leads-urgency__label"><?= _l('siba_leads_filter_urgency'); ?></span>
                            <div class="siba-leads-urgency__chips">
                                <button type="button"
                                        class="siba-leads-urgency__chip is-active"
                                        data-urgency=""
                                        onclick="siba_leads_urgency_filter(''); return false;">
                                    <?= _l('siba_leads_filter_urgency_all'); ?>
                                </button>
                                <button type="button"
                                        class="siba-leads-urgency__chip siba-leads-urgency__chip--due"
                                        data-urgency="due"
                                        onclick="siba_leads_urgency_filter('due'); return false;">
                                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                                    <?= _l('siba_leads_filter_urgency_due'); ?>
                                </button>
                                <button type="button"
                                        class="siba-leads-urgency__chip siba-leads-urgency__chip--task"
                                        data-urgency="task_due"
                                        onclick="siba_leads_urgency_filter('task_due'); return false;">
                                    <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                                    <?= _l('siba_leads_filter_urgency_task_due'); ?>
                                </button>
                                <button type="button"
                                        class="siba-leads-urgency__chip siba-leads-urgency__chip--reminder"
                                        data-urgency="reminder_due"
                                        onclick="siba_leads_urgency_filter('reminder_due'); return false;">
                                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                    <?= _l('siba_leads_filter_urgency_reminder_due'); ?>
                                </button>
                                <button type="button"
                                        class="siba-leads-urgency__chip siba-leads-urgency__chip--overdue"
                                        data-urgency="overdue"
                                        onclick="siba_leads_urgency_filter('overdue'); return false;">
                                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                    <?= _l('siba_leads_filter_urgency_overdue'); ?>
                                </button>
                                <button type="button"
                                        class="siba-leads-urgency__chip siba-leads-urgency__chip--today"
                                        data-urgency="today"
                                        onclick="siba_leads_urgency_filter('today'); return false;">
                                    <i class="fa-solid fa-sun" aria-hidden="true"></i>
                                    <?= _l('siba_leads_filter_urgency_today'); ?>
                                </button>
                                <button type="button"
                                        class="siba-leads-urgency__chip siba-leads-urgency__chip--mine"
                                        data-urgency="my_tasks"
                                        onclick="siba_leads_urgency_filter('my_tasks'); return false;">
                                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                                    <?= _l('siba_leads_filter_urgency_my_tasks'); ?>
                                </button>
                            </div>
                            <div id="kanban-params" class="hide">
                                <input type="hidden" name="urgency" id="siba_leads_urgency" value="">
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                </section>

                <?php if ($isKanBan) { ?>
                <?= form_hidden('sort_type', $sort_by); ?>
                <?= form_hidden('sort', $sort); ?>

                <div class="active kan-ban-tab" id="kan-ban-tab" style="overflow:auto;">
                    <div class="kanban-leads-sort siba-leads-sort">
                        <span class="bold"><?= _l('leads_sort_by'); ?>:</span>
                        <a href="#" onclick="siba_leads_kanban_sort('dateadded'); return false;" class="dateadded">
                            <?php if ($sort_by === 'dateadded') { ?>
                            <i class="kanban-sort-icon fa fa-sort-amount-<?= strtolower($sort); ?>"></i>
                            <?php } ?>
                            <?= _l('leads_sort_by_datecreated'); ?>
                        </a>
                        <a href="#" onclick="siba_leads_kanban_sort('leadorder'); return false;" class="leadorder">
                            <?php if ($sort_by === 'leadorder') { ?>
                            <i class="kanban-sort-icon fa fa-sort-amount-<?= strtolower($sort); ?>"></i>
                            <?php } ?>
                            <?= _l('leads_sort_by_kanban_order'); ?>
                        </a>
                        <a href="#" onclick="siba_leads_kanban_sort('lastcontact'); return false;" class="lastcontact">
                            <?php if ($sort_by === 'lastcontact') { ?>
                            <i class="kanban-sort-icon fa fa-sort-amount-<?= strtolower($sort); ?>"></i>
                            <?php } ?>
                            <?= _l('leads_sort_by_lastcontact'); ?>
                        </a>
                    </div>

                    <div class="row">
                        <div class="container-fluid leads-kan-ban siba-leads-kan-ban">
                            <div class="kan-ban-top"></div>
                            <div id="kan-ban"><?= $initial_board_html ?? ''; ?></div>
                        </div>
                    </div>
                </div>
                <?php } else { ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="panel-table-full">
                            <?php
                            render_datatable([
                                [
                                    'name'     => _l('the_number_sign'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-number'],
                                ],
                                [
                                    'name'     => _l('leads_dt_name'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-name'],
                                ],
                                [
                                    'name'     => _l('lead_company'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-company'],
                                ],
                                [
                                    'name'     => _l('leads_dt_phonenumber'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-phone'],
                                ],
                                [
                                    'name'     => _l('leads_dt_email'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-email'],
                                ],
                                [
                                    'name'     => _l('leads_dt_assigned'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-assigned'],
                                ],
                                [
                                    'name'     => _l('leads_dt_status'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-status'],
                                ],
                                [
                                    'name'     => _l('leads_source'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-source'],
                                ],
                                [
                                    'name'     => _l('tags'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-tags'],
                                ],
                                [
                                    'name'     => _l('leads_dt_last_contact'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-siba-last-contact'],
                                ],
                                [
                                    'name'     => _l('leads_dt_datecreated'),
                                    'th_attrs' => ['class' => 'toggleable date-created', 'id' => 'th-siba-date-created'],
                                ],
                            ], 'siba-leads', ['number-index-1'], [
                                'id'                         => 'siba-leads',
                                'data-last-order-identifier' => 'siba-leads',
                                'data-default-order'         => get_table_last_order('siba-leads'),
                            ]);
                            ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function () {
<?php if ($isKanBan) { ?>
    siba_leads_init_loaded_board();
<?php } else { ?>
    var SibaLeadsServerParams = {};
    SibaLeadsServerParams['siba_leads_tags'] = '[name="siba_leads_tags[]"]';
    SibaLeadsServerParams['siba_leads_tag_none'] = '[name="siba_leads_tag_none"]:checked';
    SibaLeadsServerParams['siba_leads_assigned'] = '[name="siba_leads_assigned"]';
    // Tags column (index 8) is not useful for search/sort — keep list snappy.
    initDataTable('.table-siba-leads', admin_url + 'siba_leads/table', [8], [8], SibaLeadsServerParams, [0, 'desc']);
<?php } ?>
});
</script>
