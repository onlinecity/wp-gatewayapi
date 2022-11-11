<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php
/**
 * SMS Editor
 */
add_action('admin_init', function () {
  add_meta_box('sms-recipients', __('Recipients', 'gatewayapi'), 'gatewayapi__sms_recipients', 'gwapi-sms', 'normal', 'default');
  add_meta_box('sms-recipient-groups', __('Recipient groups', 'gatewayapi'), 'gatewayapi__sms_recipient_groups', 'gwapi-sms', 'normal', 'default');
  add_meta_box('sms-recipient-manual', __('Enter mobiles manually', 'gatewayapi'), 'gatewayapi__sms_recipient_manual', 'gwapi-sms', 'normal', 'default');
  add_meta_box('sms-message', __('Message', 'gatewayapi'), 'gatewayapi__sms_message', 'gwapi-sms', 'normal', 'default');

  add_meta_box('sms-status', __('Status', 'gatewayapi'), 'gatewayapi__sms_status', 'gwapi-sms', 'side', 'default');
});

/**
 * SMS Editor Block: Recipients (pick sources of recipients)
 */
function gatewayapi__sms_recipients(\WP_Post $post)
{
  $current_types = get_post_meta($post->ID, 'recipients', true);
  ?>
  <p><?php _e('How would you like to select recipients for this SMS?', 'gatewayapi'); ?></p>

  <div class="recipient-types"
       data-selected_types="<?php echo esc_attr($current_types ? json_encode($current_types) : ''); ?>">
    <label class="gwapi-checkbox">
      <input type="checkbox" name="gatewayapi[recipients][]" value="groups"
             data-group="sms-recipient-groups">
      <?php _e('Use recipient groups', 'gatewayapi'); ?>
    </label>

    <label class="gwapi-checkbox">
      <input type="checkbox" name="gatewayapi[recipients][]" value="manual"
             data-group="sms-recipient-manual">
      <?php _e('Enter mobile numbers manually', 'gatewayapi'); ?>
    </label>
  </div>

  <p
    class="description"><?php _e('Only one message will be sent per mobile number per SMS. So don\'t be afraid to mix and match the above.', 'gatewayapi'); ?></p>
  <?php
}

/**
 * SMS Editor Block: Recipient Groups
 */
function gatewayapi__sms_recipient_groups(\WP_Post $post)
{
  $groups = get_terms([
    'taxonomy' => 'gwapi-recipient-groups'
  ]);
  $current_groups = get_post_meta($post->ID, 'recipient_groups', true);
  ?>

  <div class="gwapi-row recipient-groups"
       data-selected_groups="<?php $current_groups ? esc_attr(json_encode($current_groups)) : ''; ?>">
    <div class="all-groups col-50">
      <h4><?php _e('All recipient groups', 'gatewayapi'); ?></h4>

      <div class="inner">
        <?php foreach ($groups as $group): ?>
          <label class="gwapi-checkbox">
            <input type="checkbox" name="gatewayapi[recipient_groups][]" id=""
                   value="<?php echo esc_attr($group->term_id); ?>">
            <?php echo esc_html($group->name); ?>
            <span class="number"
                  title="<?php esc_attr_e('Recipients in group', 'gatewayapi') ?>: <?php echo esc_attr($group->count); ?>">
              <?php echo esc_attr($group->count); ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="selected-groups col-50">
      <h4><?php _e('Selected recipient groups', 'gatewayapi'); ?></h4>
      <div class="inner"></div>
    </div>
  </div>

  <div class="footer">
    <p
      class="description"><?php _e('You will be sending to all recipients who are in any of the selected groups.', 'gatewayapi'); ?></p>
    <p
      class="description"><?php _e('Only groups with at least one recipient are listed.', 'gatewayapi'); ?></p>
  </div>

  <?php
}


/**
 * SMS Editor Block: Recipient by manual entering
 */
function gatewayapi__sms_recipient_manual(\WP_Post $post)
{
  $ID = $post->ID;
  $published = $post->post_status == 'publish';

  // get the manual recipients
  global $wpdb;
  /** @var $wpdb wpdb */
  $recipients_raw = $wpdb->get_results($wpdb->prepare("SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'single_recipient'", $ID));
  $recipients = [];

  foreach ($recipients_raw as $r) {
    $recipients[$r->meta_id] = maybe_unserialize($r->meta_value);
  }
  ?>

  <div class="gwapi-row">
    <div class="col-40">
      <h4><?php _e('Add recipient', 'gatewayapi'); ?></h4>
      <div class="gwapi-star-errors"></div>

      <div class="field-group">
        <label for="recipient_cc"
               class="control-label"><?php _e('Country code', 'gatewayapi') ?></label>
        <select name="gatewayapi[single_recipient][cc]" id="recipient_cc"></select>
      </div>

      <div class="field-group">
        <label for="recipient_number" class="control-label">
          <?php _e('Phone number', 'gatewayapi') ?>
        </label>

        <input type="number" name="gatewayapi[single_recipient][number]"
               placeholder="<?php esc_attr_e('Phone number - digits only', 'gatewayapi'); ?>"
               id="recipient_number">
      </div>

      <div class="field-group">
        <label for="recipient_name"
               class="control-label"><?php _e('Name (optional)', 'gatewayapi'); ?></label>
        <input type="text" name="gatewayapi[single_recipient][name]" id="recipient_name">
      </div>

      <button type="button" class="button add_single_recipient"
              data-nonce="<?php echo esc_attr(wp_create_nonce('gatewayapi_sms_manual_add_recipient')); ?>">
        <?php _e('Add recipient', 'gatewayapi'); ?>
      </button>

    </div>
    <div class="col-60">
      <h4><?php _e('Manually added recipients', 'gatewayapi'); ?></h4>
      <div class="inner">
        <table class="widefat" data-delete-nonce="<?php echo esc_attr(wp_create_nonce('gatewayapi_sms_manual_delete_recipient')); ?>">
          <thead>
          <tr>
            <th width="10%"><a class="delete-btn"></a></th>
            <th width="30%"><?php _e('Phone number', 'gatewayapi'); ?></th>
            <th width="60%"><?php _e('Name', 'gatewayapi'); ?></th>
          </tr>
          </thead>
          <tbody>
          <tr class="empty_row" <?php echo $recipients ? 'style="display: none;"' : ''; ?>>
            <td colspan="3">
              <p class="description"
                 style="text-align: center; margin-top: 10px;"><?php _e('No recipients manually added', 'gatewayapi'); ?></p>
            </td>
          </tr>
          <?php foreach ($recipients as $ID => $r): ?>
            <tr data-meta_id="<?php echo esc_attr($ID); ?>">
              <td><a href="#delete" class="delete-btn"></a></td>
              <td>
                +<?php echo esc_html($r['cc']) ?>
                <?php echo esc_html($r['number']); ?>
              </td>
              <td>
                <?php echo esc_html($r['name']); ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php
}


/**
 * SMS Editor Block: The messaging area
 */
function gatewayapi__sms_message(\WP_Post $post)
{
  $ID = $post->ID;
  $published = $post->post_status == 'publish';

  ?>
  <div class="gwapi-star-errors"></div>
  <table width="100%" class="form-table">
    <tbody>
    <tr>
      <th width="25%">
        <?php _e('Sender', 'gatewayapi'); ?>
      </th>
      <td>
        <input type="text" name="gatewayapi[sender]" size="15"
               value="<?php echo esc_attr(get_post_meta($ID, 'sender', true)); ?>">
        <p
          class="description"><?php _e('The sender can be either 11 characters or 15 digits in total.', 'gatewayapi'); ?></p>
      </td>
    </tr>
    <tr>
      <th width="25%" class="vtop-5">
        <?php _e('Type of SMS', 'gatewayapi'); ?>
      </th>
      <td>
        <?php $destaddr = get_post_meta($ID, 'destaddr', true); ?>
        <label>
          <input type="radio" name="gatewayapi[destaddr]"
                 value="MOBILE" <?php echo ($destaddr == 'MOBILE' || !$destaddr) ? 'checked' : ''; ?>>
          <?php _e('Regular SMS', 'gatewayapi'); ?>
        </label>
        <br/>
        <label>
          <input type="radio" name="gatewayapi[destaddr]"
                 value="DISPLAY"<?php echo $destaddr == 'DISPLAY' ? 'checked' : ''; ?>>
          <abbr
            title="<?php esc_attr_e('Message is displayed immediately and usually not saved in the normal message inbox. Also knows as a Flash SMS.', 'gatewayapi'); ?>"><?php _e('Display SMS', 'gatewayapi'); ?></abbr>
        </label>
      </td>
    </tr>
    <tr>
      <th width="25%" class="vtop-5">
        <?php _e('Encoding', 'gatewayapi'); ?><br/>
        <a href="https://gatewayapi.com/docs/appendix.html#term-mcc"><small><?php _e('More information', 'gatewayapi'); ?></small></a>
      </th>
      <td>
        <?php $destaddr = get_post_meta($ID, 'encoding', true); ?>

        <table cellspacing="0" cellpadding="0" border="0" class="tiny-padding-table">
          <tr data-encoding="GSM0338">
            <td>
              <label>
                <input type="radio" name="gatewayapi[encoding]"
                       value="GSM0338" <?php echo ($destaddr === 'GSM0338' || !$destaddr) ? 'checked' : ''; ?>>
                <abbr
                  title="<?php esc_attr_e('160 characters for 1-page SMS. 153 characters for multi-page SMS. Limited special characters, no emoji-support.', 'gatewayapi'); ?>"><?php _e('GSM 03.38', 'gatewayapi'); ?></abbr>
              </label>
            </td>
            <td>
              <span class="GSM0338-recommended"
                    title="<?php esc_attr_e('All characters in the message are within GSM 03.38.', 'gatewayapi'); ?> <?php esc_attr_e('You should use GSM 03.38, as it roughly doubles the characters available per page over UCS2.', 'gatewayapi'); ?>">✅</span>
              <span class="UCS2-recommended hidden"
                    title="<?php esc_attr_e('There are characters beyond GSM 03.38. These will not be properly displayed.', 'gatewayapi'); ?>">🛑</span>
            </td>
            <td>
              <a class="UCS2-recommended hidden" href="#gwapi-show-invalid-chars"
                 data-pretext="<?php esc_attr_e('The following characters are not valid GSM 03.38 characters:', 'gatewayapi'); ?>"><?php _e('Show invalid characters', 'gatewayapi'); ?></a>
            </td>
          </tr>
          <tr data-encoding="UCS2">
            <td>
              <label>
                <input type="radio" name="gatewayapi[encoding]"
                       value="UCS2" <?php echo ($destaddr === 'UCS2') ? 'checked' : ''; ?>>
                <abbr
                  title="<?php esc_attr_e('70 characters for 1-page SMS. 67 characters for multi-page SMS. Supports most special characters and emojis.', 'gatewayapi'); ?>"><?php _e('UCS2', 'gatewayapi'); ?></abbr>
              </label>
            </td>
            <td>
              <span class="UCS2-recommended hidden"
                    title="<?php esc_attr_e('Some special characters used require this encoding to be displayed properly.', 'gatewayapi'); ?>">✅</span>
              <span class="GSM0338-recommended"
                    title="<?php esc_attr_e('All characters in SMS are within GSM 03.38.', 'gatewayapi'); ?> <?php esc_attr_e('You should use GSM 03.38, as it roughly doubles the characters available per page over UCS2.', 'gatewayapi'); ?>">🛑</span>
            </td>
            <td></td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <th>
        <?php _e('Message', 'gatewayapi') ?>
      </th>
      <td>
        <?php
        $counterI18N = [
          'character' => __('character', 'gatewayapi'),
          'characters' => __('characters', 'gatewayapi'),
          'sms' => __('SMS', 'gatewayapi'),
          'smses' => __('SMS\'es', 'gatewayapi')
        ];
        ?>
        <textarea <?php echo ($published) ? 'readonly' : ''; ?> name="gatewayapi[message]" rows="10"
                                                         style="width: 100%"
                                                         placeholder="<?php esc_attr_e(__('Enter your SMS message here.', 'gatewayapi')); ?>"
                                                         data-counter-i18n="<?php echo esc_attr(json_encode($counterI18N)); ?>"
        ><?php echo esc_attr(get_post_meta($ID, 'message', true)); ?></textarea>
        <br>
        <div>
          <p><?php _e('Writing one of the following tags (including both &percnt;-signs) will result in each recipient receiving a personalized text:', 'gatewayapi'); ?></p>
          <ul>
            <?php foreach (gatewayapi__all_tags() as $tag => $description): ?>
              <li>
                <strong><?php echo esc_html($tag); ?></strong> - <?php echo esc_html($description); ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </td>
    </tr>
    </tbody>
  </table>
  <?php
}

function gatewayapi__sms_status(\WP_Post $post)
{
  $status = get_post_meta($post->ID, 'api_status', true);
  $ids = get_post_meta($post->ID, 'api_ids', true);
  $error = get_post_meta($post->ID, 'api_error', true);
  ?>
  <p><?php _e('The current status of the SMS is:', 'gatewayapi'); ?></p>
  <p>
    <?php
    switch ($status) {
      case 'about_to_send':
        echo __('About to send', 'gatewayapi');
        break;

      case 'sending':
        $is_sending = $post->batch_is_running;

        echo '<strong style="color: blue" data-is-sending>' . __('Sending...', 'gatewayapi') . '</strong>';
        break;

      case 'bail':
        echo '<span style="color: red"><strong>' . __('Failed before sending:', 'gatewayapi') . '</strong><br />' . $error . '</span>';
        break;

      case 'tech_error':
        $error_json = json_decode(json_decode(substr($error, 1, -1)));
        if ($error_json && isset($error_json->message)) {
          $error = $error_json->message;
        }
        echo '<span style="color: red"><strong>' . __('Technical error:', 'gatewayapi') . '</strong><br />' . $error . '</span>';
        break;

      case 'is_sent':
        echo '<span style="color: green; font-weight: bold">' . __('SMS was successfully sent', 'gatewayapi') . '</span><br /><div class="text-ellipsis">ID: ' . implode(', ', $ids) . '</div>';
        break;

      default:
        _e('Not sent yet', 'gatewayapi');
        break;
    }
    ?>
  </p>
  <script>
    const _gatewayapi_validate_recipient_nonce = <?php echo json_encode(wp_create_nonce('gatewayapi_validate_recipient')); ?>;
  </script>
  <?php

  if ($status == 'sending') gatewayapi__get_sent_status_progress($post);
}

function gatewayapi__get_sent_status_progress(\WP_Post $post)
{
  $recipients_total = (int)get_post_meta($post->ID, 'recipients_count', true);
  $recipients_handled = (int)(get_post_meta($post->ID, 'recipients_handled', true) ?: 0);
  ?>
  <div class="progress">
    <div class="completed"
         style="width: <?php $recipients_handled / $recipients_total * 100; ?>%"></div>
  </div>
  <?php
}


/**
 * Pre-flight an SMS-message.
 */
add_action('wp_ajax_gatewayapi_validate_sms', function () {
  header("Content-type: application/json");

  $data = [];
  parse_str($_POST['form_data'], $data);
  $errors = gatewayapi__validate_sms($data['gatewayapi']);

  if ($errors) {
    die(json_encode(['success' => false, 'failed' => $errors]));
  } else {
    die(json_encode(['success' => true]));
  }
});

/**
 * Save the SMS Meta data.
 */
function gatewayapi__sms_edit_save($ID)
{
  static $call_more_than_once;
  if (isset($call_more_than_once) && $call_more_than_once) return;
  $call_more_than_once = true;

  $data = $_POST['gatewayapi'] ?? [];
  if (!$data || !is_array($data)) return;

  // sms meta data
  if (isset($data['sender'])) update_post_meta($ID, 'sender', sanitize_text_field($data['sender']));
  if (isset($data['message'])) update_post_meta($ID, 'message', sanitize_textarea_field($data['message']));
  if (isset($data['destaddr'])) update_post_meta($ID, 'destaddr', sanitize_text_field($data['destaddr']));
  if (isset($data['encoding'])) update_post_meta($ID, 'encoding', sanitize_text_field($data['encoding']));

  // sources for recipients
  if (!isset($data['recipients'])) $data['recipients'] = [];
  update_post_meta($ID, 'recipients', $data['recipients']);

  // recipient groups
  if (in_array('groups', $data['recipients'])) {
    if (!isset($data['recipient_groups'])) $data['recipient_groups'] = [];
    foreach ($data['recipient_groups'] as &$id) {
      $id = (int)$id;
    }
    update_post_meta($ID, 'recipient_groups', $data['recipient_groups']);
  } else {
    delete_post_meta($ID, 'recipient_groups');
  }
}

add_action('save_post_gwapi-sms', 'gatewayapi__sms_edit_save');
add_action('publish_gwapi-sms', 'gatewayapi__sms_edit_save', 9);


/**
 * I18N: Rename "publish" to "send".
 */
add_action('current_screen', function ($current_screen) {
  if ($current_screen->post_type === 'gwapi-sms') {
    add_filter('gettext', function ($translated_text, $text, $domain) {
      if ($domain != 'default') return $translated_text;

      if ($text == 'Publish') return __('Send', 'gatewayapi');
      if ($text == 'Published on: <b>%1$s</b>') return __('Sent on: <b>%1$s</b>', 'gatewayapi');
      if ($text == 'Publish <b>immediately</b>') return __('Send <b>immediately</b>', 'gatewayapi');
      if ($text == 'Udgiv: <b>%1$s</b>') return __('Send on<b>%1$s</b>', 'gatewayapi');

      return $translated_text;
    }, 20, 3);
  }
});

/**
 * AJAX: Add a single recipient to an SMS.
 */
add_action('wp_ajax_gatewayapi_sms_manual_add_recipient', function () {
  header("Content-type: application/json");

  // verify nonce
  if (!wp_verify_nonce(sanitize_key($_GET['nonce'] ?? ''), 'gatewayapi_sms_manual_add_recipient')) die(json_encode(['success' =>
    false, 'errors' => ['*' => 'Invalid nonce, please reload the page.']]));

  // editor or above required
  if (!current_user_can('edit_others_posts')) die(json_encode(['success' => false, 'errors' => ['*' => 'You do not have the privileges to use this method.']]));

  $post_ID = sanitize_key($_POST['post_ID'] ?? '');
  if (!$post_ID || !ctype_digit($post_ID)) die(json_encode(['success' => false, 'errors' => ['*' => 'Invalid post ID.']]));
  $post_ID = (int)$post_ID;
  $post = get_post($post_ID);

  if (get_post_type($post_ID) != 'gwapi-sms') wp_die("WRONG_TYPE", "Wrong post type.");
  if (get_post_status($post_ID) == 'publish') die(json_encode(['success' => false, 'errors' => ['*' => 'This SMS has already been sent and thus it cannot be modified.']]));

  $data = $_POST['gatewayapi'] ?? [];
  $recipient = $data['single_recipient'] ?? [];
  $cc = sanitize_key($recipient['cc'] ?? null);
  $number = sanitize_key($recipient['number'] ?? null);
  $name = sanitize_text_field($recipient['name'] ?? '');

  if (!ctype_digit($cc)) die(json_encode(['success' => false, 'errors' => ['*' => 'The country code must contain digits only']]));
  if (!ctype_digit($number)) die(json_encode(['success' => false, 'errors' => ['*' => 'The phone number must contain digits only.']]));

  $data = ['cc' => $cc, 'number' => $number];

  $errors = [];
  $errors = gatewayapi__validate_recipient_basic($errors, $data, $post);

  if ($errors) {
    die(json_encode(['success' => false, 'errors' => $errors]));
  }

  // is this recipient already added to this sms?
  foreach (get_post_meta($post_ID, 'single_recipient') as $sr) {
    if ($sr['cc'] == $cc && $sr['number'] == $number) {
      die(json_encode(['success' => false, 'errors' => ['*' => 'The same recipient (country code + phone number) has already been added to this SMS.']]));
    }
  };

  // save the recipient on the SMS
  $metaID = add_post_meta($post_ID, 'single_recipient', ['cc' => $cc, 'number' => $number, 'name' => $name]);

  die(json_encode(['success' => true, 'ID' => $metaID]));
});

/**
 * AJAX: Remove single recipient from SMS.
 */
add_action('wp_ajax_gatewayapi_sms_manual_delete_recipient', function () {
  header("Content-type: application/json");

  // verify nonce
  if (!wp_verify_nonce(sanitize_key($_GET['nonce'] ?? ''), 'gatewayapi_sms_manual_delete_recipient')) die(json_encode(['success' => false, 'errors'
  => ['*' => 'Invalid nonce, please reload the page.']]));

  // editor or above required
  if (!current_user_can('edit_others_posts')) die(json_encode(['success' => false, 'errors' => ['*' => 'You do not have the privileges to use this method.']]));

  $post_ID = sanitize_key($_POST['post_ID'] ?? '');
  if (!ctype_digit($post_ID)) die(json_encode(['success' => false, 'errors' => ['*' => 'Invalid post ID.']]));
  $post_ID = (int)$post_ID;

  if (get_post_type($post_ID) != 'gwapi-sms') die(json_encode(['success' => false, 'message' => "Wrong post type."]));
  if (get_post_status($post_ID) == 'publish') die(json_encode(['success' => false, 'message' => 'This SMS has already been sent and thus it cannot be modified.']));

  global $wpdb;
  /** @var $wpdb wpdb */
  $meta_ID = sanitize_key($_POST['meta_ID'] ?? '');
  if (!$meta_ID && !ctype_digit($meta_ID)) die(json_encode(['success' => false, 'message' => "Bad meta ID."]));
  $meta_ID = (int)$meta_ID;

  $wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_id = %d AND meta_key = 'single_recipient';", $post_ID, $meta_ID)
  );

  die(json_encode(['success' => true ]));
});

add_action('wp_ajax_gatewayapi_get_html_status', function () {
  header("Content-type: text/html");

  // editor or above required
  if (!current_user_can('edit_others_posts')) die();

  $ID = (int)sanitize_key($_GET['ID'] ?? '');
  if (!$ID) die('<strong style="color: red">SMS ID invalid.</strong>');

  $post = get_post($ID);
  if (!$post || $post->post_type !== 'gwapi-sms') die('<strong style="color: red">Trying to load status on an invalid SMS-post.</strong>');

  gatewayapi__sms_status($post);
  die();
});
