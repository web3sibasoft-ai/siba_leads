<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
                    <div>
                        <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-1">
                            <?php echo e($title); ?>
                        </h4>
                        <p class="text-muted tw-mb-0">
                            <?php echo _l('siba_leads_failure_reasons_desc'); ?>
                        </p>
                    </div>
                    <a href="#" onclick="sibaLeadsFailureReasonModal(); return false;" class="btn btn-primary">
                        <i class="fa-regular fa-plus tw-ml-1"></i>
                        <?php echo _l('siba_leads_failure_reason_add'); ?>
                    </a>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table dt-table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('#'); ?></th>
                                        <th><?php echo _l('siba_leads_failure_reason_title'); ?></th>
                                        <th><?php echo _l('siba_leads_failure_reason_color'); ?></th>
                                        <th><?php echo _l('options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reasons)) { ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                <?php echo _l('siba_leads_failure_reasons_empty'); ?>
                                            </td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php foreach ($reasons as $reason) { ?>
                                            <tr>
                                                <td><?php echo (int) $reason['id']; ?></td>
                                                <td><?php echo e($reason['title']); ?></td>
                                                <td>
                                                    <span class="siba-leads-failure-color"
                                                          style="background:<?php echo e($reason['color']); ?>;"
                                                          title="<?php echo e($reason['color']); ?>"></span>
                                                    <code dir="ltr"><?php echo e($reason['color']); ?></code>
                                                </td>
                                                <td>
                                                    <a href="#"
                                                       onclick="sibaLeadsFailureReasonModal(<?php echo (int) $reason['id']; ?>); return false;"
                                                       class="btn btn-default btn-icon"
                                                       title="<?php echo _l('edit'); ?>">
                                                        <i class="fa-regular fa-pen-to-square"></i>
                                                    </a>
                                                    <a href="<?php echo admin_url('siba_leads/failure_reason_delete/' . (int) $reason['id']); ?>"
                                                       class="btn btn-danger btn-icon _delete"
                                                       title="<?php echo _l('delete'); ?>">
                                                        <i class="fa-regular fa-trash-can"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="siba-leads-failure-reason-modal-wrap"></div>

<style>
.siba-leads-failure-color {
    display: inline-block;
    width: 18px;
    height: 18px;
    border-radius: 4px;
    vertical-align: middle;
    margin-left: 8px;
    border: 1px solid rgba(0,0,0,.12);
}
</style>

<script>
function sibaLeadsFailureReasonModal(id) {
    id = id || '';
    $.ajax({
        type: 'post',
        url: admin_url + 'siba_leads/failure_reason_modal/' + id,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function (res) {
            var parsed = typeof res === 'string' ? JSON.parse(res) : res;
            if (!parsed || !parsed.view) {
                return;
            }
            $('#siba-leads-failure-reason-modal-wrap').html(parsed.view);
            $('#siba-leads-failure-reason-modal').modal('show');
            if (typeof init_color_pickers === 'function') {
                init_color_pickers();
            }
        }
    });
}
</script>
<?php init_tail(); ?>
