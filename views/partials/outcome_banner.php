<?php defined('BASEPATH') or exit('No direct script access allowed');
$banner = $banner ?? null;
if (empty($banner) || !is_array($banner)) {
    return;
}
$isSuccess = ($banner['outcome'] ?? '') === 'success';
$cls = $isSuccess ? 'alert-success' : 'alert-danger';
?>
<div class="alert <?= e($cls); ?> siba-leads-outcome-banner tw-mb-3" role="status">
    <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-justify-between">
        <strong>
            <?php if ($isSuccess) { ?>
                <i class="fa-solid fa-circle-check"></i>
            <?php } else { ?>
                <i class="fa-solid fa-circle-xmark"></i>
            <?php } ?>
            <?= e($banner['label'] ?? ''); ?>
        </strong>
        <?php if (!$isSuccess && !empty($banner['reason_title'])) { ?>
            <span class="label"
                  style="background:<?= e($banner['reason_color'] ?: '#6b7280'); ?>;">
                <?= e($banner['reason_title']); ?>
            </span>
        <?php } ?>
    </div>
    <div class="tw-mt-1 tw-text-sm">
        <?= e(_l('siba_leads_outcome_by')); ?>:
        <strong><?= e($banner['by_name'] ?? '—'); ?></strong>
        <?php if (!empty($banner['at']) && $banner['at'] !== '0000-00-00 00:00:00') { ?>
            — <?= e(_dt($banner['at'])); ?>
        <?php } ?>
        <?php if (!empty($banner['how_label'])) { ?>
            <span class="tw-mx-1">·</span>
            <?= e(_l('siba_leads_outcome_how')); ?>:
            <strong><?= e($banner['how_label']); ?></strong>
        <?php } ?>
    </div>
</div>
