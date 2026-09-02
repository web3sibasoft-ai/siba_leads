(function () {
    'use strict';

    window.sibaLeads = window.sibaLeads || {
        version: '1.2.0',
        hideLeadFields: window.sibaLeadsHideLeadFields || {}
    };

    window.siba_leads_kanban_sort = function (type) {
        if (typeof kan_ban_sort === 'function') {
            kan_ban_sort(type, siba_leads_kanban);
        }
    };

    window.siba_leads_kanban_update = function (ui, object) {
        if (object !== ui.item.parent()[0]) {
            return;
        }

        var data = {
            status: $(ui.item.parent()[0]).attr('data-lead-status-id'),
            leadid: $(ui.item).attr('data-lead-id'),
            order: []
        };

        $.each($(ui.item).parents('.leads-status').find('li'), function (idx, el) {
            var id = $(el).attr('data-lead-id');
            if (id) {
                data.order.push([id, idx + 1]);
            }
        });

        setTimeout(function () {
            $.post(admin_url + 'siba_leads/update_lead_status', data).done(function () {
                if (typeof update_kan_ban_total_when_moving === 'function') {
                    update_kan_ban_total_when_moving(ui, data.status);
                }
                siba_leads_kanban();
            });
        }, 200);
    };

    window.siba_leads_init_status_sortable = function () {
        if (!$('#kan-ban').length) {
            return;
        }

        // Keep column flow matched to admin direction (Persian RTL users).
        var rtl = (typeof isRTL !== 'undefined' && (isRTL === true || isRTL === 'true'))
            || document.documentElement.getAttribute('dir') === 'rtl'
            || document.body.classList.contains('rtl');
        $('#kan-ban').css('direction', rtl ? 'rtl' : 'ltr');

        $('#kan-ban').sortable({
            helper: 'clone',
            item: '.kan-ban-col',
            update: function () {
                var data = { order: [] };

                $.each($('.kan-ban-col'), function (idx, el) {
                    data.order.push([$(el).attr('data-col-status-id'), idx + 1]);
                });

                $.post(admin_url + 'siba_leads/update_status_order', data);
            }
        });
    };

    window.siba_leads_add_fin_approve_req = function (id) {
        if (!id) {
            return;
        }

        var $wrap = $('#siba-leads-fin-modal-wrap');
        if (!$wrap.length) {
            $wrap = $('<div id="siba-leads-fin-modal-wrap"></div>').appendTo('body');
        }

        $.ajax({
            type: 'post',
            url: admin_url + 'siba_license/add_fin_approve_req_show/' + id,
            success: function (comment) {
                comment = typeof comment === 'string' ? JSON.parse(comment) : comment;
                $wrap.html(comment.view);
                var $form = $wrap.find('#add_product_group_form');
                if ($form.length && !$form.find('[name="siba_leads_return"]').length) {
                    $form.append('<input type="hidden" name="siba_leads_return" value="1">');
                }
                $('#add_inv_approve_reques-modal').modal('show');
            }
        });
    };

    window.siba_leads_mark_order_paid = function (id) {
        if (!id) {
            return;
        }
        var message = window.sibaLeadsMarkPaidConfirm || '';
        if (message && !window.confirm(message)) {
            return;
        }
        $.ajax({
            type: 'post',
            url: admin_url + 'siba_license/mark_order_paid/' + id,
            dataType: 'json',
            success: function (res) {
                if (res && res.success) {
                    if (typeof siba_leads_kanban === 'function') {
                        siba_leads_kanban();
                    } else {
                        window.location.reload();
                    }
                    return;
                }
                alert((res && res.message) || message);
            }
        });
    };

    window.siba_leads_kanban = function () {
        if (typeof init_kanban !== 'function') {
            return;
        }

        init_kanban(
            'siba_leads/kanban',
            siba_leads_kanban_update,
            '.leads-status',
            290,
            360,
            siba_leads_init_status_sortable
        );
    };

    function normalizeLabel(text) {
        return String(text || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function hideLeadProfileFields($modal) {
        var labels = window.sibaLeads.hideLeadFields || {};
        var hideSet = {};

        Object.keys(labels).forEach(function (key) {
            hideSet[normalizeLabel(labels[key])] = true;
        });

        // Always hide these in edit mode (Siba uses permissions / notes instead).
        $modal.find('[name="lead_value"]').closest('.form-group').addClass('siba-hide-lead-field');
        $modal.find('#default_language, [name="default_language"]').closest('.form-group').addClass('siba-hide-lead-field');

        // Last contact: profile/view only — do not submit from edit form.
        $modal.find('[name="lastcontact"], #lastcontact').each(function () {
            var $input = $(this);
            $input.closest('.form-group').addClass('siba-hide-lead-field');
            $input.prop('disabled', true);
        });

        // Public checkbox: visibility is controlled by Siba lead permissions.
        $modal.find('#lead_public, [name="is_public"]').each(function () {
            var $input = $(this);
            var $wrap = $input.closest('.checkbox, .checkbox-inline, .form-group');
            $wrap.addClass('siba-hide-lead-field');

            // Perfex sets is_public=0 when checkbox is absent from POST — preserve current value.
            if ($input.is(':checkbox') && $input.is(':checked')) {
                var $form = $input.closest('form');
                if ($form.length && !$form.find('input[type="hidden"][name="is_public"]').length) {
                    $('<input>', { type: 'hidden', name: 'is_public', value: '1' }).appendTo($form);
                }
            }
            $input.prop('disabled', true);
        });

        // Whatsapp Enable custom field (and any configured hide labels).
        $modal.find('.form-group').each(function () {
            var $group = $(this);
            if ($group.hasClass('siba-lead-location-province')
                || $group.hasClass('siba-lead-location-city')
                || $group.hasClass('siba-lead-profile-position')
                || $group.hasClass('siba-lead-profile-job-group')
                || $group.hasClass('siba-lead-profile-national-code')) {
                return;
            }
            var labelText = normalizeLabel($group.find('label.control-label, label').first().text().replace('*', ''));
            if (!labelText) {
                return;
            }
            if (hideSet[labelText] || labelText.indexOf('whatsapp enable') !== -1 || labelText.indexOf('whatsapp') !== -1) {
                $group.addClass('siba-hide-lead-field');
                $group.find('input, select, textarea').each(function () {
                    var $el = $(this);
                    $el.removeAttr('required')
                        .removeAttr('data-custom-field-required')
                        .removeAttr('data-fieldto')
                        .prop('disabled', true);
                    if ($el.is('select')) {
                        $el.selectpicker('refresh');
                    }
                });
            }
        });

        // View mode: match localized labels (+ public / whatsapp / value / language).
        $modal.find('.lead-view dt.lead-field-heading').each(function () {
            var label = normalizeLabel($(this).text());
            if (hideSet[label] || label.indexOf('whatsapp') !== -1) {
                $(this).addClass('siba-hide-lead-field');
                $(this).next('dd').addClass('siba-hide-lead-field');
            }
        });
    }

    function getLocationLabels() {
        return window.sibaLeadsLocationLabels || {
            province: 'Province',
            city: 'City',
            select: 'Select',
            loading: 'Loading...',
            pickProvinceFirst: 'Select province first'
        };
    }

    function buildProvinceOptions(selectedId) {
        var labels = getLocationLabels();
        var provinces = window.sibaLeadsProvinces || [];
        var opts = '<option value="">' + labels.select + '</option>';
        $.each(provinces, function (i, province) {
            var id = String(province.province_id);
            opts += '<option value="' + id + '"' + (String(selectedId) === id ? ' selected' : '') + '>' + province.province_name + '</option>';
        });
        return opts;
    }

    function buildCityOptions(cities, selectedId) {
        var labels = getLocationLabels();
        var opts = '<option value="">' + labels.select + '</option>';
        $.each(cities || [], function (i, city) {
            var id = String(city.city_id);
            opts += '<option value="' + id + '"' + (String(selectedId) === id ? ' selected' : '') + '>' + city.city_name + '</option>';
        });
        return opts;
    }

    function getLeadEditPanel($modal) {
        var $panels = $modal.find('.lead-edit');
        if ($panels.length <= 1) {
            return $panels.first();
        }

        return $panels.filter(function () {
            return $(this).find('select[name="status"], select#status').length > 0;
        }).first();
    }

    function isLeadEditPanelOpen($modal) {
        var $panel = getLeadEditPanel($modal);
        return $panel.length > 0 && !$panel.hasClass('hide');
    }

    function isEditingExistingLead($modal) {
        return !!String($modal.find('input[name="leadid"]').val() || '').trim();
    }

    function canInitLeadLocationFields($modal) {
        return !isEditingExistingLead($modal) || isLeadEditPanelOpen($modal);
    }

    function isRtlUi() {
        return (typeof isRTL !== 'undefined' && (isRTL === true || isRTL === 'true'))
            || document.documentElement.getAttribute('dir') === 'rtl'
            || document.body.classList.contains('rtl');
    }

    function getSelectpickerValue($select) {
        if (!$select || !$select.length) {
            return '';
        }

        if ($select.parent().hasClass('bootstrap-select')) {
            var val = $select.selectpicker('val');
            if (Array.isArray(val)) {
                val = val[0] || '';
            }
            return String(val || '');
        }

        return String($select.val() || '');
    }

    function destroyLocationSelectpicker($select) {
        if (!$select || !$select.length || !$select.parent().hasClass('bootstrap-select')) {
            return;
        }

        try {
            $select.selectpicker('destroy');
        } catch (e) {
            // Ignore teardown errors on stale nodes.
        }
    }

    function cleanupLocationPickerArtifacts() {
        $('body > .bs-container').remove();
        $('#lead-modal .siba-lead-location-province .bootstrap-select.open, #lead-modal .siba-lead-location-city .bootstrap-select.open')
            .removeClass('open')
            .find('.dropdown-menu')
            .removeClass('show');
    }

    function closeLocationPickerIfOpen($select) {
        if (!$select || !$select.length || !$select.parent().hasClass('bootstrap-select')) {
            return;
        }

        if ($select.parent().hasClass('open')) {
            $select.selectpicker('toggle');
        }
    }

    function enableLeadModalSelectpicker($select) {
        if (!$select || !$select.length) {
            return;
        }

        $select.prop('disabled', false).removeAttr('disabled');

        var $wrap = $select.parent('.bootstrap-select');
        if (!$wrap.length) {
            return;
        }

        $wrap.removeClass('disabled');
        $wrap.find('button.dropdown-toggle')
            .prop('disabled', false)
            .removeAttr('disabled')
            .removeClass('disabled')
            .attr('aria-disabled', 'false');
        $select.selectpicker('refresh');
    }

    function mountLocationSelectpicker($select) {
        if (!$select || !$select.length) {
            return;
        }

        $select.prop('disabled', false).removeAttr('disabled');

        if ($select.parent().hasClass('bootstrap-select')) {
            enableLeadModalSelectpicker($select);
            return;
        }

        cleanupLocationPickerArtifacts();

        $select.selectpicker({
            showSubtext: true,
            width: $select.data('width') || '100%',
            dropupAuto: false
        });
        enableLeadModalSelectpicker($select);
    }

    function ensureLeadModalSelectsEnabled($modal) {
        if (!$modal || !$modal.length) {
            return;
        }

        if (isEditingExistingLead($modal) && !isLeadEditPanelOpen($modal)) {
            return;
        }

        var $form = $modal.find('#lead_form');
        if (!$form.length) {
            return;
        }

        if (!$modal.find('.lead-save-btn').length) {
            return;
        }

        $form.find([
            '#siba_lead_province_id',
            '#siba_lead_city_id',
            '#siba_lead_position_id',
            '#siba_lead_job_group_id'
        ].join(', ')).each(function () {
            enableLeadModalSelectpicker($(this));
        });
    }

    function citiesDatasetReady() {
        return typeof window.sibaLeadsCitiesByProvince !== 'undefined'
            && window.sibaLeadsCitiesByProvince !== null
            && !Array.isArray(window.sibaLeadsCitiesByProvince);
    }

    function getCitiesForProvince(provinceId) {
        if (!citiesDatasetReady() || !provinceId) {
            return null;
        }

        var grouped = window.sibaLeadsCitiesByProvince;
        var key = String(provinceId);
        if (Object.prototype.hasOwnProperty.call(grouped, key)) {
            return grouped[key];
        }
        if (Object.prototype.hasOwnProperty.call(grouped, provinceId)) {
            return grouped[provinceId];
        }

        return [];
    }

    function renderLeadCityOptions($citySelect, cities, selectedCityId) {
        closeLocationPickerIfOpen($citySelect);
        $citySelect.prop('disabled', false).removeAttr('disabled');
        $citySelect.html(buildCityOptions(normalizeCityRows(cities), selectedCityId));

        if ($citySelect.parent().hasClass('bootstrap-select')) {
            if (selectedCityId) {
                $citySelect.selectpicker('val', String(selectedCityId));
            }
            enableLeadModalSelectpicker($citySelect);
            return;
        }

        mountLocationSelectpicker($citySelect);
        if (selectedCityId) {
            $citySelect.selectpicker('val', String(selectedCityId));
            enableLeadModalSelectpicker($citySelect);
        }
    }

    function normalizeCityRows(rows) {
        if (!Array.isArray(rows)) {
            return [];
        }

        return rows.filter(function (row) {
            return row
                && row.city_id !== null
                && row.city_id !== undefined
                && String(row.city_id) !== ''
                && row.city_name;
        });
    }

    function hideLeadCountryField($form) {
        if (!$form || !$form.length || $form.find('input[data-siba-default-country]').length) {
            return;
        }

        var $country = $form.find('select[name="country"], #country').first();
        if (!$country.length) {
            return;
        }

        var countryId = getSelectpickerValue($country) || $country.val() || '';
        if (!countryId && window.sibaLeadsDefaultCountryId) {
            countryId = String(window.sibaLeadsDefaultCountryId);
        }

        destroyLocationSelectpicker($country);

        $('<input>', {
            type: 'hidden',
            name: 'country',
            value: countryId,
            'data-siba-default-country': '1'
        }).appendTo($form);

        $country.prop('disabled', true).removeAttr('name').removeAttr('id');
        $country.closest('.form-group').addClass('siba-hide-lead-field').hide();
    }

    function fixLeadModalStatusSourceFields($modal) {
        $modal.find('.form-group-select-input-status, .form-group-select-input-source').each(function () {
            var $wrap = $(this);
            $wrap.removeClass('select-placeholder');

            var $select = $wrap.find('select.selectpicker').first();
            if (!$select.length) {
                return;
            }

            if (!$select.parent().hasClass('bootstrap-select')) {
                $select.selectpicker({
                    showSubtext: true,
                    width: '100%'
                });
                return;
            }

            $select.selectpicker('refresh');
        });
    }

    function bindLeadLocationFieldEvents($modal, $provinceSelect, $citySelect) {
        $provinceSelect.off('.sibaLocation');
        $citySelect.off('.sibaLocation');

        function onProvinceChanged() {
            var provinceId = getSelectpickerValue($provinceSelect);
            loadLeadCities(provinceId, '', $citySelect).always(function () {
                syncLeadLocationHiddenFields($modal);
                updateLeadLocationView(
                    $modal,
                    $provinceSelect.find('option:selected').text(),
                    ''
                );
            });
        }

        $provinceSelect.on('changed.bs.select.sibaLocation', onProvinceChanged);
        $provinceSelect.on('change.sibaLocation', onProvinceChanged);
        $provinceSelect.on('show.bs.select.sibaLocation', function () {
            closeLocationPickerIfOpen($citySelect);
        });

        $citySelect.on('changed.bs.select.sibaLocation', function () {
            syncLeadLocationHiddenFields($modal);
            updateLeadLocationView(
                $modal,
                $provinceSelect.find('option:selected').text(),
                $citySelect.find('option:selected').text()
            );
        });
        $citySelect.on('show.bs.select.sibaLocation', function () {
            closeLocationPickerIfOpen($provinceSelect);
        });
    }

    function ensureLocationHiddenInputs($form) {
        var $stateHidden = $form.find('input[type="hidden"][data-siba-location-sync="state"]');
        var $cityHidden = $form.find('input[type="hidden"][data-siba-location-sync="city"]');

        if (!$stateHidden.length) {
            $stateHidden = $('<input>', {
                type: 'hidden',
                name: 'state',
                'data-siba-location-sync': 'state'
            }).appendTo($form);
        }

        if (!$cityHidden.length) {
            $cityHidden = $('<input>', {
                type: 'hidden',
                name: 'city',
                'data-siba-location-sync': 'city'
            }).appendTo($form);
        }

        return {
            state: $stateHidden,
            city: $cityHidden
        };
    }

    function syncLeadLocationHiddenFields($scope) {
        var $form = $scope.find('#lead_form');
        if (!$form.length) {
            return;
        }

        var $province = $form.find('#siba_lead_province_id');
        var $city = $form.find('#siba_lead_city_id');
        if (!$province.length || !$city.length) {
            return;
        }

        var provinceName = $province.find('option:selected').text() || '';
        var cityName = $city.find('option:selected').text() || '';
        if (getSelectpickerValue($province) === '') {
            provinceName = '';
        }
        if (getSelectpickerValue($city) === '') {
            cityName = '';
        }

        var hidden = ensureLocationHiddenInputs($form);
        hidden.state.val(provinceName);
        hidden.city.val(cityName);
    }

    function refreshLeadLocationPickers($modal) {
        var $form = $modal.find('#lead_form');
        if (!$form.length) {
            return $.Deferred().resolve().promise();
        }

        var $province = $form.find('#siba_lead_province_id');
        var $city = $form.find('#siba_lead_city_id');
        if (!$province.length || !$city.length) {
            return $.Deferred().resolve().promise();
        }

        var seed = window.sibaLeadLocationSeed || null;
        var provinceId = getSelectpickerValue($province);
        var cityId = getSelectpickerValue($city);

        if (!provinceId && seed && seed.province_id) {
            provinceId = String(seed.province_id);
        }
        if (!cityId && seed && seed.city_id) {
            cityId = String(seed.city_id);
        }

        if (provinceId) {
            mountLocationSelectpicker($province);
            $province.selectpicker('val', provinceId);
            enableLeadModalSelectpicker($province);
        } else {
            mountLocationSelectpicker($province);
        }

        if (!provinceId) {
            mountLocationSelectpicker($city);
            enableLeadModalSelectpicker($city);
            syncLeadLocationHiddenFields($modal);
            return $.Deferred().resolve().promise();
        }

        return loadLeadCities(provinceId, cityId, $city).always(function () {
            syncLeadLocationHiddenFields($modal);
            enableLeadModalSelectpicker($city);
            enableLeadModalSelectpicker($province);
        });
    }

    function loadLeadCities(provinceId, selectedCityId, $citySelect) {
        var labels = getLocationLabels();
        var citiesUrl = window.sibaLeadsCitiesUrl || (admin_url + 'siba_leads/get_cities');
        provinceId = String(provinceId || '');
        var deferred = $.Deferred();

        if (!provinceId) {
            renderLeadCityOptions($citySelect, [], '');
            deferred.resolve();
            return deferred.promise();
        }

        var localCities = getCitiesForProvince(provinceId);
        if (localCities !== null) {
            renderLeadCityOptions($citySelect, localCities, selectedCityId);
            deferred.resolve(localCities);
            return deferred.promise();
        }

        if ($citySelect.parent().hasClass('bootstrap-select')) {
            $citySelect.html('<option value="">' + labels.loading + '</option>');
            $citySelect.selectpicker('refresh');
        } else {
            $citySelect.html('<option value="">' + labels.loading + '</option>');
            mountLocationSelectpicker($citySelect);
        }

        $.ajax({
            url: citiesUrl,
            type: 'POST',
            data: { province_id: provinceId },
            dataType: 'json'
        }).done(function (cities) {
            renderLeadCityOptions($citySelect, cities, selectedCityId);
            deferred.resolve(cities);
        }).fail(function () {
            renderLeadCityOptions($citySelect, [], '');
            deferred.reject();
        });

        return deferred.promise();
    }

    function updateLeadLocationView($modal, provinceName, cityName) {
        var labels = getLocationLabels();
        var $col = $modal.find('.lead-information-col').first();
        var $addressDt = $col.find('dt.lead-field-heading').filter(function () {
            var text = normalizeLabel($(this).text());
            return text.indexOf('address') !== -1 || text.indexOf('آدرس') !== -1;
        }).first();

        if (!$addressDt.length) {
            return;
        }

        var $cityDt = $addressDt.next('dd').next('dt.lead-field-heading');
        var $stateDt = $cityDt.next('dd').next('dt.lead-field-heading');

        if ($cityDt.length) {
            $cityDt.text(labels.city);
            $cityDt.next('dd').text(cityName || '—');
        }
        if ($stateDt.length) {
            $stateDt.text(labels.province);
            $stateDt.next('dd').text(provinceName || '—');
        }
    }

    function replaceLeadLocationFields($modal) {
        var $form = $modal.find('#lead_form');
        if (!$form.length) {
            return;
        }

        if (!canInitLeadLocationFields($modal)) {
            return;
        }

        if ($form.find('#siba_lead_province_id').length) {
            if (!$form.data('sibaLocationReady')) {
                $form.data('sibaLocationReady', true);
            }
            hideLeadCountryField($form);
            bindLeadLocationFieldEvents(
                $modal,
                $form.find('#siba_lead_province_id'),
                $form.find('#siba_lead_city_id')
            );
            refreshLeadLocationPickers($modal);
            return;
        }

        if ($form.data('sibaLocationPending')) {
            return;
        }
        $form.data('sibaLocationPending', true);

        var labels = getLocationLabels();
        var seed = window.sibaLeadLocationSeed || null;
        var $cityInput = $form.find('input[name="city"]');
        var $stateInput = $form.find('input[name="state"]');

        if (!$cityInput.length && !$stateInput.length) {
            $form.removeData('sibaLocationPending');
            return;
        }

        var selectedProvince = seed ? String(seed.province_id || '') : '';
        var selectedCity = seed ? String(seed.city_id || '') : '';
        var $legacyCityGroup = $cityInput.closest('.form-group');
        var $legacyStateGroup = $stateInput.closest('.form-group');
        var $addressGroup = $form.find('#address, textarea[name="address"]').first().closest('.form-group');

        $cityInput.prop('disabled', true).removeAttr('name').removeAttr('id');
        $stateInput.prop('disabled', true).removeAttr('name').removeAttr('id');
        ensureLocationHiddenInputs($form);

        var $provinceGroup = $('<div class="form-group siba-lead-location-province"></div>');
        $provinceGroup.append('<label class="control-label">' + labels.province + '</label>');
        var $provinceSelect = $('<select>', {
            id: 'siba_lead_province_id',
            name: 'province_id',
            class: 'form-control selectpicker',
            'data-none-selected-text': labels.select
        });
        $provinceSelect.html(buildProvinceOptions(selectedProvince));
        $provinceGroup.append($provinceSelect);

        var $cityGroup = $('<div class="form-group siba-lead-location-city"></div>');
        $cityGroup.append('<label class="control-label">' + labels.city + '</label>');
        var $citySelect = $('<select>', {
            id: 'siba_lead_city_id',
            name: 'city_id',
            class: 'form-control selectpicker',
            'data-none-selected-text': labels.select
        });
        $citySelect.html('<option value="">' + labels.pickProvinceFirst + '</option>');
        $cityGroup.append($citySelect);

        if ($legacyStateGroup.length) {
            $provinceGroup.insertAfter($legacyStateGroup);
        } else if ($legacyCityGroup.length) {
            $provinceGroup.insertBefore($legacyCityGroup);
        } else if ($addressGroup.length) {
            $provinceGroup.insertAfter($addressGroup);
        } else {
            $form.find('.col-md-6').last().append($provinceGroup);
        }
        $cityGroup.insertAfter($provinceGroup);

        $legacyCityGroup.remove();
        $legacyStateGroup.remove();
        hideLeadCountryField($form);

        mountLocationSelectpicker($provinceSelect);
        mountLocationSelectpicker($citySelect);
        if (selectedProvince) {
            $provinceSelect.selectpicker('val', selectedProvince);
            enableLeadModalSelectpicker($provinceSelect);
        }
        enableLeadModalSelectpicker($citySelect);
        bindLeadLocationFieldEvents($modal, $provinceSelect, $citySelect);

        $form.off('submit.sibaLocation').on('submit.sibaLocation', function () {
            syncLeadLocationHiddenFields($modal);
        });

        if (selectedProvince) {
            loadLeadCities(selectedProvince, selectedCity, $citySelect).always(function () {
                syncLeadLocationHiddenFields($modal);
                updateLeadLocationView(
                    $modal,
                    seed && seed.province_name ? seed.province_name : $provinceSelect.find('option:selected').text(),
                    seed && seed.city_name ? seed.city_name : $citySelect.find('option:selected').text()
                );
            });
        } else {
            syncLeadLocationHiddenFields($modal);
            updateLeadLocationView(
                $modal,
                seed && seed.province_name ? seed.province_name : '',
                seed && seed.city_name ? seed.city_name : ''
            );
        }

        $form.data('sibaLocationReady', true);
        $form.removeData('sibaLocationPending');
    }

    function getProfileLabels() {
        return window.sibaLeadsProfileLabels || {
            nationalCode: 'National ID',
            position: 'Position',
            jobGroup: 'Job group',
            select: 'Select'
        };
    }

    function buildJobGroupOptions(selectedId) {
        var labels = getProfileLabels();
        var groups = window.sibaLeadJobGroups || [];
        var opts = '<option value="">' + labels.select + '</option>';
        $.each(groups, function (i, group) {
            var id = String(group.siba_job_group_id);
            opts += '<option value="' + id + '"' + (String(selectedId) === id ? ' selected' : '') + '>' + group.siba_job_group_name + '</option>';
        });
        return opts;
    }

    function buildPositionOptions(selectedId) {
        var labels = getProfileLabels();
        var positions = window.sibaLeadPositions || [];
        var opts = '<option value="">' + labels.select + '</option>';
        $.each(positions, function (i, position) {
            var id = String(position.siba_position_id);
            opts += '<option value="' + id + '"' + (String(selectedId) === id ? ' selected' : '') + '>' + position.siba_position_name + '</option>';
        });
        return opts;
    }

    function syncLeadProfileTitle($form) {
        var $position = $form.find('#siba_lead_position_id');
        var $title = $form.find('input[name="title"]');
        if (!$position.length || !$title.length) {
            return;
        }

        var positionName = $position.find('option:selected').text() || '';
        if ($position.val() && positionName && positionName !== getProfileLabels().select) {
            $title.val(positionName);
        }
    }

    function updateLeadProfileView($modal) {
        var labels = getProfileLabels();
        var seed = window.sibaLeadProfileSeed || null;
        var $col = $modal.find('.lead-information-col').first();
        if (!$col.length) {
            return;
        }

        var nationalCode = seed ? (seed.national_code || '') : ($modal.find('#siba_lead_national_code').val() || '');
        var jobGroupName = seed ? (seed.job_group_name || '') : ($modal.find('#siba_lead_job_group_id option:selected').text() || '');
        var positionName = seed ? (seed.position_name || seed.title || '') : ($modal.find('#siba_lead_position_id option:selected').text() || '');

        if ($modal.find('#siba_lead_position_id').length) {
            var selectedPosition = $modal.find('#siba_lead_position_id option:selected').text();
            if (selectedPosition && selectedPosition !== labels.select) {
                positionName = selectedPosition;
            }
        }
        if ($modal.find('#siba_lead_job_group_id').length) {
            var selectedGroup = $modal.find('#siba_lead_job_group_id option:selected').text();
            if (selectedGroup && selectedGroup !== labels.select) {
                jobGroupName = selectedGroup;
            }
        }

        var $titleDt = $col.find('dt.lead-field-heading').filter(function () {
            var text = normalizeLabel($(this).text());
            return text.indexOf('title') !== -1 || text.indexOf('سمت') !== -1 || text.indexOf('lead_title') !== -1;
        }).first();
        if ($titleDt.length) {
            $titleDt.text(labels.position);
            $titleDt.next('dd').text(positionName || '—');
        }

        function ensureViewField(key, label, value) {
            var $existing = $col.find('dt[data-siba-profile="' + key + '"]');
            if (!$existing.length) {
                var $companyDt = $col.find('dt.lead-field-heading').filter(function () {
                    var text = normalizeLabel($(this).text());
                    return text.indexOf('company') !== -1 || text.indexOf('شرکت') !== -1;
                }).first();
                if (!$companyDt.length) {
                    return;
                }
                $existing = $('<dt class="lead-field-heading tw-font-normal tw-text-neutral-500"></dt>')
                    .attr('data-siba-profile', key)
                    .text(label);
                var $dd = $('<dd class="tw-text-neutral-900 tw-mt-1"></dd>')
                    .attr('data-siba-profile-value', key);
                $companyDt.next('dd').after($dd).after($existing);
            }
            $existing.text(label);
            $col.find('[data-siba-profile-value="' + key + '"]').text(value || '—');
        }

        ensureViewField('national_code', labels.nationalCode, nationalCode);
        ensureViewField('job_group', labels.jobGroup, jobGroupName);
    }

    function injectLeadProfileFields($modal) {
        var $form = $modal.find('#lead_form');
        if (!$form.length || !canInitLeadLocationFields($modal)) {
            return;
        }

        if ($form.find('#siba_lead_national_code').length) {
            refreshLeadProfilePickers($modal);
            updateLeadProfileView($modal);
            return;
        }

        var labels = getProfileLabels();
        var seed = window.sibaLeadProfileSeed || null;
        var $titleGroup = $form.find('input[name="title"]').closest('.form-group');
        var $companyGroup = $form.find('input[name="company"]').closest('.form-group');

        if ($titleGroup.length) {
            $titleGroup.addClass('siba-hide-lead-field').hide();
            $titleGroup.find('input[name="title"]').prop('disabled', false);
        }

        var $positionGroup = $('<div class="form-group siba-lead-profile-position"></div>');
        $positionGroup.append('<label class="control-label">' + labels.position + '</label>');
        var $positionSelect = $('<select>', {
            id: 'siba_lead_position_id',
            name: 'position_id',
            class: 'form-control selectpicker',
            'data-none-selected-text': labels.select
        });
        $positionSelect.html(buildPositionOptions(seed ? seed.position_id : ''));
        $positionGroup.append($positionSelect);

        if ($titleGroup.length) {
            $positionGroup.insertAfter($titleGroup);
        } else if ($form.find('input[name="name"]').length) {
            $positionGroup.insertAfter($form.find('input[name="name"]').closest('.form-group'));
        }

        var $nationalGroup = $('<div class="form-group siba-lead-profile-national-code"></div>');
        $nationalGroup.append('<label class="control-label">' + labels.nationalCode + '</label>');
        var $nationalInput = $('<input>', {
            type: 'text',
            id: 'siba_lead_national_code',
            name: 'national_code',
            class: 'form-control',
            maxlength: 20,
            value: seed ? (seed.national_code || '') : ''
        });
        $nationalGroup.append($nationalInput);

        var $jobGroupWrap = $('<div class="form-group siba-lead-profile-job-group"></div>');
        $jobGroupWrap.append('<label class="control-label">' + labels.jobGroup + '</label>');
        var $jobGroupSelect = $('<select>', {
            id: 'siba_lead_job_group_id',
            name: 'job_group_id',
            class: 'form-control selectpicker',
            'data-none-selected-text': labels.select
        });
        $jobGroupSelect.html(buildJobGroupOptions(seed ? seed.job_group_id : ''));
        $jobGroupWrap.append($jobGroupSelect);

        if ($companyGroup.length) {
            $nationalGroup.insertAfter($companyGroup);
            $jobGroupWrap.insertAfter($nationalGroup);
        } else {
            $form.find('.col-md-6').first().append($nationalGroup).append($jobGroupWrap);
        }

        mountLocationSelectpicker($positionSelect);
        mountLocationSelectpicker($jobGroupSelect);

        $positionSelect.on('changed.bs.select.sibaProfile', function () {
            syncLeadProfileTitle($form);
            updateLeadProfileView($modal);
        });

        $jobGroupSelect.on('changed.bs.select.sibaProfile', function () {
            updateLeadProfileView($modal);
        });

        $nationalInput.on('input.sibaProfile change.sibaProfile', function () {
            updateLeadProfileView($modal);
        });

        $form.off('submit.sibaProfile').on('submit.sibaProfile', function () {
            syncLeadProfileTitle($form);
        });

        refreshLeadProfilePickers($modal);
        updateLeadProfileView($modal);
    }

    function refreshLeadProfilePickers($modal) {
        var $form = $modal.find('#lead_form');
        if (!$form.length) {
            return;
        }

        var seed = window.sibaLeadProfileSeed || null;
        var $position = $form.find('#siba_lead_position_id');
        var $jobGroup = $form.find('#siba_lead_job_group_id');
        var $national = $form.find('#siba_lead_national_code');

        if (!$position.length) {
            return;
        }

        if (seed) {
            if (!$national.val() && seed.national_code) {
                $national.val(seed.national_code);
            }
            if (!$position.val() && seed.position_id) {
                $position.selectpicker('val', String(seed.position_id));
            }
            if (!$jobGroup.val() && seed.job_group_id) {
                $jobGroup.selectpicker('val', String(seed.job_group_id));
            }
        }

        mountLocationSelectpicker($position);
        mountLocationSelectpicker($jobGroup);
        syncLeadProfileTitle($form);
    }

    function shouldApplyDefaultCountry() {
        return $('.siba-leads-kan-ban').length > 0
            && window.sibaLeadsDefaultCountryId > 0;
    }

    function applyDefaultCountry($modal) {
        if (!shouldApplyDefaultCountry()) {
            return;
        }

        var $form = $modal.find('#lead_form');
        if (!$form.length) {
            return;
        }

        var leadId = $form.find('input[name="leadid"]').val();
        if (leadId) {
            return;
        }

        var $hiddenCountry = $form.find('input[data-siba-default-country]');
        if ($hiddenCountry.length) {
            $hiddenCountry.val(String(window.sibaLeadsDefaultCountryId));
            return;
        }

        var $country = $form.find('select[name="country"], #country');
        if (!$country.length) {
            return;
        }

        $country.selectpicker('val', String(window.sibaLeadsDefaultCountryId));
        $country.selectpicker('refresh');
    }

    function hideEmptyProfileDashes($modal) {
        $modal.find('.lead-view dd').each(function () {
            var $dd = $(this);
            if ($dd.hasClass('siba-hide-lead-field')) {
                return;
            }

            var text = $dd.text().replace(/\s+/g, ' ').trim();
            var hasInteractive = $dd.find('a, .label, .tags-labels, img, input, select, button').length > 0;

            if (!hasInteractive && (text === '-' || text === '—' || text === '')) {
                $dd.addClass('siba-empty-field');
                $dd.prev('dt').addClass('siba-empty-field');
            }
        });
    }

    function enhanceLeadModal() {
        var $modal = $('#lead-modal');
        if (!$modal.length || !$modal.find('.modal-content .data, .lead-wrapper, .modal-header').length) {
            // content may be directly inside .modal-content.data
        }

        if (!$modal.find('.lead-wrapper, #lead_form, .modal-header').length) {
            return;
        }

        $modal.addClass('siba-lead-modal-ready');
        hideLeadProfileFields($modal);
        fixLeadModalStatusSourceFields($modal);
        replaceLeadLocationFields($modal);
        injectLeadProfileFields($modal);
        applyDefaultCountry($modal);
        hideEmptyProfileDashes($modal);
        bindLeadPhoneUnique($modal);
        ensureLeadModalSelectsEnabled($modal);

        // Proposals not used for now — hide tab + pane.
        $modal.find('a[href="#tab_proposals_leads"]').closest('li').addClass('siba-hide-lead-field');
        $modal.find('#tab_proposals_leads').addClass('siba-hide-lead-field');
    }

    function getLeadModalLeadId($modal) {
        $modal = $modal && $modal.length ? $modal : $('#lead-modal');
        var leadId = String($modal.find('input[name="leadid"]').val() || '').trim();
        if (leadId) {
            return leadId;
        }

        var action = String($modal.find('#lead_form').attr('action') || '');
        var match = action.match(/leads\/lead\/(\d+)/i);
        return match ? match[1] : '';
    }

    function ensureLeadIdInForm($modal) {
        // Intentionally empty: lead id must not be posted on save (not a tblleads column).
    }

    function bindLeadPhoneUnique($modal) {
        var $form = $modal.find('#lead_form');
        var $phone = $form.find('input[name="phonenumber"]');
        if (!$form.length || !$phone.length || typeof $phone.rules !== 'function') {
            return;
        }

        ensureLeadIdInForm($modal);

        if ($phone.data('sibaPhoneUnique')) {
            try {
                $phone.rules('remove', 'remote');
            } catch (e) {
                // Rule may not exist yet.
            }
            $phone.removeData('sibaPhoneUnique');
        }

        $phone.data('sibaPhoneUnique', true);
        $phone.rules('add', {
            remote: {
                url: admin_url + 'siba_leads/validate_phone',
                type: 'post',
                data: {
                    phonenumber: function () {
                        return $phone.val();
                    },
                    lead_id: function () {
                        return getLeadModalLeadId($modal);
                    }
                }
            },
            messages: {
                remote: window.sibaLeadsDuplicatePhone || 'A lead with this phone number already exists.'
            }
        });
    }

    $(document).on('click', '.siba-leads-kan-ban .new-lead-from-status', function () {
        var status_id = $(this).parents('.kan-ban-col').data('col-status-id');
        init_lead_modal_data(undefined, admin_url + 'leads/lead?status_id=' + status_id);
        $('.popover').popover('hide');
    });

    $(document).on('shown.bs.modal', '#lead-modal', function () {
        setTimeout(enhanceLeadModal, 30);
    });

    $(document).on('click', '#lead-modal [lead-edit]', function () {
        setTimeout(function () {
            var $modal = $('#lead-modal');
            if (!isLeadEditPanelOpen($modal)) {
                return;
            }
            enhanceLeadModal();
        }, 100);
    });

    $(document).on('hidden.bs.modal', '#lead-modal', function () {
        window.sibaLeadLocationSeed = null;
        window.sibaLeadProfileSeed = null;
        cleanupLocationPickerArtifacts();
    });

    function isLeadRelatedTask($scope) {
        var $root = $scope && $scope.length ? $scope : $(document);
        var relType = (
            $root.find('#rel_type').val()
            || $root.find('select[name="rel_type"]').val()
            || $root.find('input[name="rel_type"]').val()
            || ''
        ).toLowerCase();

        if (relType === 'lead') {
            return true;
        }

        // Task opened from lead modal / lead tasks list
        if ($('#lead-modal').is(':visible') && $root.closest('#_task_modal, #task-modal').length) {
            return true;
        }

        return false;
    }

    function disableTaskBillableForLeads($scope) {
        var $root = $scope && $scope.length ? $scope : $(document);
        if (!isLeadRelatedTask($root)) {
            return;
        }

        $root.find(
            '.task-add-edit-billable, .task-info-billable, .task-billable-amount, .task-hours'
        ).addClass('siba-hide-lead-field');
        $root.find('#task_is_billable, input[name="billable"]').prop('checked', false).prop('disabled', true);
        $root.find('input[name="hourly_rate"], #hourly_rate').prop('disabled', true);
    }

    function bootTaskBillableHide($scope) {
        setTimeout(function () {
            disableTaskBillableForLeads($scope);
        }, 80);
    }

    function applyLeadAssigneeToNewTask($scope) {
        var $modal = $scope && $scope.length ? $scope : $('#_task_modal');
        if (!$modal.length || $modal.hasClass('edit')) {
            return;
        }

        // Only for new task forms (assignees multi-select exists only on create).
        var $assignees = $modal.find('#assignees');
        if (!$assignees.length) {
            return;
        }

        var relType = String(
            $modal.find('#rel_type').val()
            || $modal.find('select[name="rel_type"]').val()
            || ''
        ).toLowerCase();
        var relId = parseInt($modal.find('#rel_id').val() || $modal.find('select[name="rel_id"]').val() || 0, 10);

        if (relType !== 'lead' || !relId) {
            return;
        }

        if ($modal.data('sibaLeadAssigneeAppliedFor') === relId) {
            return;
        }

        $.getJSON(admin_url + 'siba_leads/lead_assignee/' + relId)
            .done(function (res) {
                var assigned = res && res.assigned ? parseInt(res.assigned, 10) : 0;
                if (!assigned) {
                    return;
                }

                // Default to lead owner; user can still change the select.
                $assignees.selectpicker('val', [String(assigned)]);
                $modal.data('sibaLeadAssigneeAppliedFor', relId);
            });
    }

    $(document).on('shown.bs.modal', '#_task_modal', function () {
        bootTaskBillableHide($(this));
        setTimeout(function () {
            applyLeadAssigneeToNewTask($('#_task_modal'));
        }, 120);
    });

    $(document).on('shown.bs.modal', '#task-modal', function () {
        bootTaskBillableHide($(this));
    });

    $(document).on('changed.bs.select change', '#_task_modal #rel_type, #_task_modal select[name="rel_type"]', function () {
        var $modal = $(this).closest('#_task_modal');
        if (isLeadRelatedTask($modal)) {
            disableTaskBillableForLeads($modal);
        } else {
            $modal.find('.task-add-edit-billable, .task-hours').removeClass('siba-hide-lead-field');
            $modal.find('#task_is_billable, input[name="billable"], input[name="hourly_rate"], #hourly_rate')
                .prop('disabled', false);
        }
        $modal.removeData('sibaLeadAssigneeAppliedFor');
        setTimeout(function () {
            applyLeadAssigneeToNewTask($modal);
        }, 50);
    });

    $(document).on('changed.bs.select change', '#_task_modal #rel_id, #_task_modal select[name="rel_id"]', function () {
        var $modal = $(this).closest('#_task_modal');
        $modal.removeData('sibaLeadAssigneeAppliedFor');
        setTimeout(function () {
            applyLeadAssigneeToNewTask($modal);
        }, 50);
    });

    $(document).ajaxComplete(function (event, xhr, settings) {
        if (!settings || !settings.url) {
            return;
        }

        if (settings.url.indexOf('leads/lead') !== -1) {
            setTimeout(enhanceLeadModal, 40);
        }

        if (settings.url.indexOf('tasks/task') !== -1
            || settings.url.indexOf('tasks/get_task_data') !== -1
            || settings.url.indexOf('get_task_data') !== -1) {
            bootTaskBillableHide($('#_task_modal'));
            bootTaskBillableHide($('#task-modal'));
            setTimeout(function () {
                applyLeadAssigneeToNewTask($('#_task_modal'));
            }, 150);
        }
    });

    function syncLeadFormBeforeSave($modal) {
        $modal = $modal && $modal.length ? $modal : $('#lead-modal');
        var $form = $modal.find('#lead_form');
        if (!$form.length) {
            return;
        }

        ensureLeadIdInForm($modal);

        $form.find('input[name="leadid"], input[name="lead_id"]').remove();

        $form.find('select.selectpicker').each(function () {
            var $select = $(this);
            if (!$select.parent().hasClass('bootstrap-select')) {
                return;
            }
            var val = $select.selectpicker('val');
            if (val === null || val === undefined) {
                return;
            }
            $select.val(val);
        });

        syncLeadLocationHiddenFields($modal);
        syncLeadProfileTitle($form);

        $form.find('#siba_lead_province_id, #siba_lead_city_id').each(function () {
            var $select = $(this);
            var val = $select.parent().hasClass('bootstrap-select')
                ? $select.selectpicker('val')
                : $select.val();
            $select.prop('disabled', false).val(val || '');
        });
    }

    function bindSibaLeadFormSaveSync() {
        if (window.sibaLeadsFormSaveBound || typeof window.lead_profile_form_handler !== 'function') {
            return;
        }

        window.sibaLeadsFormSaveBound = true;
        var coreHandler = window.lead_profile_form_handler;

        window.lead_profile_form_handler = function (form) {
            syncLeadFormBeforeSave($('#lead-modal'));
            return coreHandler(form);
        };
    }

    function bindSibaKanbanRefreshOverride() {
        if (window.sibaLeadsKanbanOverrideBound || typeof window.leads_kanban !== 'function') {
            return;
        }

        window.sibaLeadsKanbanOverrideBound = true;
        var coreKanban = window.leads_kanban;

        window.leads_kanban = function () {
            if ($('.siba-leads-kan-ban').length && typeof window.siba_leads_kanban === 'function') {
                window.siba_leads_kanban();
                return;
            }

            return coreKanban.apply(this, arguments);
        };
    }

    $(function () {
        bindSibaKanbanRefreshOverride();

        if (typeof window.lead_profile_form_handler === 'function') {
            bindSibaLeadFormSaveSync();
            return;
        }

        var attempts = 0;
        var waitForLeadHandler = window.setInterval(function () {
            attempts += 1;
            if (typeof window.lead_profile_form_handler === 'function') {
                window.clearInterval(waitForLeadHandler);
                bindSibaLeadFormSaveSync();
            } else if (attempts > 30) {
                window.clearInterval(waitForLeadHandler);
            }
        }, 100);
    });
})();
