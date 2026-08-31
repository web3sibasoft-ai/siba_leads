<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-1">
                    <?= e($title); ?>
                </h4>
                <p class="text-muted tw-mb-4">
                    <?= _l('siba_leads_teams_desc'); ?>
                </p>

                <div class="alert alert-info">
                    <?= _l('siba_leads_teams_howto'); ?>
                    <a href="<?= admin_url('siba_webkit/org'); ?>" class="alert-link">
                        <?= _l('siba_leads_org_link'); ?>
                    </a>
                    <?php if (!empty($roles)) { ?>
                        &nbsp;|&nbsp;
                        <a href="<?= admin_url('roles'); ?>" class="alert-link">
                            <?= _l('acs_roles'); ?>
                        </a>
                    <?php } ?>
                </div>

                <div class="alert alert-warning">
                    <?= _l('siba_leads_auto_assign_help'); ?>
                    <?php if ((int) $sales_member_count === 0) { ?>
                        <br /><strong><?= _l('siba_leads_auto_assign_no_sales'); ?></strong>
                    <?php } else { ?>
                        <br /><?= _l('siba_leads_auto_assign_sales_count', (int) $sales_member_count); ?>
                    <?php } ?>
                </div>

                <?= form_open(admin_url('siba_leads/teams')); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="checkbox checkbox-primary tw-mb-4">
                            <input type="checkbox"
                                name="siba_leads_auto_assign_sales"
                                id="siba_leads_auto_assign_sales"
                                value="1"
                                <?= !empty($auto_assign_sales) ? 'checked' : ''; ?>>
                            <label for="siba_leads_auto_assign_sales">
                                <?= _l('siba_leads_auto_assign_sales'); ?>
                            </label>
                        </div>

                        <?php foreach ($teams as $team) { ?>
                        <div class="siba-team-card tw-mb-5 tw-p-4 tw-rounded-xl tw-border tw-border-neutral-200">
                            <div class="row">
                                <div class="col-md-5">
                                    <h5 class="tw-mt-0 tw-font-semibold">
                                        <?= e($team['label']); ?>
                                        <small class="text-muted">(<?= e($team['key']); ?>)</small>
                                        <?php if ($team['source'] === 'org') { ?>
                                            <span class="label label-info"><?= _l('siba_leads_team_source_org'); ?></span>
                                        <?php } elseif ($team['source'] === 'role') { ?>
                                            <span class="label label-default"><?= _l('siba_leads_team_source_role'); ?></span>
                                        <?php } ?>
                                    </h5>
                                    <?php
                                    echo render_select(
                                        'org_units[' . $team['key'] . ']',
                                        $org_units,
                                        ['id', 'name'],
                                        'siba_leads_team_org_unit',
                                        $team['org_unit_id'],
                                        ['data-none-selected-text' => _l('dropdown_non_selected_tex')]
                                    );
                                    echo render_select(
                                        'teams[' . $team['key'] . ']',
                                        $roles,
                                        ['roleid', 'name'],
                                        'siba_leads_team_role_fallback',
                                        $team['role_id'],
                                        ['data-none-selected-text' => _l('dropdown_non_selected_tex')]
                                    );
                                    ?>
                                </div>
                                <div class="col-md-7">
                                    <label class="control-label tw-mb-2">
                                        <?= _l('siba_leads_team_members'); ?>
                                        <span class="badge"><?= count($team['members']); ?></span>
                                    </label>
                                    <?php if (empty($team['members'])) { ?>
                                    <p class="text-muted tw-mb-0">
                                        <?= _l('siba_leads_team_no_members'); ?>
                                    </p>
                                    <?php } else { ?>
                                    <div class="tw-flex tw-flex-wrap tw-gap-2">
                                        <?php foreach ($team['members'] as $member) {
                                            $name = trim(($member['firstname'] ?? '') . ' ' . ($member['lastname'] ?? ''));
                                            if ($name === '') {
                                                $name = $member['full_name'] ?? ('#' . $member['staffid']);
                                            }
                                            ?>
                                        <span class="label label-default tw-inline-flex tw-items-center tw-gap-1"
                                            style="border-radius:999px;padding:6px 10px;font-weight:600;">
                                            <?= staff_profile_image($member['staffid'], ['staff-profile-image-small'], 'small'); ?>
                                            <?= e($name); ?>
                                        </span>
                                        <?php } ?>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary">
                            <?= _l('submit'); ?>
                        </button>
                    </div>
                </div>
                <?= form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
