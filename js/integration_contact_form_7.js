jQuery(function($) {

    var actionEl = null;
    function initialize()
    {

        // find the action-tag
        actionEl = $('.wpcf7-form input[name="gwapi_action"]');

        // requires verify?
        if (!actionEl.data('verify')) return;

        var action = actionEl.val();
        if (action == 'update') handleUpdate();
        if (action == 'signup') handleSignup();

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
                $('.wpcf7-response-output').text('You must supply both country code and phone number in order to continue.').addClass('wpcf7-validation-errors').show();
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
                var code = window.prompt("We have just sent you an SMS with a verification code. Please enter it below:");
                if (!code) {
                    return window.alert("You did not enter a code. It is not possible for you to continue.");
                }

                $.post(gwapi_admin_ajax, {action: 'gwapi_verify_sms', 'cc': cc, 'number': mobile, 'code': code}).done(function(res) {
                    if (!res.success) {
                        return window.alert("You did not enter the code correctly. Please try again.");
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

            if (res.gwapi_verify && res.gwapi_prompt) {
                var innerF = $('.wpcf7-response-output.wpcf7-display-none.wpcf7-mail-sent-ng').hide();
                var form = innerF.closest('form');
                function enterVerifyCode() {
                    var code = window.prompt(res.gwapi_prompt);
                    if (!code) {
                        if (!window.confirm('Not entering the code will cancel the signup. If this is what you want, then click "Ok". Clicking cancel will allow you to try and enter the code again.')) {
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