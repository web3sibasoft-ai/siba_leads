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
                        <?= _l('siba_leads_kanban_desc'); ?>
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
                            <?php if (is_admin() || get_option('allow_non_admin_members_to_import_leads') == '1') { ?>
                            <a href="<?= admin_url('siba_leads/import'); ?>" class="btn btn-default">
                                <i class="fa-solid fa-file-import"></i>
                                <?= _l('import_leads'); ?>
                            </a>
                            <?php } ?>
                            <a href="<?= admin_url('leads'); ?>" class="btn btn-default">
                                <i class="fa-solid fa-table-list"></i>
                                <?= _l('siba_leads_open_core_list'); ?>
                            </a>
                            <?php if (is_admin()) { ?>
                            <a href="<?= admin_url('siba_leads/teams'); ?>" class="btn btn-default">
                                <i class="fa-solid fa-users"></i>
                                <?= _l('siba_leads_teams'); ?>
                            </a>
                            <?php } ?>
                        </div>
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
                    </div>
                </div>

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
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function () {
    siba_leads_kanban();
});
</script>
