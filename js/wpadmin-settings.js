/**
 * Settings.
 */
jQuery(function($) {

    var outer = $('#wpbody-content');

    function initialize()
    {
        handleTooltips();
        handleTabs();
        handleToggleRecipientFormTab();


        handleRecipientFieldSorting();
        handleRecipientTypeCriteria();
        handleRecipientTypeCriteriaMobileCc();
        handleRecipientFieldDelete();
        limitRecipientFieldKey();

        recipientPrepareTemplate();

        handleRecipientFieldsReset();
        handleRecipientFieldAddRow();

        handleShortcodeGenerator();
    }

    function handleTooltips()
    {
        $('.has-tooltip').tooltip({
            show: false,
            hide: false
        });
    }

    function handleTabs()
    {
        var tabs = outer.find('.nav-tab-wrapper > a').click(function(ev) {
            $(this).parent().children().removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            var inners = $('.tab-inner').children().addClass('hidden');
            inners.filter('[data-tab="'+ $(this).attr('href').substring(1) +'"]').removeClass('hidden');

            window.sessionStorage.setItem('gwapi-options-last-tab', $(this).attr('href'));
        });

        var open_tab = window.location.hash || window.sessionStorage.getItem('gwapi-options-last-tab');
        if (open_tab) {
            tabs.filter('[href="'+open_tab+'"]').click();
        } else {
            tabs.filter('[href="#base"]').click();
        }
    }

    function handleToggleRecipientFormTab()
    {
        var checkbox = outer.find('input[name="gwapi_enable_ui"]');
        var tabs = outer.find('a[href="#recipients-fields"], a[href="#build-shortcode"]');
        var inner = outer.find('.tab-inner .tab').filter('[data-tab="recipients-fields"], [data-tab="build-shortcode"]');

        checkbox.change(function() {
            if ($(this).is(':checked')) {
                tabs.removeClass('hidden');
                $('#enableCaptcha').removeClass('hidden');
            } else {
                tabs.addClass('hidden');
                $('#enableCaptcha').addClass('hidden');
            }
        }).change();
        
        // if changing TO another tab AFTER enabling sending UI, submit form
        if (!checkbox.is(':checked')) {
          tabs.click(function() { $('#submit').click(); });
        }
    }

    function handleRecipientFieldSorting()
    {
        $('.recipient-fields').sortable({
            handle: '.drag-handle',
            opacity: 0.9,
            axis: 'y'
        });
    }

    function handleRecipientTypeCriteria()
    {
        var select_selector = 'select[name="gwapi_recipient_fields[type][]"]';

        var outer = $('.recipient-fields').on('change', select_selector, function() {
            var field_group = $(this).parents('.field-group');
            var fields = field_group.find('.form-field[data-visible_on]').addClass('hidden');
            var cur_type = $(this).val();

            // must show on
            fields.each(function() {
                var visible_on = $(this).data('visible_on').split(/,/g);
                var is_visible = false;
                $.each(visible_on, function(idx, val) {
                    if (val == cur_type) is_visible = true;
                });
                if (is_visible) {
                    $(this).removeClass('hidden');
                }
            });

            // must hide on
            var fields_hide = field_group.find('.form-field[data-hidden_on]').removeClass('hidden');
            fields_hide.each(function() {
                var hidden_on = $(this).data('hidden_on').split(/,/g);
                var is_hidden = true;
                $.each(hidden_on, function(idx, val) {
                    if (val == cur_type) is_hidden = true;
                });
                if (is_hidden) {
                    $(this).addClass('hidden');
                }

            });
        });

        // trigger initially
        outer.find(select_selector).change();
    }

    function handleRecipientTypeCriteriaMobileCc()
    {
        $('.recipient-fields').on('change keyup', 'textarea[name="gwapi_recipient_fields[mobile_cc_countries][]"]', function() {
            var prev_value = $(this).data('prev-value');
            var new_value = $(this).val();
            if (prev_value != new_value) {
                new_value = new_value.replace(/[^\d\n]+/g, '');
                $(this).data('prev-value', new_value);
                $(this).val(new_value);
            }
        });
    }

    function handleRecipientFieldDelete()
    {
        $('.recipient-fields').on('click', 'button[data-delete]', function() {
            $(this).parents('.recipient-field').remove();
        });
    }

    /**
     * Make sure templates does not get submitted.
     */
    function recipientPrepareTemplate()
    {
        $('.recipient-fields .recipient-field[data-is-template]').each(function() {
           $(this).find('select,input,textarea').prop('disabled', true);
        });
    }

    /**
     * Field key may only be uppercase alpha-numeric + _ and -.
     */
    function limitRecipientFieldKey()
    {
        $('.recipient-fields').on('change keyup', '[name="gwapi_recipient_fields[field_id][]"]', function() {
            var val = $(this).val();
            var prev_val = $(this).data('prev-val');
            if (prev_val != val) {
                val = val.replace(/ /g,'_');
                val = val.toUpperCase().replace(/[^A-Z0-9_-]/g,'');
                $(this).data('prev-val', val).val(val);
            }
        });
    }

    function handleRecipientFieldsReset()
    {
        $('#recipientsTab').children('button[data-reset-btn]').click(function() {
            if (window.confirm($(this).data('warning'))) {
                $('.recipient-fields').empty();
                $('#submit').click();
            }
        });
    }

    function handleRecipientFieldAddRow()
    {
        $('#recipientsTab').children('button[data-add-btn]').click(function() {
            var fields = $('.recipient-fields');
            var row = fields.children('[data-is-template]').clone();
            row.removeClass('hidden').removeAttr('data-is-template');
            row.find('input,textarea,select').prop('disabled', false);
            row.appendTo(fields);
        });
    }

    function handleShortcodeGenerator()
    {
        var outer = $('#buildShortcodeTab');

        var updateShortcode = function() {
            var action = outer.find('[name="action"]:checked').val();

            // send sms enables more UI
            if (action == 'send_sms') {
                $('#shortcodeSendSms').removeClass('hidden');
                $('#captcha').addClass('hidden');
            }
            else {
                $('#shortcodeSendSms').addClass('hidden');
                $('#captcha').removeClass('hidden');
            }

            // unsubscribe hides a lot
            if (action == 'unsubscribe') $('#shortcodeGroups').addClass('hidden');
            else $('#shortcodeGroups').removeClass('hidden');


            // generate the shortcode
            var ss = '[gwapi action="'+action+'"';

            // groups?
            var groups = outer.find('> :not(.hidden) [name=groups]').val();
            if (groups && groups.length) {
                ss += ' groups="'+groups.join(',')+'"'
            }

            // editable groups?
            if (outer.find('> :not(.hidden) [name=editable]').is(':checked')) {
                ss += ' edit-groups=1';
            }

            // send sms?
            if (action == 'send_sms') {
                if (outer.find('> :not(.hidden) [name=sender]').is(':checked')) ss += ' edit-sender=1';
            }

            // reCAPTCHA?
            if (outer.find('> :not(.hidden) [name="recaptcha"]').is(':checked')) {
                ss += ' recaptcha=1'
            }

            ss +=']';
            outer.find('#final_shortcode').text(ss);
        };

        outer.on('change', 'input,select,textarea', updateShortcode);

        outer.parents('form').submit(function() {
            var fields = outer.find('input,textarea,select').prop('disabled', true);
            setTimeout(function() {
                field.prop('disabled', false);
            }, 1000);
        });
    }

    initialize();

});