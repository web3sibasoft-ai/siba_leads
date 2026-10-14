<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$isEdit = !empty($tag);
$name   = $isEdit ? ($tag['name'] ?? '') : '';
?>
<div class="modal fade" id="siba-leads-tag-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo _l('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <?php echo e($isEdit ? _l('siba_leads_tag_edit') : _l('siba_leads_tag_add')); ?>
                </h4>
            </div>
            <?php echo form_open(admin_url('siba_leads/tag_save')); ?>
            <?php if ($isEdit) { ?>
                <input type="hidden" name="id" value="<?php echo (int) $tag['id']; ?>">
            <?php } ?>
            <div class="modal-body">
                <?php echo render_input('name', 'siba_leads_tag_name', $name, 'text', ['required' => true, 'autocomplete' => 'off']); ?>
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
    $('#siba-leads-tag-modal').modal('show');
})(jQuery);
</script>
