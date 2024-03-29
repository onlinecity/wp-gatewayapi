<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

/**
 * Render the recipient form according to the attributes sent along.
 */
function gatewayapi__shortcode_render_recipient_form($atts, $recipient = null)
{
  // render the form fields
  $fields = get_option('gwapi_recipient_fields');
  foreach ($fields as $row) {
    if (isset($row['type']) && $row['type'] == 'hidden') continue;
    gatewayapi__render_recipient_field($row, $recipient ?: new WP_Post(new stdClass()));
  }

  // edit groups?
  if (isset($atts['edit-groups']) && $atts['edit-groups']) {
    gatewayapi__render_recipient_editable_groups($atts, $recipient);
  }
}

/**
 * Render a login-form for the recipient.
 */
function gatewayapi__shortcode_render_recipient_login($atts)
{
  $fields = get_option('gwapi_recipient_fields');
  foreach ($fields as $row) {
    if (!in_array($row['field_id'], ['CC', 'NUMBER'])) continue;
    gatewayapi__render_recipient_field($row, new WP_Post(new stdClass()));
  }
}

/**
 * Render a "confirm SMS" form for the recipient.
 */
function gatewayapi__shortcode_render_recipient_login_confirm($atts, $recipient)
{
  $cc = get_post_meta($recipient->ID, 'cc', true);
  $number = get_post_meta($recipient->ID, 'number', true);
  $msisdn = preg_replace('/\D+/', '', $cc . $number);
  ?>
  <input type="hidden" name="gwapi_action" value="update_form"/>
  <input type="hidden" name="gatewayapi[cc]" value="<?php echo esc_attr($cc); ?>"/>
  <input type="hidden" name="gatewayapi[number]" value="<?php echo esc_attr($number); ?>"/>
  <?php if (isset($atts['recaptcha']) && $atts['recaptcha']): ?>
  <input type="hidden" name="gatewayapi[security_check]" value="<?php echo esc_attr(get_transient('valid_recaptcha_' . $msisdn)); ?>"/>
<?php endif; ?>

  <?php
  gatewayapi__render_recipient_field([
    'type' => 'digits',
    'name' => __('Confirmation code', 'gatewayapi'),
    'field_id' => 'sms_verify',
    'description' => __('Please enter the code that you have just received by SMS.', 'gatewayapi'),
    'required' => 1
  ], $recipient);
}

/**
 * Render a CAPTCHA field.
 */
function gatewayapi__shortcode_render_captcha($atts)
{
  echo '<div class="g-recaptcha" data-sitekey="' . esc_attr(get_option('gwapi_recaptcha_site_key')) . '"></div>';
  wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js');
}

function gatewayapi__shortcode_render_submit($action_text)
{
  echo '<button type="submit" class="btn btn-primary">';
  echo esc_html($action_text);
  echo '</button>';
}

/**
 * Verify the CAPTCHA-data submitted by the user, by checking with Google.
 */
function gatewayapi__shortcode_verify_captcha()
{
  $captcha_res = preg_replace('/[^a-zA-Z0-9_\-]+/', '', sanitize_text_field($_POST['g-recaptcha-response'] ?? ''));

  $res = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
    'body' => [
      'secret' => get_option('gwapi_recaptcha_secret_key'),
      'response' => $captcha_res,
      'remoteip' => $_SERVER['REMOTE_ADDR']
    ]
  ]);
  if (is_wp_error($res)) return new WP_Error('recaptcha_communication_error', 'Error while communicating with reCAPTCHA.' .
    esc_html($res->get_error_message()));

  $res = json_decode($res['body']);
  if (!$res->success) return new WP_Error('recaptcha_wrong', 'Unfortunately the reCAPTCHA failed. Please try again.');

  $cc = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['cc'] ?? ''));
  $number = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['number'] ?? ''));
  $msisdn = gatewayapi__get_msisdn($cc, $number);

  if (!$msisdn) return new WP_Error('recaptcha_bad_data', 'Mobile number missing from request.');

  set_transient('valid_recaptcha_' . $msisdn, wp_generate_password(32, false), 60 * 60 * 4);

  return true;
}

/**
 * Flow for signing up a new recipient.
 */
function gatewayapi__shortcode_handle_signup($atts)
{
  $action_text = "";
  $action_note = "";

  $action = sanitize_key($_POST['gwapi_action'] ?? 'signup');
  if (!in_array($action, ['signup', 'signup_confirm_sms', 'signup_confirm_save'])) wp_die('Invalid gwapi_action');

  switch ($action) {
    // STEP 1: ENTER MOBILE
    case 'signup':
      echo '<input type="hidden" name="gwapi_action" value="signup_confirm_sms" />';
      gatewayapi__shortcode_render_recipient_form($atts);
      $action_text = __('Sign up', 'gatewayapi');
      $action_note = __('Note: A code will be sent by SMS to the mobile number specified, to confirm the ownership.', 'gatewayapi');
      break;

    // STEP 2: CONFIRM SMS
    case 'signup_confirm_sms':
      // verify the information posted
      $cc = sanitize_key($_POST['gatewayapi']['cc']);
      $no = sanitize_key(ltrim($_POST['gatewayapi']['number'], '0'));
      $recipient = gatewayapi__get_recipient($cc, $no);
      $msisdn = gatewayapi__get_msisdn($cc, $no);
      if (!is_wp_error($recipient)) return new WP_Error('already_exists', __('You are already subscribed with the phone number specified.', 'gatewayapi'));

      // save the information supplied by the user
      $data = ['cc' => $cc, 'number' => $no];
      foreach(gatewayapi__all_recipient_fields() as $f) {
        if (!isset($data[strtolower($f['field_id'])]) && isset($_POST['gatewayapi'][strtolower($f['field_id'])]))
          $data[strtolower($f['field_id'])] = sanitize_text_field($_POST['gatewayapi'][strtolower($f['field_id'])]);
      }
      set_transient('gwapi_subscriber_' . $msisdn, $data, 60 * 60 * 4);

      // send the verification sms
      $code = rand(100000, 999999);
      set_transient('gwapi_confirmation_code_' . $msisdn, $code, 60 * 60 * 4);

      $status = gatewayapi_send_sms(strtr(__('Your confirmation code: %code%', 'gatewayapi'), ['%code%' => substr($code, 0, 3) . " " . substr($code, 3, 3)]), $msisdn);
      if (is_wp_error($status)) return new WP_Error('sms_fail', __('Sending of the verification code by SMS failed. Please try again later or contact the website owner.', 'gatewayapi'));

      // show the form
      echo '<input type="hidden" name="gatewayapi[cc]" value="' . esc_attr($cc) . '">';
      echo '<input type="hidden" name="gatewayapi[number]" value="' . esc_attr($no) . '">';
      echo '<input type="hidden" name="gwapi_action" value="signup_confirm_save">';
      gatewayapi__render_recipient_field([
        'type' => 'digits',
        'name' => __('Confirmation code', 'gatewayapi'),
        'field_id' => 'sms_verify',
        'description' => __('Please enter the code that you have just received by SMS.', 'gatewayapi'),
        'required' => 1
      ], new WP_Post(new stdClass()));
      $action_text = __('Confirm', 'gatewayapi');

      break;

    // STEP 3: SAVE
    case 'signup_confirm_save':
      $cc = sanitize_key($_POST['gatewayapi']['cc']);
      $no = sanitize_key(ltrim($_POST['gatewayapi']['number'], '0'));
      $msisdn = gatewayapi__get_msisdn($cc, $no);
      $code = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['sms_verify'] ?? ''));
      $confirm = get_transient('gwapi_confirmation_code_' . $msisdn);
      if (!$confirm || $confirm != $code) return new WP_Error('invalid_sms_code', __('It seems the SMS code you are using to authorize this update, has expired. Please start over and try again.', 'gatewayapi'));

      // save the recipient
      $ID = wp_insert_post([
        'post_type' => 'gwapi-recipient',
        'post_status' => 'publish'
      ]);

      gatewayapi__save_recipient($ID, get_transient('gwapi_subscriber_' . $msisdn), true);

      $recipient_groups = array_map('intval', get_transient('gwapi_subscriber_' . $msisdn)['_gatewayapi_recipient_groups'] ?? []);
      gatewayapi__save_recipient_groups($ID, $recipient_groups, $atts);

      do_action('gwapi_form_subscribe', $ID, get_post($ID));

      echo '<div class="alert alert-success">' . __('You have been succesfully subscribed. Thank you signing up.', 'gatewayapi') . '</div>';

      // invalidate the sms code transient and subscriber post data
      delete_transient('gwapi_confirmation_code_' . $msisdn);
      delete_transient('gwapi_subscriber_' . $msisdn);

      break;
  }

  return [$action_text, $action_note];
}

/**
 * Flow for updating an existing recipient.
 */
function gatewayapi__shortcode_handle_update($atts)
{
  $action_text = $action_note = "";

  $action = sanitize_key($_POST['gwapi_action'] ?? 'update');
  switch ($action) {
    // STEP 1: ENTER MOBILE
    case 'update':
      echo '<input type="hidden" name="gwapi_action" value="update_login">';
      gatewayapi__shortcode_render_recipient_login($atts);
      $action_text = __('Log in', 'gatewayapi');
      $action_note = __('Note: A code will be sent by SMS to the mobile number specified, which is used to verify your ownership.', 'gatewayapi');
      break;

    // STEP 2: CONFIRM SMS
    case 'update_login':
      $cc = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['cc'] ?? ''));
      $number = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['number'] ?? ''));

      // verify the information posted
      $recipient = gatewayapi__get_recipient($cc, $number);
      $valid = !is_wp_error($recipient);

      // valid? send verification SMS and show the "verify SMS" form
      if ($valid) {
        // send the verification sms
        $code = rand(100000, 999999);
        $msisdn = gatewayapi__get_msisdn($cc, $number);
        set_transient('gwapi_confirmation_code_' . $msisdn, $code, 60 * 60 * 4);

        $status = gatewayapi_send_sms(strtr(__('Your confirmation code: %code%', 'gatewayapi'), ['%code%' => substr($code, 0, 3) . " " . substr($code, 3, 3)]), $msisdn);
        if (is_wp_error($status)) return new WP_Error('sms_fail', __('Sending of the verification code by SMS failed. Please try again later or contact the website owner.', 'gatewayapi'));

        // show the form
        echo '<input type="hidden" name="gwapi_action" value="update_form">';
        gatewayapi__shortcode_render_recipient_login_confirm($atts, $recipient);
        $action_text = __('Continue', 'gatewayapi');
      } else {
        return new WP_Error('recipient_not_found', __('We could not find you in our database. Maybe you made a typo or maybe you are not subscribed with this number?', 'gatewayapi'));
      }
      break;

    // STEP 3: THE UPDATE FORM
    case 'update_form':
      $cc = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['cc'] ?? ''));
      $number = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['number'] ?? ''));
      $msisdn = gatewayapi__get_msisdn($cc, $number);

      // re-verify the CAPTCHA token
      if (isset($atts['recaptcha']) && $atts['recaptcha']) {
        $security_check = sanitize_text_field($_POST['gatewayapi']['security_check'] ?? '');
        if (!get_transient('valid_recaptcha_' . $msisdn) || get_transient('valid_recaptcha_' . $msisdn) != $security_check) {
          return new WP_Error('recaptcha_reconfirm_missing', __('Your request is missing or has invalid technical security identity information. This may happen if you have waited for more than 4 hours or has been doing other subscription related actions in other tabs/windows.', 'gatewayapi'));
        } else {
          delete_transient('valid_recaptcha_' . $msisdn); // cleanup
        }
      }

      // verify the SMS code
      $sms_verify = preg_replace('/\D+/', '', sanitize_text_field($_POST['gatewayapi']['sms_verify'] ?? ''));
      if (!get_transient('gwapi_confirmation_code_' . $msisdn) || get_transient('gwapi_confirmation_code_' . $msisdn) != $sms_verify) {
        return new WP_Error('sms_code_wrong', __('The code you entered is incorrect. Please try again.', 'gatewayapi'));
      }

      // show the update form
      $recipient = gatewayapi__get_recipient($cc, $number);
      if (is_wp_error($recipient)) return new WP_Error('bad_recipient', 'The recipient could not be found.', 'gatewayapi');

      // make sure CC and NUMBER is read-only
      add_filter('gwapi_recipient_row_attributes', function ($field) {
        if ($field['field_id'] == 'CC' || $field['field_id'] == 'NUMBER') {
          $field['disabled'] = true;
        }
        return $field;
      }, 10, 1);

      gatewayapi__shortcode_render_recipient_form($atts, $recipient);

      // remember the SMS code
      echo '<input type="hidden" name="gwapi_sms_code" value="' . esc_attr(get_transient('gwapi_confirmation_code_' . $msisdn)) . '">';
      echo '<input type="hidden" name="gwapi_action" value="update_save">';
      echo '<input type="hidden" name="gatewayapi[cc]" value="' . esc_attr($cc) . '">';
      echo '<input type="hidden" name="gatewayapi[number]" value="' . esc_attr($number) . '">';

      $action_text = __('Save changes', 'gatewayapi');
      break;

    // STEP 4: SAVE
    case 'update_save':
      $cc = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['cc'] ?? ''));
      $number = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['number'] ?? ''));
      $msisdn = gatewayapi__get_msisdn($cc, $number);
      $code = sanitize_key($_POST['gwapi_sms_code'] ?? '');

      $confirm = get_transient('gwapi_confirmation_code_' . $msisdn);
      if (!$confirm || $confirm != $code) return new WP_Error('invalid_sms_code', __('It seems the SMS code you are using to authorize this update, has expired. Please start over and try again.', 'gatewayapi'));

      // fetch recipient
      $recipient = gatewayapi__get_recipient($cc, $number);
      if (is_wp_error($recipient)) return new WP_Error('bad_recipient', 'The recipient could not be found.', 'gatewayapi');

      // update the information
      do_action('save_post_gwapi-recipient', $recipient->ID);
      $recipient_groups = array_map('intval', $_POST['gatewayapi']['_gatewayapi_recipient_groups'] ?? []);
      gatewayapi__save_recipient_groups($recipient->ID, $recipient_groups, $atts);

      echo '<div class="alert alert-success">' . __('Your changes has been saved. Thank you for keeping your subscription up-to-date.', 'gatewayapi') . '</div>';

      // invalidate the sms code transient
      delete_transient('gwapi_confirmation_code_' . $msisdn);

      break;
  }

  return [$action_text, $action_note];
}

function gatewayapi__shortcode_handle_unsubscribe($atts)
{
  $action_text = '';
  $action_note = '';

  $action = sanitize_key($_POST['gwapi_action'] ?? 'unsubscribe');
  switch ($action) {
    // STEP 1: ENTER MOBILE
    case 'unsubscribe':
      echo '<input type="hidden" name="gwapi_action" value="unsubscribe_login">';
      gatewayapi__shortcode_render_recipient_login($atts);
      $action_text = __('Unsubscribe', 'gatewayapi');
      $action_note = __('Note: A code will be sent by SMS to the mobile number specified, which is used to verify your ownership.', 'gatewayapi');
      break;

    // STEP 2: CONFIRM SMS
    case 'unsubscribe_login':
      // verify the information posted
      $cc = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['cc'] ?? ''));
      $number = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['number'] ?? ''));

      $recipient = gatewayapi__get_recipient($cc, $number);
      $valid = !is_wp_error($recipient);

      // valid? send verification SMS and show the "verify SMS" form
      if ($valid) {
        // send the verification sms
        $code = rand(100000, 999999);
        $msisdn = gatewayapi__get_msisdn($cc, $number);
        set_transient('gwapi_confirmation_code_' . $msisdn, $code, 60 * 60 * 4);

        $status = gatewayapi_send_sms(strtr(__('Unsubscribe confirmation code: %code%', 'gatewayapi'), ['%code%' => substr($code, 0, 3) . " " . substr($code, 3, 3)]), $msisdn);
        if (is_wp_error($status)) return new WP_Error('sms_fail', __('Sending of the verification code by SMS failed. Please try again later or contact the website owner.', 'gatewayapi'));

        // show the form
        echo '<input type="hidden" name="gwapi_action" value="unsubscribe_confirm">';
        echo '<input type="hidden" name="gatewayapi[cc]" value="' . esc_attr($cc) . '">';
        echo '<input type="hidden" name="gatewayapi[number]" value="' . esc_attr($number) . '">';

        gatewayapi__render_recipient_field([
          'type' => 'digits',
          'name' => __('Confirmation code', 'gatewayapi'),
          'field_id' => 'sms_verify',
          'description' => __('Please enter the code that you have just received by SMS.', 'gatewayapi'),
          'required' => 1
        ], $recipient);

        $action_text = __('Confirm', 'gatewayapi');
      } else {
        return new WP_Error('recipient_not_found', __('We could not find you in our database. Maybe you made a typo or maybe you are not subscribed with this number?', 'gatewayapi'));
      }
      break;

    // STEP 3: UNSUBSCRIBE
    case 'unsubscribe_confirm':
      $cc = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['cc'] ?? ''));
      $number = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['number'] ?? ''));

      $msisdn = gatewayapi__get_msisdn($cc, $number);

      // verify the SMS code
      $sms_verify = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['sms_verify'] ?? ''));
      if (!get_transient('gwapi_confirmation_code_' . $msisdn) || get_transient('gwapi_confirmation_code_' . $msisdn) != $sms_verify) {
        return new WP_Error('sms_code_wrong', __('The code you entered is incorrect. Please try again.', 'gatewayapi'));
      }

      // find recipient
      $recipient = gatewayapi__get_recipient($cc, $number);
      if (is_wp_error($recipient)) return new WP_Error('bad_recipient', 'The recipient could not be found.', 'gatewayapi');

      do_action('gwapi_form_unsubscribe', $recipient->ID, $recipient);

      // unsubscribe now
      wp_trash_post($recipient->ID);

      // cleanup
      delete_transient('gwapi_confirmation_code_' . $msisdn);


      echo '<div class="alert alert-success">' . __('You have been unsubscribed.', 'gatewayapi') . '</div>';

      break;
  }

  return [$action_text, $action_note];
}

function gatewayapi__shortcode_handle_send_sms($atts)
{
  $action_text = "";
  $action_note = "";

  $action = sanitize_key($_POST['gwapi_action'] ?? 'write');
  switch ($action) {
    // STEP 1: ENTER MESSAGE
    case 'write':
      $dummy = new WP_Post(new stdClass());
      echo '<input type="hidden" name="gwapi_action" value="send" />';

      // alpha
      if (isset($atts['edit-sender']) && $atts['edit-sender']) {
        gatewayapi__render_recipient_field([
          'type' => 'text',
          'name' => __('Sender', 'gatewayapi'),
          'field_id' => 'SENDER',
          'description' => __('Sender of the SMS. Either up to 11 characters or up to 15 digits only.', 'gatewayapi')
        ], $dummy);
      }

      // message
      gatewayapi__render_recipient_field([
        'type' => 'textarea',
        'name' => __('Message', 'gatewayapi'),
        'field_id' => 'MESSAGE',
        'required' => 1,
        'description' => __('Please enter your message here.', 'gatewayapi')
      ], $dummy);
      $action_text = __('Send SMS', 'gatewayapi');

      // groups
      if (isset($atts['edit-groups']) && $atts['edit-groups']) {
        gatewayapi__render_recipient_editable_groups($atts, $dummy);
      }

      break;

    // STEP 2: QUEUE SMS
    case 'send':

      // create the SMS
      $ID = wp_insert_post([
        "post_type" => "gwapi-sms"
      ]);
      $message = sanitize_textarea_field($_POST['gatewayapi']['message'] ?? '');
      update_post_meta($ID, 'message', $message);

      // sender
      $sender = sanitize_text_field($_POST['gatewayapi']['sender'] ?? '');
      if (isset($atts['edit-sender']) && $atts['edit-sender'] && $sender) {
        if (ctype_digit($sender)) $sender = substr($sender, 0, 15);
        else $sender = substr($sender, 0, 11);

        update_post_meta($ID, 'sender', $sender);
      }

      // groups - customizable
      $recipientGroupsRaw = array_map('intval', $_POST['gatewayapi']['_gatewayapi_recipient_groups'] ?? []);

      if (isset($atts['edit-groups']) && $atts['edit-groups'] && $recipientGroupsRaw) {
        $groups = [];
        $validGroups = isset($atts['groups']) ? explode(',', $atts['groups']) : false;
        foreach ($recipientGroupsRaw as $gID) {
          if ($validGroups === false || in_array($gID, $validGroups)) $groups[] = (int)$gID;
        }
        if ($groups) {
          update_post_meta($ID, 'recipients', ['groups']);
          update_post_meta($ID, 'recipient_groups', $groups);
        }
      }

      // groups - fixed list
      if (!isset($atts['edit-groups']) && $atts['edit-groups'] && isset($atts['groups']) && $atts['groups']) {
        $groups = explode(',', $atts['groups']);
        foreach ($groups as &$gID) {
          $gID = (int)$gID;
        }
        update_post_meta($ID, 'recipients', ['groups']);
        update_post_meta($ID, 'recipient_groups', $groups);
      }

      // send SMS
      wp_publish_post($ID);

      $status_code = get_post_meta($ID, 'api_status', true);

      switch ($status_code) {
        case 'is_sent':
          echo '<div class="alert alert-success">' . __('Your SMS has been queued for delivery and should arrive shortly.', 'gatewayapi') . '</div>';
          break;

        default:
          echo '<div class="alert alert-danger">' . get_post_meta($ID, 'api_error', true) . '</div>';
          break;
      }

      break;
  }

  return [$action_text, $action_note];
}

$shortcode_fn = function ($atts) {
  ob_start();

  // validate: action
  $valid_actions = ['signup', 'update', 'unsubscribe', 'send_sms'];
  $action = isset($atts['action']) && $atts['action'] ? $atts['action'] : null;
  if (!in_array($action, $valid_actions)) {
    echo '<div class="alert alert-warning">' . strtr(__('Invalid action specified for GatewayAPI shortcode. Must be one of: %actions%.', 'gatewayapi'), ['%actions%' => implode(", ", $valid_actions)]) . '</div>';
    return ob_get_clean();
  }

  // validate: recaptcha
  if (isset($atts['recaptcha']) && $atts['recaptcha']) {
    if (!get_option('gwapi_recaptcha_site_key') || !get_option('gwapi_recaptcha_secret_key')) {
      echo '<div class="alert alert-warning">' . __('You must enter Site key and Secret key from Google reCAPTCHA on the GatewayAPI Settings-page, or disable CAPTCHA on your GatewayAPI Form shortcode.', 'gatewayapi') . '</div>';
      return ob_get_clean();
    }
  }

  // start form
  echo '<form method="post" action="' . get_permalink() . '" class="gwapi-form">';

  // received a captcha? validate!
  $captcha_response = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
  if ($captcha_response) {
    $verify = gatewayapi__shortcode_verify_captcha();
    if (is_wp_error($verify)) {
      echo '<div class="alert alert-warning">' . esc_html($verify->get_error_message()) . '</div>';
      return ob_get_clean();
    }
  }

  // mobile number in request? check if we have a currently non-expired captcha request
  $has_valid_recaptcha = null;
  $cc = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['cc'] ?? ''));
  $number = preg_replace('/\D+/', '', sanitize_key($_POST['gatewayapi']['number'] ?? ''));
  if ($cc && $number) {
    $msisdn = gatewayapi__get_msisdn($cc, $number);
    $has_valid_recaptcha = !!get_transient('valid_recaptcha_' . $msisdn);
  }

  // ACTION: UPDATE
  $action_res = null;
  switch ($action) {
    case 'update':
      $action_res = gatewayapi__shortcode_handle_update($atts);
      break;

    case 'signup':
      $action_res = gatewayapi__shortcode_handle_signup($atts);
      break;

    case 'unsubscribe':
      $action_res = gatewayapi__shortcode_handle_unsubscribe($atts);
      break;

    case 'send_sms':
      $action_res = gatewayapi__shortcode_handle_send_sms($atts);
      break;
  }

  if (!is_wp_error($action_res)) list($action_text, $action_note) = $action_res;
  else {
    echo '<div class="alert alert-warning">' . esc_html($action_res->get_error_message()) . '</div>';
    return ob_get_clean();
  }

  // render the recaptcha and submit button
  if ($action_text) {
    if (isset($atts['recaptcha']) && $atts['recaptcha'] && !$has_valid_recaptcha) {
      gatewayapi__shortcode_render_captcha($atts);
    }

    gatewayapi__shortcode_render_submit($action_text);
    if ($action_note) {
      echo '<div class="help-block description">' . esc_html($action_note) . '</div>';
    }
  }

  // end form
  echo '</form>';

  return ob_get_clean();
};

add_shortcode('gatewayapi', $shortcode_fn);
add_shortcode('gwapi', $shortcode_fn);
