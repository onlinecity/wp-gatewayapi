jQuery(function($) {

    function initialize()
    {
        improvedNumberFieldMno();
        handleSendVerificationSms();
        handleConfirmVerificationSms();
    }

    function handleAjaxFailure(res)
    {
        $('#login_error').remove();

        if (res && res.responseJSON) {
            res = res.responseJSON;

            // special handling of certain error codes
            switch(res.code) {
                case 'FATAL_SECURITY':
                case 'TOO_MANY_ATTEMPTS':
                case 'EXPIRED':
                    $('.step.current').empty(); // fatal type, so clear all!
                    break;
            }

            // add error message
            $('.step.current').prepend( $('<div id="login_error">').html( res.message ) );

            // reset form and get back to normal
            $('#wp-submit').btnloading(false);

        } else {
            handleAjaxFailure({responseJSON: {code: 'TECH', message: GWAPI_I18N.ajax_tech_error }});
        }
    }

    /**
     * Ensures only numbers can be entered in MNO-field and ensures the spinner-controls are hidden and arrow keys
     * cannot trigger spins neither.
     */
    function improvedNumberFieldMno() {
        // force: only numbers in gwapi_mno-fields
        $('body').on('keyup change', 'input[type=number]', function() {
            var orig = $(this).val();
            var cleaned = orig.replace(/[^0-9]+/g,'');
            if (cleaned == orig) return;
            $(this).val(cleaned);
        });

        // Disable scroll when focused on a number input.
        $('body').on('focus', 'input[type=number]', function(e) {
            $(this).on('wheel', function(e) {
                e.preventDefault();
            });
        });

        // Restore scroll on number inputs.
        $('body').on('blur', 'input[type=number]', function(e) {
            $(this).off('wheel');
        });

        // Disable up and down keys.
        $('body').on('keydown', 'input[type=number]', function(e) {
            if ( e.which == 38 || e.which == 40 ) e.preventDefault();
        });
    };

    /**
     * When adding a phone number, verify it via an AJAX
     */
    function handleSendVerificationSms()
    {
        $('#gwapi_add_phone_form').submit(function(ev) {
            ev.preventDefault();
            var submit_btn = $(this).find('input[type=submit]');
            submit_btn.btnloading(true);

            $.post(GWAPI_ADMINURL+'admin-ajax.php?action=gwapi_security_add_phone', $(this).serialize(), function(res) {
                if (!res.success) return handleAjaxFailure(res.code && res.message ? res : null);

                $('.step.current').html(res.html);
            }).fail(handleAjaxFailure);
        });
    }

    /**
     * When confirming the code.
     */
    function handleConfirmVerificationSms()
    {
        $('body').on('submit', '#gwapi_confirm_phone_form', function(ev) {
            ev.preventDefault();

            $.post(GWAPI_ADMINURL+'admin-ajax.php?action=gwapi_security_confirm_phone', $(this).serialize(), function(res) {
                $('.step.current').html(res.html);
            }).fail(handleAjaxFailure);
        });
    }

    /**
     * Helper: Disables submit button and shows the loading text from data-loading-arg if arg is true.
     * Reverts back, if arg is false.
     *
     * Only works for input[type=submit], not buttons!
     *
     * @param should_be_loading
     */
    $.fn.btnloading = function(should_be_loading) {
        $(this).each(function() {

            var was_loading = $(this).prop('disabled');
            if (was_loading && should_be_loading) return;
            if (!was_loading && !should_be_loading) return;

            var submit_btn = $(this).prop('disabled', should_be_loading);

            if (should_be_loading) {
                submit_btn.data('orig', submit_btn.val());
                if (submit_btn.data('loading')) submit_btn.val(submit_btn.data('loading'));
            } else {
                if (submit_btn.data('orig')) submit_btn.val(submit_btn.data('orig'));
            }

        });
    };

    initialize();
});