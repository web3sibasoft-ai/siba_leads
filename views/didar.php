<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-1"><?= e($title); ?></h4>
                <p class="text-muted tw-mb-4"><?= _l('siba_leads_didar_migration_desc'); ?></p>

                <?= form_open(admin_url('siba_leads/didar')); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <?= render_input('didar_base_url', 'siba_leads_didar_base_url', $didar_base_url, 'text', ['placeholder' => 'https://app.didar.me']); ?>
                        <?= render_input('didar_api_key', 'siba_leads_didar_api_key', $didar_api_key, 'password'); ?>
                        <?php
                        $pipelineOptions = [];
                        foreach ($pipelines as $pipeline) {
                            $pipelineOptions[] = [
                                'id'   => (string) ($pipeline['Id'] ?? ''),
                                'name' => (string) ($pipeline['Title'] ?? $pipeline['Id'] ?? ''),
                            ];
                        }
                        echo render_select(
                            'didar_pipeline_id',
                            $pipelineOptions,
                            ['id', 'name'],
                            'siba_leads_didar_pipeline',
                            $didar_pipeline_id,
                            ['data-none-selected-text' => _l('siba_leads_didar_pipeline_auto')]
                        );
                        ?>
                        <p class="text-muted tw-text-sm"><?= _l('siba_leads_didar_pipeline_help'); ?></p>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox"
                                name="didar_ssl_verify"
                                id="didar_ssl_verify"
                                value="1"
                                <?= !empty($didar_ssl_verify) ? 'checked' : ''; ?>>
                            <label for="didar_ssl_verify"><?= _l('siba_leads_didar_ssl_verify'); ?></label>
                        </div>
                        <p class="text-muted tw-text-sm"><?= _l('siba_leads_didar_ssl_verify_help'); ?></p>
                        <?= render_input(
                            'didar_staff_field_slug',
                            'siba_leads_didar_staff_field_slug',
                            $didar_staff_field_slug,
                            'text',
                            ['placeholder' => 'didar']
                        ); ?>
                        <p class="text-muted tw-text-sm"><?= _l('siba_leads_didar_staff_field_slug_help'); ?></p>
                        <?= render_textarea(
                            'didar_staff_map_json',
                            'siba_leads_didar_staff_map_json',
                            $didar_staff_map_json,
                            ['rows' => 4, 'placeholder' => '{"42": 5, "17": 12}']
                        ); ?>
                        <p class="text-muted tw-text-sm"><?= _l('siba_leads_didar_staff_map_json_help'); ?></p>
                        <p class="text-muted tw-text-sm"><?= _l('siba_leads_didar_sync_staff_help'); ?></p>
                        <p class="text-muted tw-text-sm"><?= _l('siba_leads_didar_sync_sources_help'); ?></p>
                        <button type="button" class="btn btn-default" id="siba-didar-test">
                            <?= _l('siba_leads_didar_test_connection'); ?>
                        </button>
                        <button type="button" class="btn btn-info" id="siba-didar-sync-staff">
                            <?= _l('siba_leads_didar_sync_staff_button'); ?>
                        </button>
                        <button type="button" class="btn btn-info" id="siba-didar-sync-sources">
                            <?= _l('siba_leads_didar_sync_sources_button'); ?>
                        </button>
                        <button type="submit" name="save_didar_settings" value="1" class="btn btn-primary">
                            <?= _l('submit'); ?>
                        </button>
                    </div>
                </div>
                <?= form_close(); ?>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="tw-font-semibold text-danger"><?= _l('siba_leads_didar_wipe_title'); ?></h5>
                        <p class="text-muted"><?= _l('siba_leads_didar_wipe_desc'); ?></p>
                        <div class="form-group">
                            <label for="siba-didar-wipe-confirm"><?= _l('siba_leads_didar_wipe_confirm_label'); ?></label>
                            <input type="text" id="siba-didar-wipe-confirm" class="form-control" autocomplete="off" placeholder="WIPE">
                        </div>
                        <button type="button" class="btn btn-danger" id="siba-didar-wipe">
                            <?= _l('siba_leads_didar_wipe_button'); ?>
                        </button>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="tw-font-semibold"><?= _l('siba_leads_didar_pipelines_panel'); ?></h5>
                        <p class="text-muted"><?= _l('siba_leads_didar_pipeline_help'); ?></p>
                        <button type="button" class="btn btn-default" id="siba-didar-preview">
                            <?= _l('siba_leads_didar_preview_button'); ?>
                        </button>
                        <div class="table-responsive tw-mt-3">
                            <table class="table table-striped" id="siba-didar-preview-table">
                                <thead>
                                    <tr>
                                        <th><?= _l('siba_leads_didar_col_pipeline'); ?></th>
                                        <th><?= _l('siba_leads_didar_col_tag'); ?></th>
                                        <th><?= _l('siba_leads_didar_col_pending'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="3" class="text-muted">—</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="tw-font-semibold"><?= _l('siba_leads_didar_import_title'); ?></h5>
                        <p class="text-muted"><?= _l('siba_leads_didar_import_desc'); ?></p>
                        <button type="button" class="btn btn-success" id="siba-didar-import">
                            <?= _l('siba_leads_didar_import_button'); ?>
                        </button>
                        <p id="siba-didar-progress" class="text-muted tw-mt-3 tw-mb-0 tw-hidden"></p>
                        <div id="siba-didar-log-wrap" class="tw-mt-3 tw-hidden">
                            <label class="control-label"><?= _l('siba_leads_didar_live_log'); ?></label>
                            <div id="siba-didar-log"
                                class="siba-didar-log tw-max-h-96 tw-overflow-y-auto tw-text-sm tw-bg-neutral-900 tw-text-neutral-100 tw-p-3 tw-rounded tw-font-mono"
                                dir="ltr"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<style>
.siba-didar-log .log-line { margin: 0 0 4px; white-space: pre-wrap; word-break: break-word; }
.siba-didar-log .log-info { color: #93c5fd; }
.siba-didar-log .log-success { color: #86efac; }
.siba-didar-log .log-error { color: #fca5a5; }
</style>
<script>
(function () {
    var ajaxUrl = <?= json_encode(admin_url('siba_leads/didar')); ?>;
    var csrfName = <?= json_encode($this->security->get_csrf_token_name()); ?>;
    var csrfHash = <?= json_encode($this->security->get_csrf_hash()); ?>;
    var logEl = document.getElementById('siba-didar-log');
    var logWrap = document.getElementById('siba-didar-log-wrap');
    var progressEl = document.getElementById('siba-didar-progress');
    var importRunning = false;

    function postJson(data, done) {
        data[csrfName] = csrfHash;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            dataType: 'json'
        }).done(function (resp) {
            if (typeof resp === 'string') {
                try { resp = JSON.parse(resp); } catch (e) { resp = { ok: false, message: resp }; }
            }
            done(resp || {});
        }).fail(function (xhr) {
            var body = xhr.responseText || '';
            if (body.length > 2000) {
                body = body.substring(0, 2000) + '…';
            }
            done({
                ok: false,
                done: true,
                message: (xhr.status ? ('HTTP ' + xhr.status + ' ') : '') + (xhr.statusText || 'Request failed'),
                log: [{ level: 'error', time: '', message: body || xhr.statusText }]
            });
        });
    }

    function showLogPanel() {
        logWrap.classList.remove('tw-hidden');
        progressEl.classList.remove('tw-hidden');
    }

    function appendLogLines(lines) {
        if (!lines || !lines.length) {
            return;
        }
        showLogPanel();
        lines.forEach(function (line) {
            var level = (line.level || 'info');
            var p = document.createElement('div');
            p.className = 'log-line log-' + level;
            p.textContent = (line.time ? ('[' + line.time + '] ') : '') + (line.message || '');
            logEl.appendChild(p);
        });
        logEl.scrollTop = logEl.scrollHeight;
    }

    function setProgress(text) {
        progressEl.textContent = text || '';
    }

    function formatStats(stats) {
        if (!stats) {
            return '';
        }
        return <?= json_encode(_l('siba_leads_didar_log_stats_prefix')); ?>
            + (stats.leads || 0) + ' · '
            + (stats.updated || 0) + ' · '
            + (stats.tasks || 0) + ' · '
            + (stats.reports || 0) + ' · '
            + (stats.reminders || 0) + ' · '
            + (stats.skipped || 0) + ' · '
            + (stats.unmapped || 0) + ' · '
            + (stats.errors || 0);
    }

    function renderPreviewRows(rows) {
        var tbody = document.querySelector('#siba-didar-preview-table tbody');
        if (!tbody) {
            return;
        }
        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-muted">—</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (row) {
            return '<tr><td>' + (row.title || row.id || '') + '</td><td>'
                + (row.tag || '') + '</td><td>' + (row.pending || 0) + '</td></tr>';
        }).join('');
    }

    function runMigrateBatch(offset) {
        postJson({
            didar_ajax_action: 'migrate',
            migrate_step: 'batch',
            migrate_offset: offset,
            migrate_batch: 15
        }, function (resp) {
            appendLogLines(resp.log);
            if (resp.stats) {
                setProgress(formatStats(resp.stats));
            }
            if (!resp.ok || resp.done) {
                importRunning = false;
                $('#siba-didar-import').prop('disabled', false);
                if (resp.message) {
                    appendLogLines([{ level: resp.ok ? 'success' : 'error', time: '', message: resp.message }]);
                }
                return;
            }
            runMigrateBatch(resp.next_offset || 0);
        });
    }

    function startMigrate() {
        if (importRunning) {
            return;
        }
        importRunning = true;
        logEl.innerHTML = '';
        showLogPanel();
        setProgress(<?= json_encode(_l('siba_leads_didar_import_running')); ?>);
        $('#siba-didar-import').prop('disabled', true);

        postJson({
            didar_ajax_action: 'migrate',
            migrate_step: 'init'
        }, function (resp) {
            appendLogLines(resp.log);
            if (!resp.ok) {
                importRunning = false;
                $('#siba-didar-import').prop('disabled', false);
                appendLogLines([{ level: 'error', time: '', message: resp.message || 'Init failed' }]);
                return;
            }
            runMigrateBatch(0);
        });
    }

    $('#siba-didar-preview').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        logEl.innerHTML = '';
        showLogPanel();
        postJson({ didar_ajax_action: 'preview' }, function (resp) {
            $btn.prop('disabled', false);
            appendLogLines(resp.log || []);
            renderPreviewRows(resp.rows || []);
            appendLogLines([{ level: resp.ok ? 'success' : 'error', time: '', message: resp.message || '' }]);
        });
    });

    $('#siba-didar-test').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        logEl.innerHTML = '';
        showLogPanel();
        postJson({ didar_ajax_action: 'test' }, function (resp) {
            $btn.prop('disabled', false);
            appendLogLines(resp.log || [{ level: resp.ok ? 'success' : 'error', time: '', message: resp.message || '' }]);
        });
    });

    $('#siba-didar-sync-staff').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        logEl.innerHTML = '';
        showLogPanel();
        postJson({ didar_ajax_action: 'sync_staff' }, function (resp) {
            $btn.prop('disabled', false);
            appendLogLines(resp.log || []);
            appendLogLines([{ level: resp.ok ? 'success' : 'error', time: '', message: resp.message || '' }]);
        });
    });

    $('#siba-didar-sync-sources').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        logEl.innerHTML = '';
        showLogPanel();
        postJson({ didar_ajax_action: 'sync_sources' }, function (resp) {
            $btn.prop('disabled', false);
            appendLogLines(resp.log || []);
            appendLogLines([{ level: resp.ok ? 'success' : 'error', time: '', message: resp.message || '' }]);
        });
    });

    $('#siba-didar-wipe').on('click', function () {
        if (!confirm(<?= json_encode(_l('siba_leads_didar_wipe_confirm_dialog')); ?>)) {
            return;
        }
        var confirmVal = $('#siba-didar-wipe-confirm').val();
        var $btn = $(this).prop('disabled', true);
        logEl.innerHTML = '';
        postJson({ didar_ajax_action: 'wipe', confirm: confirmVal }, function (resp) {
            $btn.prop('disabled', false);
            appendLogLines(resp.log || []);
            if (resp.stats) {
                appendLogLines([{ level: 'info', time: '', message: JSON.stringify(resp.stats) }]);
            }
            appendLogLines([{ level: resp.ok ? 'success' : 'error', time: '', message: resp.message || '' }]);
        });
    });

    $('#siba-didar-import').on('click', function () {
        if (!confirm(<?= json_encode(_l('siba_leads_didar_import_confirm_dialog')); ?>)) {
            return;
        }
        startMigrate();
    });
})();
</script>
