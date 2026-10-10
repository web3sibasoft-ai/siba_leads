<?php defined('BASEPATH') or exit('No direct script access allowed');

$company_label = trim((string) ($lead['company'] ?? ''));
$title_label   = trim((string) ($lead['title'] ?? ''));
?>
<dl class="siba-lead-card__dl">
    <div>
        <dt><?= _l('lead_title'); ?></dt>
        <dd><?= e($title_label !== '' ? $title_label : '—'); ?></dd>
    </div>
    <div>
        <dt><?= _l('lead_add_edit_email'); ?></dt>
        <dd>
            <?= !empty($lead['email'])
                ? '<a href="mailto:' . e($lead['email']) . '">' . e($lead['email']) . '</a>'
                : '—'; ?>
        </dd>
    </div>
    <div>
        <dt><?= _l('lead_add_edit_phonenumber'); ?></dt>
        <dd>
            <?= !empty($lead['phonenumber'])
                ? '<a href="tel:' . e($lead['phonenumber']) . '" dir="ltr">' . e($lead['phonenumber']) . '</a>'
                : '—'; ?>
        </dd>
    </div>
    <div>
        <dt><?= _l('lead_website'); ?></dt>
        <dd>
            <?= !empty($lead['website'])
                ? '<a href="' . e(maybe_add_http($lead['website'])) . '" target="_blank" rel="noopener">' . e($lead['website']) . '</a>'
                : '—'; ?>
        </dd>
    </div>
    <div>
        <dt><?= _l('lead_company'); ?></dt>
        <dd><?= e($company_label !== '' ? $company_label : '—'); ?></dd>
    </div>
    <div>
        <dt><?= _l('lead_address'); ?></dt>
        <dd><?= e(($lead['address'] ?? '') != '' ? $lead['address'] : '—'); ?></dd>
    </div>
    <div>
        <?php
        $leadLocation = function_exists('siba_leads_resolve_lead_location')
            ? siba_leads_resolve_lead_location($lead)
            : [
                'province_name' => $lead['state'] ?? '',
                'city_name'     => $lead['city'] ?? '',
            ];
        ?>
        <dt><?= _l('siba_leads_province'); ?></dt>
        <dd><?= e(($leadLocation['province_name'] ?? '') !== '' ? $leadLocation['province_name'] : '—'); ?></dd>
    </div>
    <div>
        <dt><?= _l('siba_leads_city'); ?></dt>
        <dd><?= e(($leadLocation['city_name'] ?? '') !== '' ? $leadLocation['city_name'] : '—'); ?></dd>
    </div>
    <div>
        <dt><?= _l('lead_country'); ?></dt>
        <dd><?= e(!empty($lead['country']) && (int) $lead['country'] !== 0 ? get_country($lead['country'])->short_name : '—'); ?></dd>
    </div>
    <div>
        <dt><?= _l('lead_zip'); ?></dt>
        <dd><?= e(($lead['zip'] ?? '') != '' ? $lead['zip'] : '—'); ?></dd>
    </div>
</dl>
<?php
$formId = trim((string) ($lead['form_identifier'] ?? ''));
$formTitle = trim((string) ($lead['form_source_page_title'] ?? ''));
$formLink = trim((string) ($lead['form_source_link'] ?? ''));
$smsRawExpand = (string) ($lead['sms_verification'] ?? '');
$smsVerifiedExpand = function_exists('siba_leads_is_sms_verified')
    ? siba_leads_is_sms_verified($smsRawExpand)
    : in_array($smsRawExpand, ['1', 'true', 'True'], true);
$trackingExpand = trim((string) ($lead['demo_request_tracking_id'] ?? ''));
$packageExpand = trim((string) ($lead['user_package_number'] ?? ''));
$requestExpand = trim((string) ($lead['request_type'] ?? ''));
$descriptionMap = function_exists('siba_leads_parse_description_map')
    ? siba_leads_parse_description_map($lead['description'] ?? '')
    : [];
$descriptionMap = function_exists('siba_leads_unique_form_detail_rows')
    ? siba_leads_unique_form_detail_rows($descriptionMap, [
        'form_identifier'          => $formId,
        'form_source_link'         => $formLink,
        'form_source_page_title'   => $formTitle,
        'demo_request_tracking_id' => $trackingExpand,
        'user_package_number'      => $packageExpand,
        'request_type'             => $requestExpand,
    ])
    : $descriptionMap;
$leadMetaRows = function_exists('siba_leads_get_lead_meta')
    ? siba_leads_get_lead_meta((int) $lead['id'])
    : [];
$hasWebsiteBlock = $formId !== '' || $formTitle !== '' || $formLink !== ''
    || $smsRawExpand !== '' || $trackingExpand !== '' || $packageExpand !== ''
    || $requestExpand !== '' || $descriptionMap !== [] || $leadMetaRows !== [];
if ($hasWebsiteBlock) { ?>
<div class="siba-lead-card__form-block">
    <div class="siba-lead-card__form-title"><?= _l('siba_leads_website_form_info'); ?></div>
    <dl class="siba-lead-card__dl siba-lead-card__dl--form">
    <?php if ($formId !== '') { ?>
    <div>
        <dt><?= _l('form_identifier'); ?></dt>
        <dd><?= e($formId); ?></dd>
    </div>
    <?php }
    if ($trackingExpand !== '') { ?>
    <div>
        <dt><?= _l('demo_request_tracking_id'); ?></dt>
        <dd><?= e($trackingExpand); ?></dd>
    </div>
    <?php }
    if ($formTitle !== '') { ?>
    <div>
        <dt><?= _l('form_source_page_title'); ?></dt>
        <dd><?= e($formTitle); ?></dd>
    </div>
    <?php }
    if ($formLink !== '') { ?>
    <div>
        <dt><?= _l('form_source_link'); ?></dt>
        <dd>
            <a href="<?= e($formLink); ?>" target="_blank" rel="noopener" dir="ltr"><?= e($formLink); ?></a>
        </dd>
    </div>
    <?php }
    if ($smsRawExpand !== '') { ?>
    <div>
        <dt><?= _l('sms_verification'); ?></dt>
        <dd>
            <span class="siba-lead-card__chip siba-lead-card__chip--sms<?= $smsVerifiedExpand ? ' is-verified' : ' is-pending'; ?>">
                <?= $smsVerifiedExpand ? _l('siba_leads_sms_verified') : _l('siba_leads_sms_unverified'); ?>
            </span>
        </dd>
    </div>
    <?php }
    if ($packageExpand !== '') { ?>
    <div>
        <dt><?= _l('user_package_number'); ?></dt>
        <dd><?= e($packageExpand); ?></dd>
    </div>
    <?php }
    if ($requestExpand !== '') { ?>
    <div>
        <dt><?= _l('request_type'); ?></dt>
        <dd><?= e($requestExpand); ?></dd>
    </div>
    <?php }
    foreach ($descriptionMap as $metaKey => $metaVal) { ?>
    <div>
        <dt><?= e((string) $metaKey); ?></dt>
        <dd><?php
            $metaVal = (string) $metaVal;
            if (preg_match('#^https?://#i', $metaVal)) {
                echo '<a href="' . e($metaVal) . '" target="_blank" rel="noopener" dir="ltr">' . e($metaVal) . '</a>';
            } else {
                echo e($metaVal);
            }
        ?></dd>
    </div>
    <?php }
    $shownDescKeys = [];
    foreach (array_keys($descriptionMap) as $shownKey) {
        $shownDescKeys[mb_strtolower((string) $shownKey)] = true;
    }
    foreach ($leadMetaRows as $metaRow) {
        $metaKey = trim((string) ($metaRow['meta_key'] ?? ''));
        $metaVal = trim((string) ($metaRow['meta_value'] ?? ''));
        if ($metaKey === '' || $metaVal === '') {
            continue;
        }
        if (isset($shownDescKeys[mb_strtolower($metaKey)])) {
            continue;
        }
        $filtered = function_exists('siba_leads_unique_form_detail_rows')
            ? siba_leads_unique_form_detail_rows([$metaKey => $metaVal], [
                'form_identifier'          => $formId,
                'form_source_link'         => $formLink,
                'form_source_page_title'   => $formTitle,
                'demo_request_tracking_id' => $trackingExpand,
                'user_package_number'      => $packageExpand,
                'request_type'             => $requestExpand,
            ])
            : [$metaKey => $metaVal];
        if ($filtered === []) {
            continue;
        }
        ?>
    <div>
        <dt><?= e($metaKey); ?></dt>
        <dd><?= e($metaVal); ?></dd>
    </div>
    <?php } ?>
    </dl>
</div>
<?php } ?>
