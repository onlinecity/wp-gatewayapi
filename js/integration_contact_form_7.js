jQuery(function($) {

    var actionEl = null;
    function initialize()
    {

        // find the action-tag
        actionEl = $('.wpcf7-form input[name="gwapi_action"]');

        // requires verify?
        if (actionEl.data('verify')) {
            var action = actionEl.val();
            if (action == 'update') handleUpdate();
            if (action == 'signup') handleSignup();
        }

        // admin form: sms reply
        handleEditorSmsReply();
    }

    function handleEditorSmsReply()
    {
        var panel = $('#sms-reply-panel.contact-form-editor-panel');
        if (!panel.length) return;

        var updateVisibleFieldsFn = function() {
            var isVisible = $(this).is(':checked');
            var shouldBeToggled = panel.find('.only-show-on-enabled-sms-reply');
            if (isVisible) shouldBeToggled.show();
            else shouldBeToggled.hide();
        };

        var replyEnableSel = 'input[name="_gwapi_form_settings[reply-enable]"]';
        panel.on('change', replyEnableSel, updateVisibleFieldsFn);
        panel.find(replyEnableSel).each(updateVisibleFieldsFn);
    }

    function handleUpdate()
    {
        var origSubmitHandler = null;

        // hide all other fields but cc and number
        var hiddens=[];
        $('.wpcf7-form-control-wrap').each(function() {
            if ($(this).find('input,select').attr('name').substring(0,6) != 'gwapi_') {
                var nodeNameParent = $(this).parent().prop('nodeName');
                var hidden = $(this);
                if (nodeNameParent != 'form') hidden = $(this).parent();

                hidden.hide();
                hiddens.push(hidden);
            }
        });

        // hide original submit-button
        var origSubmit = $('.wpcf7-form-control.wpcf7-submit').prop('disabled', true).hide();
        hiddens.push(origSubmit);

        // insert own submit-button instead
        var newSubmit = $('<input type="button" class="wpcf7-form-control wpcf7-button">').val('Log in');
        newSubmit.insertAfter(origSubmit);

        // on success, update the form, hide own submit and insert real submit
        newSubmit.click(function() {
            var cc = $('[name="gwapi_country"]').val();
            var mobile = $('[name="gwapi_phone"]').val();
            if (!cc || !mobile) {
                $('.wpcf7-response-output').text(i18n_gwapi_cf7.country_and_cc).addClass('wpcf7-validation-errors').show();
                return false;
            }

            // going to ajax...
            $('.wpcf7-response-output').text('').removeClass('wpcf7-validation-errors').hide();

            $(this).val('Verifying...').prop('disabled', true);
            $.post(gwapi_admin_ajax, { action: 'gwapi_send_verify_sms', 'cc': cc, 'number': mobile }).done(function(res) {
                $(this).val('Log in').prop('disabled', false);

                if (!res.success) {
                    $('.wpcf7-response-output').text(res.message).addClass('wpcf7-validation-errors').show();
                    return;
                }

                // success! ask for SMS code
                var code = window.prompt(i18n_gwapi_cf7.verification_sms_sent);
                if (!code) {
                    return window.alert(i18n_gwapi_cf7.no_code_entered);
                }

                $.post(gwapi_admin_ajax, {action: 'gwapi_verify_sms', 'cc': cc, 'number': mobile, 'code': code}).done(function(res) {
                    if (!res.success) {
                        return window.alert(i18n_gwapi_cf7.bad_code);
                    }

                    // inject the verification token into the form
                    $('<input type="hidden" name="_gwapi_token" value="'+code+'">').insertAfter(actionEl);

                    // mark the phone and country code fields readonly
                    $('[name="gwapi_phone"]').prop('readonly', true);
                    var gwapiCountryEl = $('[name="gwapi_country"]').prop('disabled', true);
                    $('<input type="hidden" name="gwapi_country">').val(cc).insertAfter(gwapiCountryEl);

                    // remove our submit and re-insert all hidden fields and re-enable proper submit
                    newSubmit.remove();
                    $.each(hiddens, function(idx, el) {
                        el.show();
                    });

                    origSubmit.prop('disabled', false);

                    // for the rest of the fields, update with the current value
                    $.each(res.recipient, function(key, val) {
                        var el = $('.wpcf7-form [name="'+key+'"], .wpcf7-form [name="'+key+'[]"]');
                        if (Array.isArray(val)) {
                            el.prop('checked', false);
                            $.each(val, function(key2, val2) {
                                el.filter('[value="'+val2+'"]').prop('checked', true);
                            });
                        } else {
                            el.val(val);
                        }
                    });
                });
            });
        });
    }

    function handleSignup()
    {
        $(document).ajaxSuccess(function(event, xhr, settings) {
            if (!settings.extraData._wpcf7_is_ajax_call) return; // wrong call

            var res = xhr.responseJSON;
            if (!res) return; // wrong format

            var innerF = $('.wpcf7-response-output.wpcf7-display-none.wpcf7-mail-sent-ng');
            var form = innerF.closest('form');

            if (res.spam_trap_resolve) {
                if (!form.find('.gwapi-spam-trap-resolve').length) {
                    form.append($('<input type="hidden" name="gwapi_spam_trap_resolve" class="gwapi-spam-trap-resolve">').val(res.spam_trap_resolve));
                } else {
                    $('.gwapi-spam-trap-resolve').val(res.spam_trap_resolve);
                }
            }

            if (res.gwapi_verify && res.gwapi_prompt) {
                innerF.hide();
                function enterVerifyCode() {
                    var code = window.prompt(res.gwapi_prompt);
                    if (!code) {
                        if (window.confirm(i18n_gwapi_cf7.no_code_try_again)) {
                            enterVerifyCode();
                        }
                    } else {
                        // do we have an input field for the code?
                        var inputCode = form.find('input[name="_gwapi_verify_signup"]');
                        if (!inputCode.length) {
                            $('<input name="_gwapi_verify_signup" type="hidden">').appendTo($('.wpcf7-form'));
                        }
                        inputCode = form.find('input[name="_gwapi_verify_signup"]');
                        inputCode.val(code);
                        form.submit();
                    }
                }
                enterVerifyCode();

            } else if (res.gwapi_error) {
                $('.wpcf7-response-output.wpcf7-display-none.wpcf7-mail-sent-ng').text(res.gwapi_error);
            }
        });
    }

    initialize();

});