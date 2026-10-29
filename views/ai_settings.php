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
                        <!-- Provider Selection -->
                        <div class="form-group">
                            <label class="control-label tw-font-bold"><?= _l('siba_leads_ai_settings_provider'); ?></label>
                            <div class="tw-flex tw-gap-4 tw-mt-2">
                                <div class="radio radio-primary radio-inline">
                                    <input type="radio" name="ai_provider" id="provider_gemini" value="gemini"
                                        <?= (empty($ai_provider) || $ai_provider === 'gemini') ? 'checked' : ''; ?>>
                                    <label for="provider_gemini"><strong>Google Gemini</strong> (جمینای گوگل - سریع و بهینه)</label>
                                </div>
                                <div class="radio radio-primary radio-inline">
                                    <input type="radio" name="ai_provider" id="provider_openrouter" value="openrouter"
                                        <?= ($ai_provider === 'openrouter') ? 'checked' : ''; ?>>
                                    <label for="provider_openrouter">OpenRouter (اوپن‌روتر)</label>
                                </div>
                            </div>
                        </div>

                        <!-- Execution Mode -->
                        <div class="form-group tw-mt-4">
                            <label class="control-label tw-font-bold"><?= _l('siba_leads_ai_execution_mode'); ?></label>
                            <div class="tw-flex tw-gap-4 tw-mt-2">
                                <div class="radio radio-primary radio-inline">
                                    <input type="radio" name="ai_execution_mode" id="exec_browser" value="browser"
                                        <?= (empty($ai_execution_mode) || $ai_execution_mode === 'browser') ? 'checked' : ''; ?>>
                                    <label for="exec_browser">
                                        <strong>مرورگر کاربر (غیرهمگام / Async Browser)</strong>
                                        <span class="label label-success mleft5">توصیه شده</span>
                                        <p class="text-muted tw-text-xs tw-mt-1 tw-mb-0">ارتباط مستقیم از مرورگر کاربر به API هوش مصنوعی؛ بدون تحریم سرور، فوق‌العاده سریع و بدون معطلی سرور.</p>
                                    </label>
                                </div>
                                <div class="radio radio-primary radio-inline tw-mt-2">
                                    <input type="radio" name="ai_execution_mode" id="exec_server" value="server"
                                        <?= ($ai_execution_mode === 'server') ? 'checked' : ''; ?>>
                                    <label for="exec_server">
                                        <strong>سرور (Server cURL)</strong>
                                        <p class="text-muted tw-text-xs tw-mt-1 tw-mb-0">ارسال درخواست توسط cURL سرور PHP.</p>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Gemini Settings Section -->
                        <div id="gemini-settings-section" class="<?= ($ai_provider === 'openrouter') ? 'hide' : ''; ?>">
                            <h5 class="tw-font-bold tw-text-base text-primary tw-mb-3">
                                <i class="fa-brands fa-google mright5"></i> تنظیمات Google Gemini
                            </h5>

                            <div class="form-group">
                                <label for="gemini_api_key"><?= _l('siba_leads_ai_settings_gemini_key'); ?></label>
                                <div class="input-group">
                                    <input type="password"
                                        name="gemini_api_key"
                                        id="gemini_api_key"
                                        class="form-control"
                                        value="<?= e($gemini_api_key ?? ''); ?>"
                                        autocomplete="new-password"
                                        placeholder="">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default siba-toggle-password" data-target="#gemini_api_key" tabindex="-1">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </span>
                                </div>
                                <p class="text-muted tw-text-sm tw-mt-2 tw-mb-0">
                                    <?php if (!empty($gemini_key_configured)) { ?>
                                        <span class="text-success"><i class="fa fa-check-circle"></i> <?= _l('siba_leads_ai_settings_api_key_set'); ?></span>
                                    <?php } else { ?>
                                        <span class="text-danger"><i class="fa fa-exclamation-circle"></i> <?= _l('siba_leads_ai_settings_api_key_missing'); ?></span>
                                    <?php } ?>
                                </p>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <?= render_input(
                                        'gemini_model',
                                        'siba_leads_ai_settings_gemini_model',
                                        $gemini_model ?? 'gemini-3.8-flash',
                                        'text',
                                        ['placeholder' => 'gemini-3.8-flash']
                                    ); ?>
                                    <p class="text-muted tw-text-xs">مدل پیشنهادی: <code>gemini-3.8-flash</code></p>
                                </div>
                                <div class="col-md-3">
                                    <?= render_input(
                                        'gemini_temperature',
                                        'siba_leads_ai_settings_gemini_temperature',
                                        $gemini_temperature ?? 0.2,
                                        'number',
                                        ['step' => '0.05', 'min' => '0', 'max' => '1']
                                    ); ?>
                                </div>
                                <div class="col-md-3">
                                    <?= render_input(
                                        'gemini_max_tokens',
                                        'siba_leads_ai_settings_gemini_max_tokens',
                                        $gemini_max_tokens ?? 50000,
                                        'number',
                                        ['step' => '1000', 'min' => '1000']
                                    ); ?>
                                </div>
                            </div>
                        </div>

                        <!-- OpenRouter Settings Section -->
                        <div id="openrouter-settings-section" class="<?= (empty($ai_provider) || $ai_provider === 'gemini') ? 'hide' : ''; ?>">
                            <h5 class="tw-font-bold tw-text-base text-primary tw-mb-3">
                                <i class="fa fa-robot mright5"></i> تنظیمات OpenRouter
                            </h5>

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
                                        <button type="button" class="btn btn-default siba-toggle-password" data-target="#openrouter_api_key" tabindex="-1">
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
                        </div>

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
    $('input[name="ai_provider"]').on('change', function () {
        var val = $(this).val();
        if (val === 'gemini') {
            $('#gemini-settings-section').removeClass('hide');
            $('#openrouter-settings-section').addClass('hide');
        } else {
            $('#gemini-settings-section').addClass('hide');
            $('#openrouter-settings-section').removeClass('hide');
        }
    });

    $('.siba-toggle-password').on('click', function () {
        var targetSel = $(this).data('target');
        var $input = $(targetSel);
        if (!$input.length) return;
        var isPass = $input.attr('type') === 'password';
        $input.attr('type', isPass ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });
})();
</script>
</body>
</html>
