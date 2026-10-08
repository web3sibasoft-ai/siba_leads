<?php defined('BASEPATH') or exit('No direct script access allowed');
$items = is_array($items ?? null) ? $items : [];
$typeLabels = [
    'note'         => _l('siba_leads_timeline_filter_note'),
    'activity'     => _l('siba_leads_timeline_filter_activity'),
    'task'         => _l('siba_leads_timeline_filter_task'),
    'task_comment' => _l('siba_leads_timeline_filter_comment'),
    'task_report'  => _l('siba_leads_timeline_filter_report'),
];
?>
<div class="siba-lead-timeline__filters tw-mb-4 tw-flex tw-flex-wrap tw-gap-2">
    <button type="button" class="btn btn-default btn-xs siba-lead-timeline__filter is-active" data-filter="all">
        <?= e(_l('siba_leads_timeline_filter_all')); ?>
    </button>
    <?php foreach ($typeLabels as $key => $label) { ?>
        <button type="button" class="btn btn-default btn-xs siba-lead-timeline__filter" data-filter="<?= e($key); ?>">
            <?= e($label); ?>
        </button>
    <?php } ?>
</div>

<?php if (empty($items)) { ?>
    <div class="tw-text-center tw-text-neutral-500 tw-py-10">
        <i class="fa-regular fa-clock fa-2x tw-mb-2"></i>
        <div><?= e(_l('siba_leads_timeline_empty')); ?></div>
    </div>
<?php } else { ?>
    <div class="siba-lead-timeline__feed">
        <?php foreach ($items as $item) {
            $type = (string) ($item['type'] ?? 'activity');
            $staffId = (int) ($item['staff_id'] ?? 0);
            $date = (string) ($item['date'] ?? '');
            $url = (string) ($item['url'] ?? '');
            $badge = (string) ($item['badge'] ?? '');
            $badgeColor = (string) ($item['badge_color'] ?? '#64748b');
            $icon = (string) ($item['icon'] ?? 'fa-solid fa-circle');
            $dateContacted = (string) ($item['meta']['date_contacted'] ?? '');
            ?>
            <article class="siba-lead-timeline__item" data-type="<?= e($type); ?>">
                <div class="siba-lead-timeline__rail">
                    <span class="siba-lead-timeline__dot siba-lead-timeline__dot--<?= e($type); ?>">
                        <i class="<?= e($icon); ?>"></i>
                    </span>
                </div>
                <div class="siba-lead-timeline__card">
                    <header class="siba-lead-timeline__head">
                        <div class="siba-lead-timeline__head-main">
                            <?php if ($staffId > 0) { ?>
                                <a href="<?= admin_url('profile/' . $staffId); ?>" target="_blank" class="siba-lead-timeline__avatar">
                                    <?= staff_profile_image($staffId, ['staff-profile-xs-image']); ?>
                                </a>
                            <?php } ?>
                            <div>
                                <div class="siba-lead-timeline__title-row">
                                    <strong class="siba-lead-timeline__title"><?= e((string) ($item['title'] ?? '')); ?></strong>
                                    <?php if ($badge !== '') { ?>
                                        <span class="label siba-lead-timeline__badge"
                                              style="background:<?= e($badgeColor); ?>;">
                                            <?= e($badge); ?>
                                        </span>
                                    <?php } ?>
                                </div>
                                <?php if (!empty($item['staff_name'])) { ?>
                                    <div class="siba-lead-timeline__staff tw-text-neutral-500">
                                        <?= e((string) $item['staff_name']); ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="siba-lead-timeline__meta">
                            <?php if ($date !== '') { ?>
                                <span class="text-has-action" data-toggle="tooltip"
                                      data-title="<?= e(_dt($date)); ?>">
                                    <?= e(time_ago($date)); ?>
                                </span>
                                <div class="siba-lead-timeline__datetime tw-text-neutral-400">
                                    <?= e(function_exists('siba_leads_jalali_datetime')
                                        ? siba_leads_jalali_datetime($date)
                                        : _dt($date)); ?>
                                </div>
                            <?php } ?>
                        </div>
                    </header>

                    <?php if ($dateContacted !== '' && $dateContacted !== '0000-00-00 00:00:00') { ?>
                        <div class="siba-lead-timeline__contacted tw-text-success tw-mb-2">
                            <i class="fa fa-phone-square"></i>
                            <?= e(_l('lead_add_edit_datecontacted')); ?>:
                            <?= e(function_exists('siba_leads_jalali_datetime')
                                ? siba_leads_jalali_datetime($dateContacted)
                                : _dt($dateContacted)); ?>
                        </div>
                    <?php } ?>

                    <div class="siba-lead-timeline__content">
                        <?= $item['body'] ?? ''; ?>
                    </div>

                    <?php if ($url !== '') { ?>
                        <div class="siba-lead-timeline__actions tw-mt-2">
                            <a href="<?= e($url); ?>"
                               onclick="if (typeof init_task_modal === 'function') { init_task_modal(<?= (int) ($item['meta']['task_id'] ?? 0); ?>); return false; }"
                               class="btn btn-default btn-xs">
                                <i class="fa-regular fa-eye"></i>
                                <?= e(_l('view')); ?>
                            </a>
                        </div>
                    <?php } ?>
                </div>
            </article>
        <?php } ?>
    </div>
<?php } ?>
