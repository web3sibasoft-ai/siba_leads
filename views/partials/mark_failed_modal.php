<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade" id="siba-leads-mark-failed-modal" tabindex="-1" role="dialog" aria-labelledby="sibaLeadsMarkFailedLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?= e(_l('close')); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="sibaLeadsMarkFailedLabel">
                    <?= e(_l('siba_leads_mark_failed')); ?>
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?= e(_l('siba_leads_mark_failed_hint')); ?></p>
                <input type="hidden" id="siba-leads-fail-lead-id" value="">
                <div class="form-group">
                    <label for="siba-leads-fail-reason" class="control-label">
                        <?= e(_l('siba_leads_failure_reason')); ?>
                        <span class="text-danger">*</span>
                    </label>
                    <select id="siba-leads-fail-reason" class="form-control selectpicker" data-live-search="true" data-width="100%">
                        <option value=""><?= e(_l('dropdown_non_selected_tex')); ?></option>
                        <?php foreach (($reasons ?? []) as $reason) { ?>
                            <option value="<?= (int) $reason['id']; ?>"
                                    data-content="<span class='label' style='background:<?= e($reason['color'] ?? '#6b7280'); ?>;'>&nbsp;</span> <?= e($reason['title'] ?? ''); ?>">
                                <?= e($reason['title'] ?? ''); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="button" class="btn btn-danger" id="siba-leads-fail-submit">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <?= e(_l('siba_leads_mark_failed_confirm')); ?>
                </button>
            </div>
        </div>
    </div>
</div>
