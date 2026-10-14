<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Lightweight column shells only — cards load in parallel via kanban_column.
 */
$is_admin   = is_admin();
$can_create = function_exists('siba_leads_can_create')
    ? siba_leads_can_create()
    : ($is_admin || staff_can('create', 'siba_leads'));
foreach ($statuses as $status) {
    // Converted/default Customer column is no longer shown — succeeded leads leave the board.
    if (!empty($status['isdefault'])) {
        continue;
    }

    $settings = '';
    foreach (get_system_favourite_colors() as $color) {
        $color_selected_class = ($color == $status['color']) ? 'cpicker-big' : 'cpicker-small';
        $settings .= "<div class='kanban-cpicker cpicker " . $color_selected_class . "' data-color='" . $color . "' style='background:" . $color . ';border:1px solid ' . $color . "'></div>";
    }

    $statusSummaryIndex = array_search($status['id'], array_column($summary, 'id'));
    $statusTotal        = (int) ($summary[$statusSummaryIndex]['total'] ?? 0);
    $limit              = max(1, (int) get_option('leads_kanban_limit'));
    $total_pages        = $statusTotal > 0 ? (int) ceil($statusTotal / $limit) : 0;
    $status_color       = !empty($status['color'])
        ? 'style="background:' . $status['color'] . ';border:1px solid ' . $status['color'] . '"'
        : '';
    $is_backlog = function_exists('siba_leads_is_backlog_status') && siba_leads_is_backlog_status($status['id']);
    $refreshTotal = null;
    if ($this->input->get('refresh')) {
        $refreshTotal = $this->input->get('refresh')[$status['id']] ?? null;
    }
    ?>
<ul class="kan-ban-col<?= $is_backlog ? ' siba-backlog-col' : ''; ?>"
    data-col-status-id="<?= e($status['id']); ?>"
    data-total-pages="<?= e($total_pages); ?>"
    data-total="0"
    data-siba-loaded="0"
    <?= $refreshTotal !== null && $refreshTotal !== '' ? 'data-siba-refresh="' . e($refreshTotal) . '"' : ''; ?>
    <?= $is_backlog ? 'data-siba-backlog="1"' : ''; ?>>
    <li class="kan-ban-col-wrapper">
        <div class="border-right panel_s">
            <div class="panel-heading tw-bg-neutral-700 tw-text-white"
                <?php if ($status['isdefault'] == 1) { ?>
                data-toggle="tooltip"
                data-title="<?= _l('leads_converted_to_client') . ' - ' . _l('client'); ?>"
                <?php } ?>
                <?= $status_color; ?>
                data-status-id="<?= e($status['id']); ?>">
                <?php if ($is_admin) { ?>
                <i class="fa fa-reorder pointer"></i>
                <?php } ?>
                <span class="heading pointer tw-ml-1"
                    <?php if ($is_admin) { ?>
                    data-order="<?= e($status['statusorder']); ?>"
                    data-color="<?= e($status['color']); ?>"
                    data-name="<?= e($status['name']); ?>"
                    onclick="edit_status(this,<?= e($status['id']); ?>); return false;"
                    <?php } ?>>
                    <?= e($status['name']); ?>
                </span>
                -
                <?= app_format_money(
                    $summary[$statusSummaryIndex]['value'] ?? 0,
                    $base_currency
                ); ?>
                -
                <small><?= $statusTotal . ' ' . _l('leads'); ?></small>
                <?php if ($can_create || $is_admin) { ?>
                <a href="#" onclick="return false;"
                    class="pull-right color-white kanban-color-picker kanban-stage-color-picker<?= $status['isdefault'] == 1 ? ' kanban-stage-color-picker-last' : ''; ?>"
                    data-placement="bottom" data-toggle="popover" data-html="true" data-trigger="focus"
                    data-content="
                        <?php if ($can_create) { ?>
                        <div class='text-center'>
                          <button type='button' class='btn btn-primary btn-block mtop10 new-lead-from-status'>
                            <?= _l('new_lead'); ?>
                          </button>
                        </div>
                        <?php } ?>
                        <?php if ($is_admin) { ?>
                        <?php if ($can_create) { ?><hr /><?php } ?>
                        <div class='kan-ban-settings cpicker-wrapper'>
                          <?= $settings; ?>
                        </div>
                        <?php } ?>">
                    <i class="fa fa-angle-down"></i>
                </a>
                <?php } ?>
            </div>
            <div class="kan-ban-content-wrapper">
                <div class="kan-ban-content">
                    <ul class="status leads-status sortable"
                        data-lead-status-id="<?= e($status['id']); ?>">
                        <li class="text-center not-sortable siba-kanban-skeleton" aria-hidden="true">
                            <div class="siba-kanban-skeleton__card"></div>
                            <div class="siba-kanban-skeleton__card"></div>
                            <div class="siba-kanban-skeleton__card"></div>
                        </li>
                        <li class="text-center not-sortable kanban-load-more hide"
                            data-load-status="<?= e($status['id']); ?>">
                            <a href="#"
                                class="btn btn-default btn-block<?= $total_pages <= 1 ? ' disabled' : ''; ?>"
                                data-page="1"
                                onclick="kanban_load_more(<?= e($status['id']); ?>, this, 'siba_leads/kanban_load_more', 315, 360); return false;">
                                <?= _l('load_more'); ?>
                            </a>
                        </li>
                        <li class="text-center not-sortable mtop30 kanban-empty hide">
                            <h4>
                                <i class="fa-solid fa-circle-notch" aria-hidden="true"></i><br /><br />
                                <?= _l('no_leads_found'); ?>
                            </h4>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </li>
</ul>
<?php } ?>
