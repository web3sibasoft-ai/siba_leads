<?php defined('BASEPATH') or exit('No direct script access allowed');

$lead_already_client_tooltip = '';
$lead_is_client              = $lead['is_lead_client'] !== '0';
if ($lead_is_client) {
    $lead_already_client_tooltip = ' data-toggle="tooltip" title="' . _l('lead_have_client_profile') . '"';
}

if ($lead['status'] != $status['id']) {
    return;
}

$lead_value = $lead['lead_value'] != 0
    ? app_format_money($lead['lead_value'], $base_currency->symbol)
    : null;
$has_last_contact = is_date($lead['lastcontact']) && $lead['lastcontact'] != '0000-00-00 00:00:00';
$company_label = trim((string) ($lead['company'] ?? ''));
$title_label   = trim((string) ($lead['title'] ?? ''));
$locked_class  = $lead_is_client && get_option('lead_lock_after_convert_to_customer') == 1 && !is_admin()
    ? ' not-sortable'
    : '';
$mine_class = $lead['assigned'] == get_staff_user_id() ? ' current-user-lead' : '';
$myActiveTasks = (int) ($lead['my_active_tasks'] ?? 0);
$hasMyActiveTask = $myActiveTasks > 0;
?>
<li data-lead-id="<?= e($lead['id']); ?>"<?= $lead_already_client_tooltip; ?>
    class="lead-kan-ban siba-lead-card<?= $mine_class . $locked_class; ?><?= $hasMyActiveTask ? ' has-my-active-task' : ''; ?>">
    <div class="panel-body lead-body siba-lead-card__body">
        <div class="siba-lead-card__header">
            <div class="siba-lead-card__identity">
                <div class="siba-lead-card__title-wrap">
                    <a href="<?= admin_url('leads/index/' . e($lead['id'])); ?>"
                        class="siba-lead-card__name"
                        title="#<?= e($lead['id']) . ' - ' . e($lead['lead_name']); ?>"
                        onclick="init_lead(<?= e($lead['id']); ?>);return false;">
                        <?= e($lead['lead_name']); ?>
                    </a>
                    <div class="siba-lead-card__sub">
                        <span class="siba-lead-card__id">#<?= e($lead['id']); ?></span>
                        <?php if ($company_label !== '') { ?>
                        <span class="siba-lead-card__dot" aria-hidden="true">·</span>
                        <span class="siba-lead-card__company"><?= e($company_label); ?></span>
                        <?php } elseif ($title_label !== '') { ?>
                        <span class="siba-lead-card__dot" aria-hidden="true">·</span>
                        <span class="siba-lead-card__company"><?= e($title_label); ?></span>
                        <?php } ?>
                        <?php if ($lead_is_client) { ?>
                        <span class="siba-lead-card__badge siba-lead-card__badge--client">
                            <?= _l('client'); ?>
                        </span>
                        <?php } ?>
                        <?php if ($hasMyActiveTask) { ?>
                        <span class="siba-lead-card__badge siba-lead-card__badge--task"
                            data-toggle="tooltip"
                            title="<?= e(_l('siba_leads_card_my_active_tasks_tooltip', $myActiveTasks)); ?>">
                            <i class="fa-regular fa-circle-check"></i>
                            <?= e(_l('siba_leads_card_my_active_tasks', $myActiveTasks)); ?>
                        </span>
                        <?php } ?>
                        <?php
                        $phoneDup = !empty($lead['phone_duplicate']);
                        $phoneDupOf = (int) ($lead['phone_duplicate_of'] ?? 0);
                        if ($phoneDup) {
                            $dupTitle = $phoneDupOf > 0
                                ? _l('siba_leads_card_duplicate_phone_tooltip', $phoneDupOf)
                                : _l('siba_leads_duplicate_phone_warning');
                            ?>
                        <span class="siba-lead-card__badge siba-lead-card__badge--duplicate"
                            data-toggle="tooltip"
                            title="<?= e($dupTitle); ?>">
                            <i class="fa-solid fa-clone"></i>
                            <?= e(_l('siba_leads_card_duplicate_phone')); ?>
                            <?php if ($phoneDupOf > 0) { ?>
                            <a href="<?= admin_url('leads/index/' . $phoneDupOf); ?>"
                                class="siba-lead-card__badge-link"
                                onclick="init_lead(<?= (int) $phoneDupOf; ?>);return false;">
                                #<?= (int) $phoneDupOf; ?>
                            </a>
                            <?php } ?>
                        </span>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <button type="button"
                class="siba-lead-card__expand"
                onclick="slideToggle('#kan-ban-expand-<?= e($lead['id']); ?>'); return false;"
                title="<?= _l('siba_leads_card_details'); ?>">
                <i class="fa fa-chevron-down" aria-hidden="true"></i>
            </button>
        </div>

        <?php
        $assignedId   = (int) ($lead['assigned'] ?? 0);
        $assignedName = $assignedId > 0 ? get_staff_full_name($assignedId) : '';
        ?>
        <div class="siba-lead-card__assignee<?= $assignedId > 0 ? '' : ' is-empty'; ?>"
            title="<?= _l('leads_dt_assigned'); ?>">
            <?php if ($assignedId > 0) { ?>
            <a href="<?= admin_url('profile/' . $assignedId); ?>" class="siba-lead-card__assignee-link">
                <?= staff_profile_image($assignedId, ['staff-profile-image-xs', 'siba-lead-card__assignee-img']); ?>
                <span class="siba-lead-card__assignee-name"><?= e($assignedName); ?></span>
            </a>
            <?php } else { ?>
            <span class="siba-lead-card__assignee-link">
                <span class="siba-lead-card__assignee-img siba-lead-card__assignee-img--empty">
                    <i class="fa-regular fa-user"></i>
                </span>
                <span class="siba-lead-card__assignee-name"><?= _l('siba_leads_unassigned'); ?></span>
            </span>
            <?php } ?>
        </div>

        <div class="siba-lead-card__meta">
            <?php if (!empty($lead['source_name'])) { ?>
            <span class="siba-lead-card__chip" title="<?= _l('lead_add_edit_source'); ?>">
                <i class="fa-solid fa-bullseye"></i>
                <?= e($lead['source_name']); ?>
            </span>
            <?php } ?>
            <?php if ($lead_value) { ?>
            <span class="siba-lead-card__chip siba-lead-card__chip--value" title="<?= _l('lead_value'); ?>">
                <i class="fa-solid fa-coins"></i>
                <?= e($lead_value); ?>
            </span>
            <?php }
            $smsRaw = (string) ($lead['sms_verification'] ?? '');
            $smsVerified = function_exists('siba_leads_is_sms_verified')
                ? siba_leads_is_sms_verified($smsRaw)
                : in_array($smsRaw, ['1', 'true', 'True'], true);
            $formIdChip = trim((string) ($lead['form_identifier'] ?? ''));
            $formTitleChip = trim((string) ($lead['form_source_page_title'] ?? ''));
            $requestType = trim((string) ($lead['request_type'] ?? ''));
            $packageNo = trim((string) ($lead['user_package_number'] ?? ''));
            $trackingId = trim((string) ($lead['demo_request_tracking_id'] ?? ''));
            $hasFormMeta = $smsRaw !== '' || $requestType !== '' || $packageNo !== ''
                || $trackingId !== '' || $formIdChip !== '' || $formTitleChip !== '';
            if ($hasFormMeta) {
                if ($smsRaw !== '') { ?>
            <span class="siba-lead-card__chip siba-lead-card__chip--sms<?= $smsVerified ? ' is-verified' : ' is-pending'; ?>"
                title="<?= _l('sms_verification'); ?>">
                <i class="fa-solid fa-<?= $smsVerified ? 'check' : 'clock'; ?>"></i>
                <?= $smsVerified ? _l('siba_leads_sms_verified') : _l('siba_leads_sms_unverified'); ?>
            </span>
            <?php }
                if ($formTitleChip !== '') {
                    $formTitleShort = mb_strlen($formTitleChip) > 22
                        ? mb_substr($formTitleChip, 0, 20) . '…'
                        : $formTitleChip;
                    ?>
            <span class="siba-lead-card__chip" title="<?= e(_l('form_source_page_title') . ': ' . $formTitleChip); ?>">
                <i class="fa-regular fa-file-lines"></i>
                <?= e($formTitleShort); ?>
            </span>
            <?php }
                if ($requestType !== '') { ?>
            <span class="siba-lead-card__chip" title="<?= _l('request_type'); ?>">
                <i class="fa-solid fa-clipboard-list"></i>
                <?= e($requestType); ?>
            </span>
            <?php }
                if ($packageNo !== '') { ?>
            <span class="siba-lead-card__chip" title="<?= _l('user_package_number'); ?>">
                <i class="fa-solid fa-box"></i>
                <?= e($packageNo); ?>
            </span>
            <?php }
                if ($trackingId !== '') {
                    $trackingShort = mb_strlen($trackingId) > 18
                        ? mb_substr($trackingId, 0, 16) . '…'
                        : $trackingId;
                    ?>
            <span class="siba-lead-card__chip" title="<?= e(_l('demo_request_tracking_id') . ': ' . $trackingId); ?>">
                <i class="fa-solid fa-hashtag"></i>
                <?= e($trackingShort); ?>
            </span>
            <?php }
                if ($formIdChip !== '') { ?>
            <span class="siba-lead-card__chip" title="<?= _l('form_identifier'); ?>">
                <i class="fa-solid fa-fingerprint"></i>
                <?= e($formIdChip); ?>
            </span>
            <?php }
            } ?>
        </div>

        <?php if (!empty($lead['phonenumber']) || !empty($lead['email'])) { ?>
        <div class="siba-lead-card__contacts">
            <?php if (!empty($lead['phonenumber'])) { ?>
            <a href="tel:<?= e($lead['phonenumber']); ?>" class="siba-lead-card__contact" dir="ltr">
                <i class="fa-solid fa-phone"></i>
                <span><?= e($lead['phonenumber']); ?></span>
            </a>
            <?php } ?>
            <?php if (!empty($lead['email'])) { ?>
            <a href="mailto:<?= e($lead['email']); ?>" class="siba-lead-card__contact">
                <i class="fa-regular fa-envelope"></i>
                <span><?= e($lead['email']); ?></span>
            </a>
            <?php } ?>
        </div>
        <?php } ?>

        <?php if ($lead['tags']) { ?>
        <div class="siba-lead-card__tags kanban-tags">
            <?= render_tags($lead['tags']); ?>
        </div>
        <?php } ?>

            <div class="siba-lead-card__footer">
            <div class="siba-lead-card__dates">
                <?php if ($has_last_contact) { ?>
                <span class="siba-lead-card__date" data-toggle="tooltip" data-title="<?= e(function_exists('siba_leads_jalali_datetime') ? siba_leads_jalali_datetime($lead['lastcontact']) : _dt($lead['lastcontact'])); ?>">
                    <i class="fa-regular fa-comments"></i>
                    <?= e(time_ago($lead['lastcontact'])); ?>
                </span>
                <?php } ?>
                <span class="siba-lead-card__date" data-toggle="tooltip" data-title="<?= e(function_exists('siba_leads_jalali_datetime') ? siba_leads_jalali_datetime($lead['dateadded']) : _dt($lead['dateadded'])); ?>">
                    <i class="fa-regular fa-clock"></i>
                    <?= e(time_ago($lead['dateadded'])); ?>
                </span>
            </div>

            <div class="siba-lead-card__stats">
                <?php if (!$lead_is_client && (is_admin() || staff_can('edit', SIBA_LEADS_MODULE_NAME) || staff_can('edit', 'leads'))) { ?>
                <a href="#"
                   class="siba-lead-card__fail-btn text-danger"
                   data-toggle="tooltip"
                   title="<?= e(_l('siba_leads_mark_failed')); ?>"
                   onclick="siba_leads_open_mark_failed(<?= (int) $lead['id']; ?>); return false;">
                    <i class="fa-solid fa-circle-xmark"></i>
                </a>
                <?php } ?>
                <?php hooks()->do_action('before_leads_kanban_card_icons', $lead); ?>
                <span data-toggle="tooltip" data-title="<?= _l('leads_canban_notes', $lead['total_notes']); ?>">
                    <i class="fa-regular fa-note-sticky"></i>
                    <?= e($lead['total_notes']); ?>
                </span>
                <span data-toggle="tooltip" data-title="<?= _l('lead_kan_ban_attachments', $lead['total_files']); ?>">
                    <i class="fa fa-paperclip"></i>
                    <?= e($lead['total_files']); ?>
                </span>
                <?php hooks()->do_action('after_leads_kanban_card_icons', $lead); ?>
            </div>
        </div>

        <?php if (staff_can('creat_order', 'siba_license')) {
            $activeOrderId = (int) ($lead['active_order_id'] ?? 0);
            if ($activeOrderId > 0) {
                $pendingFinReq = (int) ($lead['has_pending_fin_req'] ?? 0) === 1;
                $invoiceId = (int) ($lead['lead_invoice_id'] ?? 0);
                $invoiceStatus = (int) ($lead['lead_invoice_status'] ?? 0);
                $invoicePaid = $invoiceId > 0 && $invoiceStatus === 2;
                $invoiceExpired = $invoiceId > 0 && $invoiceStatus === 5;
                $orderExpired = (int) ($lead['lead_order_expired'] ?? 0) === 1 || $invoiceExpired;
                $needsPayment = $invoiceId > 0 && !$invoicePaid && !$invoiceExpired;

                if ($orderExpired) {
        ?>
        <span class="siba-lead-card__order-btn siba-lead-card__order-btn--expired">
            <i class="fa fa-ban"></i>
            <span><?= _l('siba_leads_card_invoice_expired'); ?></span>
        </span>
        <?php } elseif ($needsPayment) {
            $invoiceUrl = admin_url('invoices/list_invoices/' . $invoiceId);
        ?>
        <a href="<?= e($invoiceUrl); ?>"
            class="siba-lead-card__order-btn siba-lead-card__order-btn--awaiting"
            onclick="event.stopPropagation();"
            onmousedown="event.stopPropagation();"
            title="<?= e(_l('siba_leads_card_awaiting_payment')); ?>">
            <i class="fa fa-clock"></i>
            <span><?= _l('siba_leads_card_awaiting_payment'); ?></span>
        </a>
        <?php } else {
                $finLabel = $pendingFinReq
                    ? _l('siba_license_finan_approl_requested')
                    : _l('siba_license_finan_approl_req');
                $finIcon = $pendingFinReq ? 'fa-hourglass-half' : 'fa-file-invoice-dollar';
                $finClass = $pendingFinReq
                    ? ' siba-lead-card__order-btn--requested'
                    : ' siba-lead-card__order-btn--finance';

                $financeReady = true;
                $financeMissingMsg = '';
                if (!$lead_is_client) {
                    if (function_exists('siba_leads_ensure_loaded')) {
                        siba_leads_ensure_loaded();
                    }
                    if (function_exists('siba_leads_lead_order_readiness')) {
                        $readiness = siba_leads_lead_order_readiness($lead);
                        $financeReady = !empty($readiness['ok']);
                        if (!$financeReady) {
                            $missingText = implode('، ', $readiness['missing_labels'] ?? []);
                            $financeMissingMsg = $missingText !== ''
                                ? _l('siba_leads_finance_incomplete', $missingText)
                                : _l('siba_leads_finance_incomplete_short');
                        }
                    }
                }

                if (!$financeReady && !$pendingFinReq) {
        ?>
        <a href="#"
            class="siba-lead-card__order-btn siba-lead-card__order-btn--incomplete"
            data-siba-complete-lead="<?= (int) $lead['id']; ?>"
            data-siba-missing="<?= e($financeMissingMsg); ?>"
            onclick="event.stopPropagation(); if (typeof siba_leads_prompt_complete_lead === 'function') { siba_leads_prompt_complete_lead(<?= (int) $lead['id']; ?>, this.getAttribute('data-siba-missing')); } else if (typeof init_lead === 'function') { init_lead(<?= (int) $lead['id']; ?>, true); } return false;"
            onmousedown="event.stopPropagation();"
            title="<?= e($financeMissingMsg !== '' ? $financeMissingMsg : _l('siba_leads_finance_incomplete_short')); ?>">
            <i class="fa fa-user-edit"></i>
            <span><?= _l('siba_leads_card_complete_lead'); ?></span>
        </a>
        <?php } else { ?>
        <a href="#"
            class="siba-lead-card__order-btn<?= $finClass; ?>"
            onclick="event.stopPropagation(); siba_leads_add_fin_approve_req(<?= $activeOrderId; ?>); return false;"
            onmousedown="event.stopPropagation();"
            title="<?= e($finLabel); ?>">
            <i class="fa <?= $finIcon; ?>"></i>
            <span><?= $finLabel; ?></span>
        </a>
        <?php }
            }
            } else {
            $existingOrderId = (int) ($lead['existing_order_id'] ?? 0);
            $hideNewOrder = $lead_is_client && $existingOrderId > 0;
            if ($hideNewOrder) {
                $hasOrderUrl = !empty($lead['client_userid'])
                    ? admin_url('clients/client/' . (int) $lead['client_userid'] . '?group=siba_license_reports')
                    : admin_url('siba_license/manage_orders');
        ?>
        <a href="<?= e($hasOrderUrl); ?>"
            class="siba-lead-card__order-btn siba-lead-card__order-btn--has-order"
            onclick="event.stopPropagation();"
            onmousedown="event.stopPropagation();"
            title="<?= e(_l('siba_leads_card_has_order')); ?>">
            <i class="fa fa-check"></i>
            <span><?= _l('siba_leads_card_has_order'); ?></span>
        </a>
        <?php } else {
                $lead_order_url = ($lead_is_client && !empty($lead['client_userid']))
                    ? admin_url('siba_license/show_add_orders/' . (int) $lead['client_userid'])
                    : admin_url('siba_license/show_add_orders/' . (int) $lead['id'] . '/lead');
        ?>
        <a href="<?= e($lead_order_url); ?>"
            class="siba-lead-card__order-btn"
            onclick="event.stopPropagation();"
            onmousedown="event.stopPropagation();"
            title="<?= e(_l('siba_leads_card_add_order')); ?>">
            <i class="fa fa-cart-plus"></i>
            <span><?= _l('siba_leads_card_add_order'); ?></span>
        </a>
        <?php }
            }
        } ?>

        <div id="kan-ban-expand-<?= e($lead['id']); ?>" class="siba-lead-card__details" style="display:none;">
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
                    <dd><?= e($lead['address'] != '' ? $lead['address'] : '—'); ?></dd>
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
                    <dd><?= e($lead['country'] != 0 ? get_country($lead['country'])->short_name : '—'); ?></dd>
                </div>
                <div>
                    <dt><?= _l('lead_zip'); ?></dt>
                    <dd><?= e($lead['zip'] != '' ? $lead['zip'] : '—'); ?></dd>
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
        </div>
    </div>
</li>
