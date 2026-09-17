<?php defined('BASEPATH') or exit('No direct script access allowed');
$is_admin = is_admin();
foreach ($statuses as $status) {
    // Converted/default Customer column is no longer shown — succeeded leads leave the board.
    if (!empty($status['isdefault'])) {
        continue;
    }

    $kanBan = siba_leads_kanban_for_status($status['id']);
    $kanBan->search($this->input->get('search'))
        ->sortBy($this->input->get('sort_by'), $this->input->get('sort'));

    if ($this->input->get('refresh')) {
        $kanBan->refresh($this->input->get('refresh')[$status['id']] ?? null);
    }

    $leads       = $kanBan->get();
    $total_leads = count($leads);
    $total_pages = $kanBan->totalPages();

    $settings = '';
    foreach (get_system_favourite_colors() as $color) {
        $color_selected_class = ($color == $status['color']) ? 'cpicker-big' : 'cpicker-small';
        $settings .= "<div class='kanban-cpicker cpicker " . $color_selected_class . "' data-color='" . $color . "' style='background:" . $color . ';border:1px solid ' . $color . "'></div>";
    }

    $statusSummaryIndex = array_search($status['id'], array_column($summary, 'id'));
    $status_color       = !empty($status['color'])
        ? 'style="background:' . $status['color'] . ';border:1px solid ' . $status['color'] . '"'
        : '';
    $is_backlog = function_exists('siba_leads_is_backlog_status') && siba_leads_is_backlog_status($status['id']);
    ?>
<ul class="kan-ban-col<?= $is_backlog ? ' siba-backlog-col' : ''; ?>" data-col-status-id="<?= e($status['id']); ?>"
    data-total-pages="<?= e($total_pages); ?>"
    data-total="<?= e($total_leads); ?>"
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
                <small><?= ($summary[$statusSummaryIndex]['total'] ?? 0) . ' ' . _l('leads'); ?></small>
                <a href="#" onclick="return false;"
                    class="pull-right color-white kanban-color-picker kanban-stage-color-picker<?= $status['isdefault'] == 1 ? ' kanban-stage-color-picker-last' : ''; ?>"
                    data-placement="bottom" data-toggle="popover" data-html="true" data-trigger="focus"
                    data-content="
                        <div class='text-center'>
                          <button type='button' class='btn btn-primary btn-block mtop10 new-lead-from-status'>
                            <?= _l('new_lead'); ?>
                          </button>
                        </div>
                        <?php if ($is_admin) { ?>
                        <hr />
                        <div class='kan-ban-settings cpicker-wrapper'>
                          <?= $settings; ?>
                        </div>
                        <?php } ?>">
                    <i class="fa fa-angle-down"></i>
                </a>
            </div>
            <div class="kan-ban-content-wrapper">
                <div class="kan-ban-content">
                    <ul class="status leads-status sortable"
                        data-lead-status-id="<?= e($status['id']); ?>">
                        <?php foreach ($leads as $lead) {
                            $this->load->view('siba_leads/_kan_ban_card', [
                                'lead'          => $lead,
                                'status'        => $status,
                                'base_currency' => $base_currency,
                            ]);
                        } ?>
                        <?php if ($total_leads > 0) { ?>
                        <li class="text-center not-sortable kanban-load-more"
                            data-load-status="<?= e($status['id']); ?>">
                            <a href="#"
                                class="btn btn-default btn-block<?= ($total_pages <= 1 || $kanBan->getPage() === $total_pages) ? ' disabled' : ''; ?>"
                                data-page="<?= e($kanBan->getPage()); ?>"
                                onclick="kanban_load_more(<?= e($status['id']); ?>, this, 'siba_leads/kanban_load_more', 315, 360); return false;">
                                <?= _l('load_more'); ?>
                            </a>
                        </li>
                        <?php } ?>
                        <li class="text-center not-sortable mtop30 kanban-empty<?= $total_leads > 0 ? ' hide' : ''; ?>">
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
