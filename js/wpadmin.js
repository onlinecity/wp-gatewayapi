jQuery(function ($) {

  var GSM_CHARS_ONE = ' !"#$%&\'()*+,-./0123456789:;<=>?@abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ£¥§¿_\n\rΔΦΓΛΩΠΨΣΘΞèéùìòÇØøÅåÆæßÉÄÖÑÜäöñüàäöñüà';
  var GSM_CHARS_TWO = '^{}[]~|€';


  function initialize() {
    var body = $('body');

    // if we find a gatewayapi[message] textarea we enable smsLengthCounter plugin
    if (body.find('textarea[name="gatewayapi[message]"]').length) {
      smsLengthCounter();
      handleShowInvalidCharsPopup();
    }

    var has_send_ui = body.hasClass('post-type-gwapi-sms');
    var has_recipient_ui = body.hasClass('gwapi-recipient-ui');
    var has_receive_sms_ui = body.hasClass('post-type-gwapi-receive-sms');
    if (!has_send_ui && !has_recipient_ui && !has_receive_sms_ui) return; // bail early

    loadCountryCodes();

    if (has_send_ui) {
      validateSmsSendOnPublish();
      handlePickRecipientTypes();
      handleMoveRecipientGroupsAround();
      loadOldData();
      handleAddSingleRecipient();
      handleRemoveSingleRecipient();
      lockdownOnPublish();

      handleSending();
    }

    if (has_recipient_ui) {
      validateSmsRecipientOnPublish();
      addFormFieldTooltips();
      handleRecipientExport();
    }

    if (has_receive_sms_ui) {
      handleSmsExport();
    }

  }

  /**
   * Render a list of country codes into the CC-fields on the page.
   */
  function loadCountryCodes() {
    $('select[name="gatewayapi[cc]"], #recipient_cc').gwapiMobileCc();
  }

  /**
   * Count the length of the current SMS.
   */
  function smsLengthCounter() {
    var textarea = $('textarea[name="gatewayapi[message]"]');
    if (!textarea.length) return;

    var countEl = $('<div class="sms-length-counter">').insertAfter(textarea);

    /**
     * Count characters.
     */
    function doCount() {
      var message = textarea.val();

      var isUCS2 = $('input[name="gatewayapi[encoding]"]:checked').val() == 'UCS2';
      var stats = {};

      if (isUCS2) {
        stats = messageCountUcs2(message);
      } else {
        stats = messageCountGsm0338(message);
      }

      var chars = stats.characters;
      var smses = stats.pages;

      var charsText = chars == 1 ? textarea.data('counter-i18n').character : textarea.data('counter-i18n').characters;
      var smsText = smses == 1 ? textarea.data('counter-i18n').sms : textarea.data('counter-i18n').smses;

      countEl.text(chars + ' ' + charsText + ' (' + smses + ' ' + smsText + ')');
    }

    /**
     * Check for non GSM 03.38-characters and update the recommended encoding.
     */
    function checkForBestEncoding() {
      var message = textarea.val();
      var failed_chars = failedGSM0338Chars(message);

      if (failed_chars.length) {
        $('.GSM0338-recommended').addClass('hidden');
        $('.UCS2-recommended').removeClass('hidden');
      } else {
        $('.UCS2-recommended').addClass('hidden');
        $('.GSM0338-recommended').removeClass('hidden');
      }
    }

    textarea.on('input', doCount).on('input', checkForBestEncoding);
    $('input[name="gatewayapi[encoding]"]').change(doCount).change(checkForBestEncoding);

    doCount();
    checkForBestEncoding();
  }

  /**
   * If user clicks the "show invalid characters" link, show an alert with the invalid characters in it.
   */
  function handleShowInvalidCharsPopup()
  {
    var textarea = $('textarea[name="gatewayapi[message]"]');

    $('a[href="#gwapi-show-invalid-chars"]').click(function(ev) {
      ev.preventDefault();
      console.log(failedGSM0338Chars( textarea.val() ));
      window.alert($(this).data('pretext')+'\n\n'+failedGSM0338Chars( textarea.val() ).join(' '));
    });
  }

  /**
   * Detect if there are any specialchars beyond the GSM 03.38 spec and return an array of failed
   * characters. Returns an empty array if nothing failed.
   */
  function failedGSM0338Chars(message) {
    var lookup = (GSM_CHARS_ONE + GSM_CHARS_TWO).split('');
    var chars = punycode.ucs2.decode(message);

    var failed_chars = [];
    _.each(chars, function(char_hex) {
      var char = punycode.ucs2.encode([char_hex]);
      if (!_.contains(lookup, char)) failed_chars.push(char);
    });

    return _.uniq(failed_chars);
  }

  /**
   * Returns object with statistics about message, emulating GSM 03.38.
   *
   * @param message
   */
  function messageCountGsm0338(message)
  {
    var chars = punycode.ucs2.decode(message);
    var lookup2 = GSM_CHARS_TWO.split('');

    var chars_count = 0;

    _.each(chars, function(char_hex) {
      var char = punycode.ucs2.encode([char_hex]);

      chars_count++;
      if (_.contains(lookup2, char)) chars_count ++;
    });

    return {
      characters: chars_count,
      pages: chars_count / 160 > 1 ? Math.ceil(chars_count / 153) : 1
    }
  }

  /**
   * Returns object with statistics about message, emulating UCS2.
   *
   * @param message
   */
  function messageCountUcs2(message)
  {
    var chars = punycode.ucs2.decode(message);
    console.log(chars);

    return {
      characters: chars.length,
      pages: chars.length / 70 > 1 ? Math.ceil(chars.length / 67) : 1
    };
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
      $('<div class="gwapi-notice error"><p>' + GWAPI_I18N_DEFAULT_ERROR + '</p></div>')
    );

    _.each(response.failed, function (msg, field) {
      var el = $('[name="gatewayapi[' + field + ']"]');
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
  function handlePickRecipientTypes() {
    var outer = $('#sms-recipients');

    outer.on('change', 'input', function () {
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
  function handleMoveRecipientGroupsAround() {
    var outer = $('#sms-recipient-groups');

    var all = outer.find('.recipient-groups .all-groups .inner');
    var selected = outer.find('.recipient-groups .selected-groups .inner');

    outer.find('.recipient-groups input:checkbox').change(function () {
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
  function loadOldData() {
    // recipient types
    var recipientTypesEl = $('.recipient-types');
    var selected_types = recipientTypesEl.data('selected_types') || [];
    _.each(selected_types, function (t) {
      recipientTypesEl.find('[value="' + t + '"]').prop('checked', true).trigger('change');
    });

    // recipient groups
    var groupsEl = $('.recipient-groups');
    var selected_groups = groupsEl.data('selected_groups') || [];
    _.each(selected_groups, function (t) {
      groupsEl.find('[value="' + t + '"]').prop('checked', true).trigger('change');
    });
  }

  /**
   * Add single recipient.
   */
  function handleAddSingleRecipient() {
    var outer = $('#sms-recipient-manual');

    function doAddTheSingleRecipient(metaID) {
      var tbody = outer.find('table tbody');
      tbody.find('.empty_row').hide();

      var ccEl = outer.find('select[name="gatewayapi[single_recipient][cc]"]');
      var numberEl = outer.find('input[name="gatewayapi[single_recipient][number]"]');
      var nameEl = outer.find('input[name="gatewayapi[single_recipient][name]"]');

      var cc = ccEl.val();
      var number = numberEl.val();
      var name = nameEl.val();

      numberEl.val('');
      nameEl.val('');

      var tr = $('<tr>').data('meta_id', metaID);
      tr.append($('<td>').append('<a href="#delete" class="delete-btn">'));
      tr.append($('<td>').text('+' + cc + ' ' + number));
      tr.append($('<td>').text(name));
      tbody.append(tr);

      numberEl.focus();
    }

    outer.find('button.add_single_recipient').click(function (ev) {
      ev.preventDefault();

      outer.find('.gwapi-star-errors').empty();
      outer.find('.gwapi-field-error-message').remove();

      // serialize the data we need
      var data = $('#sms-recipient-manual input, #sms-recipient-manual select, #post_ID').serialize();

      // send via ajax
      $.post(ajaxurl + '?action=gatewayapi_sms_manual_add_recipient', data, function (res) {

        if (res.success) {
          return doAddTheSingleRecipient(res.ID);
        }

        // fail :-(
        _.each(res.errors, function (msg, field) {
          var el = outer.find('[name="gatewayapi[single_recipient][' + field + ']"]');
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
  function handleRemoveSingleRecipient() {
    var outer = $('#sms-recipient-manual');

    outer.on('click', 'tbody .delete-btn', function (ev) {
      ev.preventDefault();
      var tr = $(this).parents('tr');
      var metaID = tr.data('meta_id');
      tr.addClass('deleting');
      $.post(ajaxurl + '?action=gatewayapi_sms_manual_delete_recipient', {
        post_ID: $('#post_ID').val(),
        'meta_ID': metaID
      }, function (res) {
        if (!res.success) {
          return window.alert(res.message);
        }
        tr.fadeOut(function () {
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
    inner.find('input,button,select,label,#submitdiv a').prop('readonly', true).css('pointer-events', 'none');
  }

  function addFormFieldTooltips() {
    $('#custom_fields').find('.info.hidden').each(function () {
      if (!$.trim($(this).text())) return;
      var i = $('<i class="info has-tooltip">').attr({ 'title': $(this).text() });
      i.insertAfter($(this));
      i.tooltip({ show: false, hide: false });
    });
  }

  /**
   * Buttons for exporting SMS'es to CSV and XLS.
   */
  function handleSmsExport() {
    if ($('form#posts-filter').length) {
      var buttons = [
        '<a class="add-new-h2 gwapi-receive-sms-export-button" data-format="csv">CSV</a>',
        '<a class="add-new-h2 gwapi-receive-sms-export-button" data-format="xlsx">XLSX</a>'
      ];
      $('<span>').css({
        float: 'left',
        'margin-top': '15px',
        'margin-left': '10px'
      }).append(buttons).insertBefore($('#posts-filter'));
      $('.gwapi-receive-sms-export-button').click(function () {
        var form = $('form#gwapiReceiveSmsExportForm');
        form.find('input[name="gwapi_receive_sms_export_format"]').val($(this).attr('data-format'));
        form.submit();
      });
    }
  }

  /**
   * Buttons for exporting recipients to CSV and XLS.
   */
  function handleRecipientExport() {
    if ($('form#posts-filter').length) {
      var buttons = [
        '<a class="add-new-h2 gwapi-recipient-export-button" data-format="csv">CSV</a>',
        '<a class="add-new-h2 gwapi-recipient-export-button" data-format="xlsx">XLSX</a>'
      ];
      $('<span>').css({
        float: 'left',
        'margin-top': '15px',
        'margin-left': '10px'
      }).append(buttons).insertBefore($('#posts-filter'));
      $('.gwapi-recipient-export-button').click(function () {
        var form = $('form#gwapiRecipientExportForm');
        form.find('input[name="gwapi_recipient_export_format"]').val($(this).attr('data-format'));
        form.submit();
      });
    }
  }

  /**
   * When status is "sending", start the batch-sender.
   */
  function handleSending() {
    if (!$('#sms-status [data-is-sending]').length) return;

    setTimeout(function () {
      $.get(ajaxurl + '?action=gwapi_get_html_status&ID=' + $('#post_ID').val(), function (ret) {
        $('#sms-status .inside').html(ret);
        handleSending();
      })
    }, 5000);
  }

  initialize();

});