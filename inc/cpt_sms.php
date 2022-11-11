<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

const GATEWAYAPI__UI_MAX_RECIPIENTS_PER_BATCH = 500;

// post type SMS'es
add_action('init', function () {

  $labels = array(
    'name' => __('SMS\'es', 'gatewayapi'),
    'singular_name' => __('SMS', 'gatewayapi'),
    'add_new' => __('Create SMS', 'gatewayapi'),
    'add_new_item' => __('Create new SMS', 'gatewayapi'),
    'edit_item' => __('View SMS', 'gatewayapi'),
    'new_item' => __('New SMS', 'gatewayapi'),
    'search_items' => __('Search SMS\'es', 'gatewayapi'),
    'not_found' => __('No SMS\'es found', 'gatewayapi'),
    'not_found_in_trash' => __('No SMS\'es found in trash', 'gatewayapi'),
    'menu_name' => __('SMS\'es', 'gatewayapi'),
  );

  $args = array(
    'labels' => $labels,
    'hierarchical' => false,
    'supports' => false,
    'public' => false,
    'show_ui' => get_option('gwapi_enable_ui'),
    'show_in_menu' => true,
    'menu_position' => 10,
    'menu_icon' => 'dashicons-format-chat',
    'show_in_nav_menus' => true,
    'publicly_queryable' => false,
    'exclude_from_search' => true,
    'has_archive' => false,
    'query_var' => false,
    'capability_type' => 'post'
  );

  register_post_type('gwapi-sms', $args);

  add_action('current_screen', function ($current_screen) {
    if ($current_screen->post_type === 'gwapi-sms') {
      // I18N: Rename texts
      add_filter('gettext', function ($translated_text, $text, $domain) {
        if ($domain != 'default') return $translated_text;
        if ($text === 'Edit') return __('View');
        return $translated_text;
      }, 20, 3);
    }
  });
});

/**
 * Send SMS when the post type is published.
 */
add_action('publish_gwapi-sms', function ($ID) {
  // already sent or anything else API-related?
  if (get_post_meta($ID, 'api_status', true)) return;

  // send the SMS now
  update_post_meta($ID, 'api_status', 'about_to_send');
  gatewayapi__prepare_sms($ID);
});

/**
 * Create recipients for the SMS.
 * Extract all the recipients from the database and inserts them into the gwapi_sms_recipients-table
 *
 * @internal
 */
function gatewayapi__create_recipients_for_sms($ID, $tags)
{
  $sources = get_post_meta($ID, 'recipients', true);

  $recipientsByNumber = [];
  $recipientsByID = [];

  // by recipient group
  if (!$sources || in_array('groups', $sources)) {
    $groups = get_post_meta($ID, 'recipient_groups', true);

    $recipientsQ = [
      "post_type" => "gwapi-recipient",
      "fields" => "ids",
      "posts_per_page" => -1
    ];

    if ($sources && in_array('groups', $sources)) {
      $recipientsQ["tax_query"] = [
        [
          'taxonomy' => 'gwapi-recipient-groups',
          'field' => 'term_id',
          'terms' => $groups
        ]
      ];
    }

    // fetch recipient ids and prepare for use in SQL
    $recipientIDs = implode(',', (new WP_Query($recipientsQ))->posts);

    // fetch the phone numbers
    global $wpdb;
    /** @var $wpdb wpdb */
    $tmp = [];
    foreach ($wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($recipientIDs) AND meta_key IN ('number', 'cc');") as $row) {
      $tmp[$row->post_id][$row->meta_key] = $row->meta_value;
    };
    foreach ($tmp as $recID => $row) {
      if (!$row['cc'] || !$row['number']) continue;
      $msisdn = gatewayapi__get_msisdn($row['cc'], $row['number']);
      $recipientsByNumber[$msisdn] = [];
      $recipientsByID[$recID] = $msisdn;
    }

    if (in_array('%NAME%', $tags)) {
      // fetch the names
      foreach ($wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($recipientIDs)") as $row) {
        $msisdn = $recipientsByID[$row->ID];
        $recipientsByNumber[$msisdn]['%NAME%'] = $row->post_title;
      }
    }

    // other tags to fetch from the database
    $tags_meta_keys = [];
    foreach ($tags as $tag) {
      $meta_key = strtolower(trim($tag, '%'));
      if ($meta_key == 'name') continue; // special
      $tags_meta_keys[] = $meta_key;
    }

    if ($tags_meta_keys) {
      $meta_keys_safe = [];
      foreach ($tags_meta_keys as $k) {
        $meta_keys_safe[] = $wpdb->_real_escape($k);
      }
      foreach ($wpdb->get_results($q = "SELECT post_ID, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_ID IN ($recipientIDs) AND meta_key IN ('" . implode("','", $meta_keys_safe) . "')") as $row) {
        $msisdn = $recipientsByID[$row->post_ID];
        $tag = '%' . strtoupper($row->meta_key) . '%';
        $tag_def = gatewayapi__get_tag_specification($tag);
        $recipientsByNumber[$msisdn][$tag] = apply_filters('gwapi_format_tag_' . $tag_def['type'], $row->meta_value, $tag_def);
      }
    }
  }

  // manually added
  foreach (get_post_meta($ID, 'single_recipient') as $sr) {
    $msisdn = gatewayapi__get_msisdn($sr['cc'], $sr['number']);
    if (isset($recipientsByNumber[$msisdn])) continue;

    $recipientsByNumber[$msisdn] = [];

    if (in_array('%NAME%', $tags)) {
      $recipientsByNumber[$msisdn]['%NAME%'] = $sr['name'];
    }
  }

  // ensure everybody has all tags, at least with just empty strings
  foreach ($tags as $t) {
    foreach ($recipientsByNumber as &$n) {
      if (!isset($n[$t])) {
        $n[$t] = '';
      }
    }
  }

  // save amount of recipients on the post
  update_post_meta($ID, 'recipients_count', count($recipientsByNumber));

  return $recipientsByNumber;
}

/**
 * Prepare the SMS to be sent and start sending.
 */
function gatewayapi__prepare_sms($ID)
{
  if (wp_is_post_revision($ID)) return; // no reason to spend any more time on a revision
  if (get_post_meta($ID, 'api_status', true) != 'about_to_send') return; // got here some wrong way
  update_post_meta($ID, 'api_status', 'sending');

  $sender = get_post_meta($ID, 'sender', true);
  $message = get_post_meta($ID, 'message', true);
  $destaddr = get_post_meta($ID, 'destaddr', true) ?: 'MOBILE';


  // don't send invalid sms
  if ($errors = gatewayapi__validate_sms([
    'sender' => $sender,
    'message' => $message,
    'destaddr' => $destaddr
  ])) {
    update_post_meta($ID, 'api_status', 'bail');
    update_post_meta($ID, 'api_error', __('Validation of the SMS failed prior to sending with the following errors:', 'gatewayapi') . "\n- " . implode("\n- ", $errors));
    return;
  }

  // missing secret etc.?
  if (!get_option('gwapi_key') || !get_option('gwapi_secret')) {
    update_post_meta($ID, 'api_status', 'bail');
    $no_api_error = strtr(__("You have not entered your OAuth key and secret yet. Go to :link to complete the setup.", 'gatewayapi'), [':link' => '<a href="options-general.php?page=gatewayapi">' . __('GatewayAPI Settings', 'gatewayapi') . '</a>']);
    update_post_meta($ID, 'api_error', $no_api_error);
    return;
  }

  // Extract all tags
  $allTags = gatewayapi__extract_tags_from_message($message);

  // Prepare the recipients
  $recipients = gatewayapi__create_recipients_for_sms($ID, $allTags);

  if (!$recipients) {
    update_post_meta($ID, 'api_status', 'bail');
    update_post_meta($ID, 'api_error', 'No recipients added.');
    return;
  }

  // preflight
  $preflight = wp_remote_post(admin_url('admin-ajax.php'), [
    'body' => [
      'action' => 'gwapi_send_next_batch_preflight',
      'post_ID' => $ID
    ],
    'timeout' => 5
  ]);

  // yea, we can defer to "background-process"
  if (trim(wp_remote_retrieve_body($preflight)) === 'success') {
    wp_remote_post(admin_url('admin-ajax.php'), [
      'body' => [
        'action' => 'gwapi_send_next_batch',
        'post_ID' => $ID
      ],
      'timeout' => 5
    ]);
  } else { // we can't background it, so just do in same thread
    set_time_limit(-1);
    do_action('wp_ajax_nopriv_gatewayapi_send_next_batch', false, $ID);
  }
}

;

/**
 * Batch-sending PREFLIGHT
 */
add_action('wp_ajax_nopriv_gatewayapi_send_next_batch_preflight', function () {
  die('success');
});


/**
 * AJAX: Send next batch of SMS.
 */
add_action('wp_ajax_nopriv_gatewayapi_send_next_batch', function ($can_use_remote = true, $post_ID = false) {
  if ($post_ID === false) {
    $post_ID = (int)preg_replace('/\D+/', '', sanitize_key($_POST['post_ID'] ?? 0));
  }
  if (!$post_ID) throw new \InvalidArgumentException('Invalid post ID.');

  $post = get_post($post_ID);
  if (!$post) {
    throw new \InvalidArgumentException('Invalid post ID.');
  }

  $ID = $post->ID;

  try {
    if (get_post_type($post) != 'gwapi-sms') throw new InvalidArgumentException(__('Invalid post type.', 'gatewayapi'));
    $status = get_post_meta($post->ID, 'api_status', true);
    if ($status != 'sending') throw new \InvalidArgumentException(__('SMS is not in the right state for sending.', 'gatewayapi'));
    if ($post->batch_is_running) throw new \InvalidArgumentException(__('Sending is already in progress', 'gatewayapi'));

    update_post_meta($ID, 'batch_is_running', time());

    // who is in next batch?
    $handled_count = (int)$post->recipients_handled ?: 0;

    // ensure we don't try to send the same batch multiple times
    update_post_meta($ID, 'recipients_handled', $handled_count + GATEWAYAPI__UI_MAX_RECIPIENTS_PER_BATCH);

    // base information for SMS
    $sender = $post->sender;
    $message = $post->message;
    $destaddr = $post->destaddr ?: 'MOBILE';
    $encoding = $post->encoding === 'UCS2' ? 'UCS2' : 'UTF8';

    // get all recipients
    $allTags = gatewayapi__extract_tags_from_message($message);
    $allRecipients = gatewayapi__create_recipients_for_sms($ID, $allTags);
    $recipients = array_slice($allRecipients, $handled_count, GATEWAYAPI__UI_MAX_RECIPIENTS_PER_BATCH, true);

    $send_req = gatewayapi_send_sms($message, $recipients, $sender, $destaddr, $encoding);

    if (!is_wp_error($send_req)) {
      if ($handled_count + GATEWAYAPI__UI_MAX_RECIPIENTS_PER_BATCH >= count($allRecipients)) {
        update_post_meta($ID, 'api_status', 'is_sent');
      }

      $ids = $post->api_ids ?: [];
      $ids[] = $send_req;
      update_post_meta($ID, 'api_ids', $ids);
    } else {
      /** @var $send_req WP_Error */
      update_post_meta($ID, 'api_status', 'tech_error');
      update_post_meta($ID, 'api_error', json_encode($send_req->get_error_message()));
    }

    // reset the "batch_is_running" status
    update_post_meta($post->ID, 'batch_is_running', false);

  } catch (\Exception $e) {
    update_post_meta($ID, 'api_status', 'tech_error');
    update_post_meta($ID, 'api_error', $e->getMessage());
  }

  $post = get_post($ID);
  if ($post->api_status != 'sending') return;

  if ($can_use_remote) {
    wp_remote_post(admin_url('admin-ajax.php'), [
      'body' => [
        'action' => 'gwapi_send_next_batch',
        'post_ID' => $ID
      ],
      'blocking' => false
    ]);
  } else {
    do_action('wp_ajax_nopriv_gatewayapi_send_next_batch', false, $ID);
  }

}, 10, 2);

/**
 * Checkbox-tag: Unserialize the raw database value and comma-separate the list.
 */
add_filter('gwapi_format_tag_checkbox', function ($value, $def) {
  $value = unserialize($value);

  if (!$value) return __('None', 'gatewayapi');
  return implode(', ', $value);
}, 5, 2);
