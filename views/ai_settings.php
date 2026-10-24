<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-1"><?= e($title); ?></h4>
                <p class="text-muted tw-mb-4"><?= _l('siba_leads_ai_settings_desc'); ?></p>

                <?= form_open(admin_url('siba_leads/ai_settings')); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="openrouter_api_key"><?= _l('siba_leads_ai_settings_api_key'); ?></label>
                            <div class="input-group">
                                <input type="password"
                                    name="openrouter_api_key"
                                    id="openrouter_api_key"
                                    class="form-control"
                                    value=""
                                    autocomplete="new-password"
                                    placeholder="<?= e(!empty($api_key_configured) ? _l('siba_leads_ai_settings_api_key_keep') : 'sk-or-v1-…'); ?>">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" id="siba-ai-toggle-key" tabindex="-1">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </span>
                            </div>
                            <p class="text-muted tw-text-sm tw-mt-2 tw-mb-0">
                                <?php if (!empty($api_key_configured)) { ?>
                                    <span class="text-success"><i class="fa fa-check-circle"></i> <?= _l('siba_leads_ai_settings_api_key_set'); ?></span>
                                    — <?= _l('siba_leads_ai_settings_api_key_help'); ?>
                                <?php } else { ?>
                                    <span class="text-danger"><i class="fa fa-exclamation-circle"></i> <?= _l('siba_leads_ai_settings_api_key_missing'); ?></span>
                                <?php } ?>
                            </p>
                        </div>

                        <?= render_input(
                            'openrouter_model',
                            'siba_leads_ai_settings_model',
                            $openrouter_model,
                            'text',
                            ['placeholder' => e($openrouter_model !== '' ? $openrouter_model : 'recursive/flint-v1.0:free')]
                        ); ?>
                        <p class="text-muted tw-text-sm"><?= _l('siba_leads_ai_settings_model_help'); ?></p>

                        <div class="checkbox checkbox-primary">
                            <input type="checkbox"
                                name="openrouter_ssl_verify"
                                id="openrouter_ssl_verify"
                                value="1"
                                <?= !empty($openrouter_ssl_verify) ? 'checked' : ''; ?>>
                            <label for="openrouter_ssl_verify"><?= _l('siba_leads_ai_settings_ssl_verify'); ?></label>
                        </div>
                        <p class="text-muted tw-text-sm"><?= _l('siba_leads_ai_settings_ssl_verify_help'); ?></p>

                        <hr>
                        <button type="submit" name="save_ai_settings" value="1" class="btn btn-primary">
                            <?= _l('submit'); ?>
                        </button>
                        <a href="<?= admin_url('siba_leads'); ?>" class="btn btn-default">
                            <?= _l('siba_leads_workspace'); ?>
                        </a>
                    </div>
                </div>
                <?= form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function () {
    var $btn = $('#siba-ai-toggle-key');
    var $input = $('#openrouter_api_key');
    if (!$btn.length || !$input.length) {
        return;
    }
    $btn.on('click', function () {
        var isPass = $input.attr('type') === 'password';
        $input.attr('type', isPass ? 'text' : 'password');
        $btn.find('i').toggleClass('fa-eye fa-eye-slash');
    });
})();
</script>
</body>
</html>
