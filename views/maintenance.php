<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="panel_s">
                    <div class="panel-body tw-text-center" style="padding: 40px 24px 48px;">
                        <h3 class="tw-mt-0 tw-mb-2 tw-font-bold">
                            <?= e(_l('siba_leads_maintenance_title')); ?>
                        </h3>
                        <p class="text-muted tw-mb-6" style="max-width: 560px; margin-left: auto; margin-right: auto;">
                            <?= e(_l('siba_leads_maintenance_desc')); ?>
                        </p>
                        <div class="siba-leads-maintenance-art">
                            <img
                                src="<?= e($image_url); ?>"
                                alt="<?= e(_l('siba_leads_maintenance_title')); ?>"
                                style="max-width: min(720px, 100%); width: 100%; height: auto; border-radius: 16px; box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
