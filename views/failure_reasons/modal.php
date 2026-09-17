<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$isEdit = !empty($reason);
$title  = $isEdit ? ($reason['title'] ?? '') : '';
$color  = $isEdit ? ($reason['color'] ?? '#6b7280') : '#6b7280';
if ($color === '') {
    $color = '#6b7280';
}
?>
<div class="modal fade" id="siba-leads-failure-reason-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo _l('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <?php echo e($isEdit ? _l('siba_leads_failure_reason_edit') : _l('siba_leads_failure_reason_add')); ?>
                </h4>
            </div>
            <?php echo form_open(admin_url('siba_leads/failure_reason_save')); ?>
            <?php if ($isEdit) { ?>
                <input type="hidden" name="id" value="<?php echo (int) $reason['id']; ?>">
            <?php } ?>
            <div class="modal-body">
                <?php echo render_input('title', 'siba_leads_failure_reason_title', $title, 'text', ['required' => true]); ?>
                <?php echo render_color_picker('color', _l('siba_leads_failure_reason_color'), $color); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>
<script>
(function ($) {
    var $modal = $('#siba-leads-failure-reason-modal');
    $modal.modal('show');
    if (typeof init_color_pickers === 'function') {
        init_color_pickers();
    }
})(jQuery);
</script>
