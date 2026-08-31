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
        hideEmptyProfileDashes($modal);
        bindLeadPhoneUnique($modal);

        // Proposals not used for now — hide tab + pane.
        $modal.find('a[href="#tab_proposals_leads"]').closest('li').addClass('siba-hide-lead-field');
        $modal.find('#tab_proposals_leads').addClass('siba-hide-lead-field');
    }

    function bindLeadPhoneUnique($modal) {
        var $form = $modal.find('#lead_form');
        var $phone = $form.find('input[name="phonenumber"]');
        if (!$form.length || !$phone.length || typeof $phone.rules !== 'function') {
            return;
        }
        if ($phone.data('sibaPhoneUnique')) {
            return;
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
                        return $modal.find('input[name="leadid"]').val() || '';
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
        setTimeout(enhanceLeadModal, 30);
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
})();
