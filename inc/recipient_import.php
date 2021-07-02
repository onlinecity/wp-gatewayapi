<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

add_action('admin_menu', function () {
  // editor or above required
  if (!current_user_can('edit_others_posts')) die(json_encode(['success' => false, 'errors' => ['*' => 'You do not have the privileges to use this method.']]));

  add_action('current_screen', 'gatewayapi__import_table_sync');
  add_submenu_page('edit.php?post_type=gwapi-sms', __('Import recipients from spreadsheet', 'gatewayapi'), __('Import recipients', 'gatewayapi'), 'edit_posts', 'gwapi_import', function () {
    require_once(__DIR__ . "/../tpl/import.php");
  });
}, 20);

function gatewayapi__import_table_sync($current_screen)
{
  global $wpdb;
  $is_subpage = isset($_POST['step']);

  $post_table = $wpdb->prefix . 'posts';
  $recipients_import_table = $wpdb->prefix . 'oc_recipients_import';

  // Only proceed if the recipients table already exists...
  if ($wpdb->get_var("SHOW TABLES LIKE '$recipients_import_table'") !== $recipients_import_table) {
    return;
  }

  // On the first page of import recipients - make sure we have no imported recipients
  // in the custom table that does not have corresponding post
  if (!$is_subpage && $current_screen->id === 'gwapi-sms_page_gatewayapi_import') {
    // Remove all imported recipients if the posts was deleted and the matching row in the import table was not.
    $result = $wpdb->query('DELETE from ' . $recipients_import_table . ' WHERE post_id NOT IN (SELECT p.ID from ' . $post_table . ' p)');
  }
}

add_action('wp_ajax_gatewayapi_import', function () {
  global $wpdb;

  // editor required
  if (!current_user_can('edit_others_posts')) return;

  // nonce validate
  if (!wp_verify_nonce(sanitize_key($_POST['nonce']), 'gwapi_import')) {
    http_response_code(400);
    die('Bad nonce');
  }

  header('Content-type: application/json');

  $post_table = $wpdb->prefix . 'posts';
  $recipients_import_table = $wpdb->prefix . 'oc_recipients_import';
  $data = get_transient('gwapi_import_' . get_current_user_id());
  $page = (int)sanitize_key($_POST['page']);
  $per_page = (int)sanitize_key($_POST['per_page']);
  $ccCol = sanitize_key($_POST['columns']['cc']);
  $noCol = sanitize_key($_POST['columns']['number']);
  $nameCol = sanitize_key($_POST['columns']['name']);

  $rows = array_slice(explode("\n", $data), $page * $per_page + 1, $per_page);

  $failed = 0;
  $new = 0;
  $updated = 0;

  wp_defer_term_counting(true);
  foreach ($rows as $row) {
    $ID = null;
    $cols = explode("\t", $row);

    $cc = trim(preg_replace('/\D+/', '', $cols[$ccCol]));
    $number = trim(preg_replace('/\D+/', '', $cols[$noCol]));
    if (!$cc || !$number) {
      $failed++;
      continue;
    }

    // find out if post exists
    $row = $wpdb->get_row($wpdb->prepare(
      'SELECT DISTINCT id, post_id FROM ' . $recipients_import_table . '	WHERE country_code = %d AND phone_number = %d', $cc, $number
    ), OBJECT);

    $row_exist = !empty($row);
    if (!$row_exist) {
      $new++;
      $newID = wp_insert_post([
        "ID" => $ID,
        "post_title" => sanitize_text_field($cols[$nameCol] ?? null),
        "post_name" => $number,
        "post_type" => "gwapi-recipient",
        "post_status" => $ID ? get_post_status($ID) : "publish",
      ]);
      $ID = $newID ?: $ID;

      // Create the row in a indexed table for faster lookup
      $wpdb->insert(
        $recipients_import_table,
        array(
          'phone_number' => $number,
          'country_code' => $cc,
          'post_id' => (int)$newID,
        )
      );
      $record_id = $wpdb->insert_id;
    } else {
      $updated++;
      $ID = $row->post_id;
      wp_update_post([
        "ID" => $ID,
        "post_name" => $number,
        "post_type" => "gwapi-recipient",
        "post_status" => $ID ? get_post_status($ID) : "publish",
      ]);
    }

    // recipient groups
    $groups = isset($_POST['gwapi-recipient-groups']) ? $_POST['gwapi-recipient-groups'] : [];

    // Make sure groups are integers
    $groups = array_map('intval', $groups);
    $groups = array_unique($groups);

    if ($groups) wp_set_object_terms($ID, $groups, 'gwapi-recipient-groups', false);

    foreach ($_POST['columns'] as $key => $idx) {
      if (!strlen($idx)) continue;
      if ($key == 'name') continue;
      update_post_meta($ID, $key, $cols[$idx]);
    }
  }
  wp_defer_term_counting(false);
  echo json_encode(['failed' => $failed, 'new' => $new, 'updated' => $updated]);

  exit;
});
