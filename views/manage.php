<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-mb-4">
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

                <div class="_buttons tw-mb-3">
                    <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-2">
                        <div class="tw-flex tw-items-center tw-gap-2">
                            <?php if (staff_can('create', 'leads') || is_admin()) { ?>
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
                            <a href="<?= admin_url('siba_leads/failed'); ?>" class="btn btn-default">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <?= _l('siba_leads_failed'); ?>
                            </a>
                            <?php if (is_admin()) { ?>
                            <a href="<?= admin_url('siba_leads/teams'); ?>" class="btn btn-default">
                                <i class="fa-solid fa-users"></i>
                                <?= _l('siba_leads_teams'); ?>
                            </a>
                            <?php } ?>
                        </div>
                        <?php if ($isKanBan) { ?>
                        <div class="leads-search" style="min-width: 240px;">
                            <div data-toggle="tooltip" data-placement="top"
                                data-title="<?= _l('search_by_tags'); ?>">
                                <?= render_input('search', '', '', 'search', [
                                    'data-name'   => 'search',
                                    'onkeyup'     => 'siba_leads_kanban();',
                                    'placeholder' => _l('leads_search'),
                                ], [], 'no-margin'); ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($isKanBan) { ?>
                <?= form_hidden('sort_type', $sort_by); ?>
                <?= form_hidden('sort', $sort); ?>

                <div class="active kan-ban-tab" id="kan-ban-tab" style="overflow:auto;">
                    <div class="kanban-leads-sort tw-mb-3">
                        <span class="bold"><?= _l('leads_sort_by'); ?>:</span>
                        <a href="#" onclick="siba_leads_kanban_sort('dateadded'); return false;" class="dateadded">
                            <?php if ($sort_by === 'dateadded') { ?>
                            <i class="kanban-sort-icon fa fa-sort-amount-<?= strtolower($sort); ?>"></i>
                            <?php } ?>
                            <?= _l('leads_sort_by_datecreated'); ?>
                        </a>
                        |
                        <a href="#" onclick="siba_leads_kanban_sort('leadorder'); return false;" class="leadorder">
                            <?php if ($sort_by === 'leadorder') { ?>
                            <i class="kanban-sort-icon fa fa-sort-amount-<?= strtolower($sort); ?>"></i>
                            <?php } ?>
                            <?= _l('leads_sort_by_kanban_order'); ?>
                        </a>
                        |
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
                            <div id="kan-ban"></div>
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
    siba_leads_kanban();
<?php } else { ?>
    initDataTable('.table-siba-leads', admin_url + 'siba_leads/table', undefined, undefined, undefined, [0, 'desc']);
<?php } ?>
});
</script>
