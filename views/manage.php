<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4 class="no-margin"><?php echo e($title); ?></h4>
                <hr class="hr-panel-heading" />

                <p class="text-muted">
                    <?php echo _l('siba_leads_workspace_desc'); ?>
                </p>

                <div class="alert alert-info mtop15">
                    <strong><?php echo _l('siba_leads_repo'); ?>:</strong>
                    <a href="<?php echo e($repo_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($repo_url); ?></a>
                </div>

                <div class="siba-leads-placeholder">
                    <?php echo _l('siba_leads_placeholder'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
