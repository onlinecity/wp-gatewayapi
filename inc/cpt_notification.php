<?php

use OnlineCity\GatewayAPI\TriggerStore;

if (!defined('ABSPATH')) die('Cannot be accessed directly!');

$plugin_dir = gatewayapi__dir();

require_once "$plugin_dir/src/classes/Notification.php";
require_once "$plugin_dir/src/classes/Trigger.php";
require_once "$plugin_dir/src/classes/TriggerStore.php";

// Hooking up our function to theme setup
/*
* Creating a function to create our CPT
*/

function gatewayapi__cpt_notification()
{

//    gwapi_callback_autocomplete_recipient();
// Set UI labels for Custom Post Type
  $labels = [
    'name' => _x('Notifications', 'Post Type General Name', 'gatewayapi'),
    'singular_name' => _x('Notification', 'Post Type Singular Name', 'gatewayapi'),
    'menu_name' => __('Notifications', 'gatewayapi'),
    'parent_item_colon' => __('Parent Notification', 'gatewayapi'),
    'all_items' => __('All Notifications', 'gatewayapi'),
    'view_item' => __('View Notification', 'gatewayapi'),
    'add_new_item' => __('Add New Notification', 'gatewayapi'),
    'add_new' => __('Add New', 'gatewayapi'),
    'edit_item' => __('Edit Notification', 'gatewayapi'),
    'update_item' => __('Update Notification', 'gatewayapi'),
    'search_items' => __('Search Notification', 'gatewayapi'),
    'not_found' => __('No Notifications found', 'gatewayapi'),
    'not_found_in_trash' => __('Not found in Trash', 'gatewayapi'),
  ];

// Set other options for Custom Post Type

  $args = [
    'label' => __('Notifications', 'gatewayapi'),
    'description' => __('SMS Notifications for actions', 'gatewayapi'),
    'labels' => $labels,
    'supports' => ['title'],
    'hierarchical' => false,
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => false,
    'show_in_nav_menus' => true,
    'show_in_admin_bar' => true,
    'menu_position' => 10,
    'can_export' => true,
    'has_archive' => false,
    'exclude_from_search' => true,
    'publicly_queryable' => false,
    'capability_type' => 'post',
  ];


  // Registering your Custom Post Type
  register_post_type('gwapi-notification', $args);
  TriggerStore::listen();
}

/**
 * Define which columns we'll need.
 */
add_filter('manage_gwapi-notification_posts_columns', function ($columns) {
  $date_text = $columns['date'];
  unset($columns['date']);

  return array_merge($columns, [
    'trigger' => __('Trigger', 'gatewayapi'),
    'recipients' => __('Recipients', 'gatewayapi'),
    'date' => $date_text,
  ]);
});

/**
 * Print the content for our custom columns.
 */
add_action('manage_posts_custom_column', function ($column, $id) {
  if (get_post_type($id) !== 'gwapi-notification') {
    return;
  }
  switch ($column) {
    case 'trigger':
      $triggers = get_post_meta($id, 'triggers', true);
      $trigger = gatewayapi__get_trigger_by_id($triggers);
      echo esc_html($trigger ? $trigger->getName() : '-');
      break;
    case 'recipients':
      $recipient_type = get_post_meta($id, 'recipient_type', true);
      switch ($recipient_type) {
        case 'recipient':
          esc_html_e('Single recipient', 'gatewayapi');
          break;
        case 'recipientGroup':
          esc_html_e('Recipient Group', 'gatewayapi');
          break;
        case 'role':
          esc_html_e('Role', 'gatewayapi');
          break;
      }
      break;
  }
}, 10, 2);


function gatewayapi__cpt_notification_admin_menu()
{
  add_submenu_page('edit.php?post_type=gwapi-sms',
    __('Notifications (beta)', 'gatewayapi'),
    __('Notifications (beta)', 'gatewayapi'),
    'manage_options',
    'edit.php?post_type=gwapi-notification',
    '',
    5
  );
}

/* Hook into the 'init' action so that the function
* Containing our post type registration is not
* unnecessarily executed.
*/

add_action('init', 'gatewayapi__cpt_notification');
add_action('admin_menu', 'gatewayapi__cpt_notification_admin_menu');

// fields on the SMS editor page
add_action('admin_init', function () {
  add_meta_box('notification_meta_triggers', __('Triggers', 'gatewayapi'), 'gatewayapi__notification_meta_triggers', 'gwapi-notification', 'normal', 'default');
  add_meta_box('notification_meta_groups', __('Recipients', 'gatewayapi'), 'gatewayapi__notification_meta_groups', 'gwapi-notification', 'normal', 'default');
  add_meta_box('notification_meta_message', __('Message', 'gatewayapi'), 'gatewayapi__notification_meta_message', 'gwapi-notification', 'normal', 'default');
});

add_action('admin_enqueue_scripts', 'gatewayapi__notification_enqueue_scripts');

function gatewayapi__notification_enqueue_scripts($hook)
{

  wp_enqueue_style('gwapi-wp-notification', gatewayapi__url() . '/css/gwapi-notification.css');

  $transient_name = 'gwapi_notification_posts';

  $cached_posts_titles = [];

  // check if cached post titles are available in the transient.
  $cached_posts = get_transient($transient_name);
  if ($cached_posts) {
    foreach ($cached_posts as $index => $post) {
      $cached_posts_titles[$index] = $post['title'];
    }
  }

  $params = [
    'cached_post_titles' => $cached_posts_titles,
  ];

  wp_localize_script('gwapi_notification', 'params', $params);
}


/**
 * Build the administration fields for triggers
 */
function gatewayapi__notification_meta_triggers(\WP_Post $post)
{
  $triggers = gatewayapi__get_triggers_grouped();
  gatewayapi__render_template('notification/triggers', ['post' => $post]);
}

/**
 * Build the administration fields recipients
 */
function gatewayapi__notification_meta_groups(\WP_Post $post)
{
  gatewayapi__render_template('notification/groups', ['post' => $post]);
}

/**
 * Build the administration fields for the message
 */
function gatewayapi__notification_meta_message(\WP_Post $post)
{
  gatewayapi__render_template('notification/message', ['post' => $post]);
}

/**
 * Save recipient meta data
 */
add_action('save_post_gwapi-notification', 'gatewayapi__save_notification');

/**
 * Save the contents of a recipients form onto the recipient behind the given ID. Takes data from $_POST['gatewayapi'] if
 * data is not specified.
 *
 * @param int $post_id
 * @param \WP_Post $post
 * @param bool $update
 */
function gatewayapi__save_notification(int $post_id, WP_Post $post = null, bool $update = false)
{
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

  // editor required
  if (!current_user_can('edit_others_posts')) return;

  $data = $_POST['gatewayapi'] ?? null;
  if (!is_array($data)) {
    return; // not in a relevant state to save the notification
  }

  // sanitize all input
  $triggers = sanitize_text_field($data['triggers']);
  $recipient_type = sanitize_text_field($data['recipient_type']);
  if (!in_array($recipient_type, ['recipient', 'recipientGroup', 'role'])) {
    wp_die('Could not save notification: Recipient type is invalid: ' .esc_html($recipient_type));
  }

  $recipient_id = $recipient_type === 'recipient' ? $data['recipient_id'] : null;
  $recipient_name = $recipient_type === 'recipient' ? sanitize_text_field($data['recipient_name']) : null;
  $recipient_groups = $recipient_type === 'recipientGroup' ? $data['recipient_groups'] : null;
  $recipient_roles = $recipient_type === 'role' ? $data['roles'] : null;

  if ($recipient_id && !ctype_digit($recipient_id)) {
    wp_die('Could not save notification: Recipient ID is invalid (should be digits only): '.esc_html($recipient_id));
  }

  if ($recipient_groups) {
    foreach($recipient_groups as &$g) {
      if (!ctype_digit($g)) {
        wp_die('Could not save notification: Recipient group ID is invalid (should be digits only): '.esc_html($g));
      }
    }
  }

  if ($recipient_roles) {
    foreach($recipient_roles as &$r) {
      $r = sanitize_key($r);
    }
  }

  $sender = sanitize_text_field($data['sender']);
  $destaddr = $data['destaddr'];
  if (!in_array($destaddr, ['MOBILE', 'DISPLAY'])) wp_die('Could not save notification: Destination type is invalid (must be either MOBILE or DISPLAY): '.esc_html($destaddr));

  $encoding = $data['encoding'];
  if (!in_array($encoding, ['GSM0338', 'UCS2'])) wp_die('Could not save notification: Encoding is invalid (must be either GSM0338 or UCS2): ' .esc_html($encoding));
  $message = sanitize_textarea_field($data['message']);

  // all good! save
  update_post_meta($post_id, 'triggers', $triggers);
  update_post_meta($post_id, 'recipient_type', $recipient_type);
  update_post_meta($post_id, 'recipient_id', $recipient_id);
  update_post_meta($post_id, 'recipient_name', $recipient_name);
  update_post_meta($post_id, 'recipient_groups', $recipient_groups);
  update_post_meta($post_id, 'roles', $recipient_roles);
  update_post_meta($post_id, 'sender', $sender);
  update_post_meta($post_id, 'destaddr', $destaddr);
  update_post_meta($post_id, 'encoding', $encoding);
  update_post_meta($post_id, 'message', $message);
}


// Same handler function...
add_action('wp_ajax_gatewayapi_callback_autocomplete_recipient', 'gatewayapi__callback_autocomplete_recipient');

function gatewayapi__callback_autocomplete_recipient()
{
  // admin: editor required
  if (!current_user_can('edit_others_posts')) return;

  // only accept with proper nonce
  if (!wp_verify_nonce(sanitize_key($_POST['nonce']??''), 'gwapi_callback_autocomplete_recipient')) return;

  $recipients = [];

  // retrieve the post types to search from the plugin settings.
  $post_types = 'gwapi-recipient';

  // run a new query against the search key and the cached post ids for the seleted post types.
  $args = [
    'post_type' => $post_types,
    'posts_per_page' => -1,
    'no_found_rows' => true, // as we don't need pagination.
    'ignore_sticky_posts' => true,
    'meta_key' => 'number',
  ];

  $posts = get_posts($args);

  foreach ($posts as $post) {
    $ID = $post->ID;

    if (empty($post->post_title)) {
      continue;
    }

    $recipient = [
      'id' => $ID,
      'name' => $post->post_title,
      'cc' => get_post_meta($ID, 'cc', true),
      'number' => get_post_meta($ID, 'number', true),
    ];

    $recipients[] = $recipient;
  }

  echo json_encode($recipients);
  wp_die();
}


function gatewayapi__notification_get_notifications()
{
  $args = [
    'post_type' => 'gwapi-notification',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'no_found_rows' => true, // true by default.
    'suppress_filters' => true, // true by default.
    'ignore_sticky_posts' => true, // true by default.
  ];

  // get_posts() to retrieve posts belonging to the required post types.
  return get_posts($args);
}

function gatewayapi__notification_get_recipients_by_id($ids)
{

  if (!is_array($ids)) {
    $ids = [$ids];
  }

  $args = [
    'post_type' => 'gwapi-recipient',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'include' => $ids,
    'no_found_rows' => true, // true by default.
    'suppress_filters' => true, // true by default.
    'ignore_sticky_posts' => true, // true by default.
  ];

  // get_posts() to retrieve posts belonging to the required post types.
  $posts = get_posts($args);

  return gatewayapi__notification_recipients_format($posts);
}


function gatewayapi__notification_recipients_format($recipients)
{
  $response = [];

  /**
   * @var $recipient \WP_Post
   */
  foreach ($recipients as $recipient) {

    $tags['%NAME%'] = $recipient->post_title;

    $ignored_meta = ['api_status', '_edit_lock', '_edit_last'];
    $meta = get_post_meta($recipient->ID);

    foreach ($meta as $key => $value) {
      if (in_array($key, $ignored_meta, true)) {
        continue;
      }

      $tag = "%" . strtoupper($key) . '%';
      $tags[$tag] = current($value);
    }

    $msisdn = current($meta['cc']) . current($meta['number']);

    $response[$msisdn] = $tags;
  }

  return $response;
}
