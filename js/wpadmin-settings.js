/**
 * Settings.
 */
jQuery(function ($) {

  var outer = $('#wpbody-content');

  function initialize() {
    handleTooltips();
    handleTabs();

    handleToggleEnableUI();
    handleToggleSecurityUI();

    handleUserSynchronization();
    handleReceiveSms();

    handleRecipientFieldSorting();
    handleRecipientTypeCriteria();
    handleRecipientTypeCriteriaMobileCc();
    handleRecipientFieldDelete();
    limitRecipientFieldKey();

    recipientPrepareTemplate();

    handleRecipientFieldsReset();
    handleRecipientFieldAddRow();

    handleShortcodeGenerator();

    handleOneTimeUserSync();

    handleSecurityTab();
  }

  function handleTooltips() {
    $('.has-tooltip').tooltip({
      show: false,
      hide: false
    });
  }

  function handleTabs() {
    var tabs = outer.find('.nav-tab-wrapper > a').click(function (ev) {
      $(this).parent().children().removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');

      var inners = $('.tab-inner').children().addClass('hidden');
      inners.filter('[data-tab="' + $(this).attr('href').substring(1) + '"]').removeClass('hidden');

      window.sessionStorage.setItem('gwapi-options-last-tab', $(this).attr('href'));
    });

    var open_tab = window.location.hash || window.sessionStorage.getItem('gwapi-options-last-tab');
    if (open_tab) {
      tabs.filter('[href="' + open_tab + '"]').click();
    } else {
      tabs.filter('[href="#base"]').click();
    }
  }

  function handleToggleEnableUI() {
    var checkbox = outer.find('input[name="gwapi_enable_ui"]');
    var tabs = outer.find('a[href="#recipients-fields"], a[href="#build-shortcode"], a[href="#user-sync"], a[href="#sms-inbox"], a[href="#notifications"]');

    checkbox.change(function () {
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
      tabs.click(function () {
        $('#submit').click();
      });
    }
  }

  function handleToggleSecurityUI() {
    var checkbox = outer.find('input[name="gwapi_security_enable"]');
    var tabs = outer.find('a[href="#security"]');

    checkbox.change(function () {
      if ($(this).is(':checked')) {
        tabs.removeClass('hidden');
      } else {
        tabs.addClass('hidden');
      }
    }).change();

    // if changing TO another tab AFTER enabling sending UI, submit form
    if (!checkbox.is(':checked')) {
      tabs.click(function () {
        $('#submit').click();
      });
    }
  }

  function handleRecipientFieldSorting() {
    $('.recipient-fields').sortable({
      handle: '.drag-handle',
      opacity: 0.9,
      axis: 'y'
    });
  }

  function handleRecipientTypeCriteria() {
    var select_selector = 'select[name="gwapi_recipient_fields[type][]"]';

    var outer = $('.recipient-fields').on('change', select_selector, function () {
      var field_group = $(this).parents('.field-group');
      var fields = field_group.find('.form-field[data-visible_on]').addClass('hidden');
      var cur_type = $(this).val();

      // must show on
      fields.each(function () {
        var visible_on = $(this).data('visible_on').split(/,/g);
        var is_visible = false;
        $.each(visible_on, function (idx, val) {
          if (val == cur_type) is_visible = true;
        });
        if (is_visible) {
          $(this).removeClass('hidden');
        }
      });

      // must hide on
      var fields_hide = field_group.find('.form-field[data-hidden_on]').removeClass('hidden');
      fields_hide.each(function () {
        var hidden_on = $(this).data('hidden_on').split(/,/g);
        var is_hidden = true;
        $.each(hidden_on, function (idx, val) {
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

  function handleRecipientTypeCriteriaMobileCc() {
    $('.recipient-fields').on('change keyup', 'textarea[name="gwapi_recipient_fields[mobile_cc_countries][]"]', function () {
      var prev_value = $(this).data('prev-value');
      var new_value = $(this).val();
      if (prev_value != new_value) {
        new_value = new_value.replace(/[^\d\n]+/g, '');
        $(this).data('prev-value', new_value);
        $(this).val(new_value);
      }
    });
  }

  function handleRecipientFieldDelete() {
    $('.recipient-fields').on('click', 'button[data-delete]', function () {
      $(this).parents('.recipient-field').remove();
    });
  }

  /**
   * Make sure templates does not get submitted.
   */
  function recipientPrepareTemplate() {
    $('.recipient-fields .recipient-field[data-is-template]').each(function () {
      $(this).find('select,input,textarea').prop('disabled', true);
    });
  }

  /**
   * Field key may only be uppercase alpha-numeric + _ and -.
   */
  function limitRecipientFieldKey() {
    $('.recipient-fields').on('change keyup', '[name="gwapi_recipient_fields[field_id][]"]', function () {
      var val = $(this).val();
      var prev_val = $(this).data('prev-val');
      if (prev_val != val) {
        val = val.replace(/ /g, '_');
        val = val.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
        $(this).data('prev-val', val).val(val);
      }
    });
  }

  function handleRecipientFieldsReset() {
    $('#recipientsTab').children('button[data-reset-btn]').click(function () {
      if (window.confirm($(this).data('warning'))) {
        $('.recipient-fields').empty();
        $('#submit').click();
      }
    });
  }

  function handleRecipientFieldAddRow() {
    $('#recipientsTab').children('button[data-add-btn]').click(function () {
      var fields = $('.recipient-fields');
      var row = fields.children('[data-is-template]').clone();
      row.removeClass('hidden').removeAttr('data-is-template');
      row.find('input,textarea,select').prop('disabled', false);
      row.appendTo(fields);
    });
  }

  function handleShortcodeGenerator() {
    var outer = $('#buildShortcodeTab');

    var updateShortcode = function () {
      var action = outer.find('[name="action"]:checked').val();

      // send sms enables more UI
      if (action == 'send_sms') {
        $('#shortcodeSendSms').removeClass('hidden');
        $('#captcha').addClass('hidden');
      } else {
        $('#shortcodeSendSms').addClass('hidden');
        $('#captcha').removeClass('hidden');
      }

      // unsubscribe hides a lot
      if (action == 'unsubscribe') $('#shortcodeGroups').addClass('hidden');
      else $('#shortcodeGroups').removeClass('hidden');


      // generate the shortcode
      var ss = '[gwapi action="' + action + '"';

      // groups?
      var groups = outer.find('> :not(.hidden) [name=groups]').val();
      if (groups && groups.length) {
        ss += ' groups="' + groups.join(',') + '"'
      }

      // editable groups?
      var select_all_outer = outer.find('.select-all-wrapper');
      var select_all = select_all_outer.find('> :not(.hidden) [name=deselect_all]');

      if (outer.find('> :not(.hidden) [name=editable]').is(':checked')) {
        ss += ' edit-groups=1';
        select_all_outer.removeClass('hidden');
      } else {
        select_all_outer.addClass('hidden');
      }

      // all selected by default?
      if (select_all.is(':checked')) {
        ss += ' groups-deselected=1';
      }

      // send sms?
      if (action == 'send_sms') {
        if (outer.find('> :not(.hidden) [name=sender]').is(':checked')) ss += ' edit-sender=1';
      }

      // reCAPTCHA?
      if (outer.find('> :not(.hidden) [name="recaptcha"]').is(':checked')) {
        ss += ' recaptcha=1'
      }

      ss += ']';
      outer.find('#final_shortcode').text(ss);
    };

    outer.on('change', 'input,select,textarea', updateShortcode);

    outer.parents('form').submit(function () {
      var fields = outer.find('input,textarea,select').prop('disabled', true);
      setTimeout(function () {
        field.prop('disabled', false);
      }, 1000);
    });
  }

  function handleUserSynchronization() {
    // the enable/disable everything toggle
    var outer = $('#userSync');
    outer.find('#userSyncEnableCb').change(function () {
      if ($(this).is(':checked')) {
        $('#userSyncEnabled').removeClass('hidden').find('input,textarea').prop('disabled', false);
      } else {
        $('#userSyncEnabled').addClass('hidden').find('input,textarea').prop('disabled', true);
      }
    }).change();

    // country code
    let fieldDefaultCC = $('select[name="gwapi_user_sync_meta_default_countrycode"]');
    let defaultCountryCode = fieldDefaultCC.data('default-cc') || 45;
    $('select[name="gwapi_user_sync_meta_default_countrycode"]').gwapiMobileCc({"default_cc": defaultCountryCode});
  }

  function handleReceiveSms() {
    $('#receiveSmsEnabled input[name="gwapi_receive_sms_url"]').click(function (ev) {
      $(this).select && $(this).select();
    });
  }

  function handleOneTimeUserSync() {
    $('body').on('change', '#gwapiSynchronizeOnNextLoad', function () {
      sessionStorage.setItem('gwapiSynchronizeOnNextLoad', $(this).is(':checked') ? '1' : '');
    });

    var finishUserSync = function () {
      $('#isSyncingStatus').parent().removeClass('notice-info').addClass('notice-success');
    };

    var doSync = sessionStorage.getItem('gwapiSynchronizeOnNextLoad') == '1';
    if (doSync) {
      sessionStorage.removeItem('gwapiSynchronizeOnNextLoad');

      $('<div class="notice notice-info"><p id="isSyncingStatus"></p></div>').insertBefore($('#userSyncEnabled'));

      $.post(ajaxurl + '?action=gatewayapi_user_sync', function (res) {
        $('#isSyncingStatus').prepend(res.html);
        if (res.finished) return finishUserSync();

        var offset = 1;
        var updateFn = function () {
          $.post(ajaxurl + '?action=gatewayapi_user_sync&page=' + (offset++), function (res) {
            $('#isSyncingStatus').html(res.html);
            if (!res.finished) updateFn();
            else finishUserSync();
          });
        };
        updateFn();
      });
    }
  }

  function handleSecurityTab() {
    $('#gwapiSecurityBypassCodeReset').click(function () {
      $('input[name="gwapi_security_bypass_code"]').val('');
      $('input[name="gwapi_security_bypass_code"] + input').val('');
    });
    $('input[name="gwapi_security_bypass_code"]').click(function () {
      $(this).get(0).select();
    });

    $('select[name^="gwapi_security_required_roles"]').select2({
      placeholder: "Select user roles",
      multiple: true,
      closeOnSelect: false,
      allowClear: true,
    });
  }

  initialize();

});
