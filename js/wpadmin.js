jQuery(function ($) {

    function initialize() {
        var body = $('body');
        var has_send_ui = body.hasClass('post-type-gwapi-sms');
        var has_recipient_ui = body.hasClass('post-type-gwapi-recipient');
        if (!has_send_ui && !has_recipient_ui) return; // bail early

        loadCountryCodes();

        if (has_send_ui) {
            smsLengthCounter();
            validateSmsSendOnPublish();
            handlePickRecipientTypes();
            handleMoveRecipientGroupsAround();
            loadOldData();
            handleAddSingleRecipient();
            handleRemoveSingleRecipient();
            lockdownOnPublish();
        }

        if (has_recipient_ui) {
            validateSmsRecipientOnPublish();
            addFormFieldTooltips();
        }
    }

    /**
     * Render a list of country codes into the CC-fields on the page.
     */
    function loadCountryCodes() {
        $('select[name="gwapi[cc]"], #recipient_cc').gwapiMobileCc();
    }

    /**
     * Count the length of the current SMS.
     */
    function smsLengthCounter() {
        var textarea = $('textarea[name="gwapi[message]"]');
        if (!textarea.length) return;

        var countEl = $('<div class="sms-length-counter">').insertAfter(textarea);

        function doCount() {
            var chars = textarea.val().length;
            var smses = Math.ceil(chars / 160);
            if (smses == 0) smses = 1;
            countEl.text(chars + ' characters (' + smses + ' sms)');
        }

        textarea.keyup(doCount);
        doCount();
    }


    /**
     * Validate the current submission, via AJAX.
     */
    function validateSmsSendOnPublish() {
        var do_real_submit = false;

        function resetField() {
            $(this).parents('tr').removeClass('gwapi-field-error-trs');
            $(this).parents('td').find('.gwapi-field-error-message').remove();
            if (!$('.gwapi-field-error-message').length) $('#poststuff .gwapi-notice').remove();
        }

        $('#publish').click(function () {
            if (do_real_submit) return;
            console.log('fisk fisk fisk');

            $('#poststuff .gwapi-notice, .gwapi-field-error-message').remove();
            $('tr.gwapi-field-error').removeClass('gwapi-field-error-tr');

            var form_data = $('#post').serialize();
            var data = {
                action: 'gatewayapi_validate_sms',
                form_data: form_data
            };
            $.post(ajaxurl, data, function (response) {
                jQuery('#ajax-loading').hide();
                jQuery('#publish').removeClass('button-primary-disabled');

                if (response.success) {
                    do_real_submit = true;
                    $('#publish').click();
                    return true;
                } else {
                    return handleValidationError(response);
                }
            });
            return false;
        });

    }


    function validateSmsRecipientOnPublish() {
        var do_real_submit = false;

        function resetField() {
            $(this).parents('tr').removeClass('gwapi-field-error-trs');
            $(this).parents('td').find('.gwapi-field-error-message').remove();
            if (!$('.gwapi-field-error-message').length) $('#poststuff .gwapi-notice').remove();
        }

        function handleSubmit() {
            if (do_real_submit) return;

            $('#poststuff .gwapi-notice, .gwapi-field-error-message').remove();
            $('tr.gwapi-field-error').removeClass('gwapi-field-error-tr');

            var form_data = $('#post').serialize();
            var data = {
                action: 'gatewayapi_validate_recipient',
                form_data: form_data
            };
            $.post(ajaxurl, data, function (response) {
                jQuery('#ajax-loading').hide();
                jQuery('#publish').removeClass('button-primary-disabled');

                if (response.success) {
                    do_real_submit = true;
                    return $('#publish').click();
                } else {
                    handleValidationError(response);
                }
            });
            return false;
        }

        $('#post').submit(handleSubmit);
        $('#publish').click(handleSubmit);

    }

    /**
     * Handler of AJAX validation error responses.
     *
     * @param response
     * @returns {boolean}
     */
    function handleValidationError(response) {
        $('#poststuff').prepend(
            $('<div class="gwapi-notice error"><p>'+GWAPI_I18N_DEFAULT_ERROR+'</p></div>')
        );

        _.each(response.failed, function (msg, field) {
            var el = $('[name="gwapi[' + field + ']"]');
            if (field === '*') {
                $('.gwapi-star-errors').empty().append(
                    $('<div class="gwapi-field-error-message">').text(msg)
                );
                return;
            }

            el.parents('tr').addClass('gwapi-el-error-trs')
            el.parents('td').prepend($('<div class="gwapi-field-error-message">').text(msg));
            el.keyup(resetField).change(resetField);
        });

        return false;
    }

    /**
     * On sending UI, handle selecting a recipient type.
     */
    function handlePickRecipientTypes()
    {
        var outer = $('#sms-recipients');

        outer.on('change', 'input', function() {
           if ($(this).is(':checked')) { // show

               $('#' + $(this).data('group')).show();
           } else { // hide
               $('#' + $(this).data('group')).hide();
           }
        });
    }

    /**
     * Handle moving the recipient groups around on selection.
     */
    function handleMoveRecipientGroupsAround()
    {
        var outer = $('#sms-recipient-groups');

        var all = outer.find('.recipient-groups .all-groups .inner');
        var selected = outer.find('.recipient-groups .selected-groups .inner');

        outer.find('.recipient-groups input:checkbox').change(function() {
           if ($(this).is(':checked')) {
               $(this).parents('label').appendTo(selected)
           } else {
               $(this).parents('label').appendTo(all)
           }
        });
    }

    /**
     * Load old SMS data.
     */
    function loadOldData()
    {
        // recipient types
        var recipientTypesEl = $('.recipient-types');
        var selected_types = recipientTypesEl.data('selected_types') || [];
        _.each(selected_types, function(t) {
            recipientTypesEl.find('[value="'+t+'"]').prop('checked', true).trigger('change');
        });
        
        // recipient groups
        var groupsEl = $('.recipient-groups');
        var selected_groups = groupsEl.data('selected_groups') || [];
        _.each(selected_groups, function(t) {
            groupsEl.find('[value="'+t+'"]').prop('checked', true).trigger('change');
        });
    }

    /**
     * Add single recipient.
     */
    function handleAddSingleRecipient()
    {
        var outer = $('#sms-recipient-manual');

        function doAddTheSingleRecipient(metaID)
        {
            var tbody = outer.find('table tbody');
            tbody.find('.empty_row').hide();

            var ccEl = outer.find('select[name="gwapi[single_recipient][cc]"]');
            var numberEl = outer.find('input[name="gwapi[single_recipient][number]"]');
            var nameEl = outer.find('input[name="gwapi[single_recipient][name]"]');

            var cc = ccEl.val();
            var number = numberEl.val();
            var name = nameEl.val();

            numberEl.val('');
            nameEl.val('');

            var tr = $('<tr>').data('meta_id', metaID);
            tr.append($('<td>').append('<a href="#delete" class="delete-btn">'));
            tr.append($('<td>').text('+' + cc+' '+number));
            tr.append($('<td>').text(name));
            tbody.append(tr);

            numberEl.focus();
        }

        outer.find('button.add_single_recipient').click(function(ev) {
            ev.preventDefault();

            outer.find('.gwapi-star-errors').empty();
            outer.find('.gwapi-field-error-message').remove();

            // serialize the data we need
            var data = $('#sms-recipient-manual input, #sms-recipient-manual select, #post_ID').serialize();

            // send via ajax
            $.post(ajaxurl+'?action=gatewayapi_sms_manual_add_recipient', data, function(res) {

                if (res.success) {
                    return doAddTheSingleRecipient(res.ID);
                }

                // fail :-(
                _.each(res.errors, function (msg, field) {
                    var el = outer.find('[name="gwapi[single_recipient][' + field + ']"]');
                    if (field === '*') {
                        outer.find('.gwapi-star-errors').empty().append(
                            $('<div class="gwapi-field-error-message">').text(msg)
                        );
                        return;
                    }

                    $('<div class="gwapi-field-error-message">').text(msg).insertAfter(el.parents('.field-group').children('label'));
                });
            });
        });
    }

    /**
     * Remove a single recipient.
     */
    function handleRemoveSingleRecipient()
    {
        var outer = $('#sms-recipient-manual');

        outer.on('click', 'tbody .delete-btn', function(ev) {
            ev.preventDefault();
            var tr = $(this).parents('tr');
            var metaID = tr.data('meta_id');
            tr.addClass('deleting');
            $.post(ajaxurl+'?action=gatewayapi_sms_manual_delete_recipient', { post_ID: $('#post_ID').val(), 'meta_ID': metaID }, function(res) {
                if (!res.success) {
                    return window.alert(res.message);
                }
                tr.fadeOut(function() {
                    tr.remove();
                    if (outer.find('tbody').children().length == 1) {
                        outer.find('.empty_row').show();
                    }
                });
            });
        });
    }

    function lockdownOnPublish() {
        if ($('#post_type').val() != 'gwapi-sms') return;
        if ($('#original_post_status').val() != 'publish') return;

        var inner = $('#poststuff');
        inner.find('input,button,select,label,a').prop('readonly', true).css('pointer-events', 'none');
    }

    function addFormFieldTooltips() {
        $('#custom_fields').find('.info.hidden').each(function() {
            if (!$.trim($(this).text())) return;
            var i = $('<i class="info has-tooltip">').attr({'title': $(this).text()});
            i.insertAfter($(this));
            i.tooltip({show: false, hide: false});
        });
    }


    initialize();

});