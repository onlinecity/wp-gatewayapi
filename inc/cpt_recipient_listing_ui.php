<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

/**
 * Define which columns we'll need.
 */
add_filter('manage_gwapi-recipient_posts_columns', function ($columns) {
  $date_text = $columns['date'];
  unset($columns['date']);

  return array_merge($columns, [
    'cc' => __('Country code', 'gatewayapi'),
    'mobile' => __('Mobile number', 'gatewayapi'),
    'groups' => __('Groups', 'gatewayapi'),
    'date' => $date_text
  ]);
});


/**
 * Print the content for our custom columns.
 */
add_action('manage_posts_custom_column', function ($column, $ID) {
  if (get_post_type($ID) != 'gwapi-recipient') return;

  switch ($column) {
    case 'cc':
      echo esc_html('+' . get_post_meta($ID, 'cc', true) ?: '-');
      break;

    case 'mobile':
      echo wp_trim_words(esc_html(get_post_meta($ID, 'number', true) ?: '-'), 8);
      break;

    case 'groups':
      $groups = wp_get_object_terms($ID, 'gwapi-recipient-groups');
      $list = [];
      foreach ($groups as $g) {
        $list[] = $g->name;
      }
      echo implode(', ', $list);
      if (!$list) echo '<em>' . __('None', 'gatewayapi') . '</em>';
      break;
  }

}, 10, 2);


/**
 * Hidden form for handling of recipient export to XLS/CSV.
 */
add_action('admin_footer', function () {
  global $query_string;
  global $current_screen;
  if ($current_screen->post_type != 'gwapi-recipient') return;
  ?>
  <form id="gwapiRecipientExportForm" method="post" action="edit.php?<?php echo $query_string; ?>">
    <input type="hidden" name="gwapi_recipient_export_format" value="">
    <input type="hidden" name="gwapi_recipient_export_nonce" value="<?php echo esc_attr(wp_create_nonce('gwapi_recipient_export')); ?>">
  </form>
  <?php
});


/**
 * Handle the export recipients to CSV/XLS request.
 */
add_action('parse_request', function ($wp) {
  global $current_screen;

  // admin: editor required
  if (!current_user_can('edit_others_posts')) return;

  // nonce
  if (!wp_verify_nonce($_POST['gwapi_recipient_export_nonce'], 'gwapi_recipient_export')) return;

  if (!is_object($current_screen)) return;
  if ($current_screen->post_type != 'gwapi-recipient') return;
  $export_format = $_POST['gwapi_recipient_export_format'] ?? null;
  if (!$export_format) return;

  switch ($export_format) {
    case 'xlsx':
      $format = 'xlsx';
      $filename = 'recipients.xlsx';
      break;
    default:
      $format = 'csv';
      $filename = 'recipients.csv';
      break;
  }
  $metas = [];
  foreach (get_option('gwapi_recipient_fields') as $field) {
    $metas[strtolower($field['field_id'])] = $field['name'];
  }

  $args = $wp->query_vars;
  $args['posts_per_page'] = -1;
  unset($args['paged']);
  $args['fields'] = 'ids';

  $q = new WP_Query($args);

  header('Pragma: public');
  header('Expires: 0');
  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
  header('Cache-Control: private', false);
  header('Content-Type: application/octet-stream');
  header('Content-Description: File Transfer');
  header('Content-Disposition: attachment; filename="' . $filename . '";');
  header('Content-Transfer-Encoding: binary');

  switch ($format) {
    case 'xlsx':
      include_once __DIR__ . "/../lib/xlsxwriter.class.php";
      $writer = new \XLSXWriter();
      $headers = [];
      foreach ($metas as $metaName) {
        $headers[$metaName] = 'string';
      }
      $writer->writeSheetHeader('Sheet1', $headers);
      foreach ($q->posts as $postID) {
        $metadata = get_post_meta($postID);
        $columns = [];
        foreach ($metas as $metaID => $metaName) {
          $columns[] = isset($metadata[$metaID]) ? $metadata[$metaID][0] : '';
        }
        @$writer->writeSheetRow('Sheet1', $columns);

        $q->next_post();
      }
      echo $writer->writeToString();
      break;
    default:
      $out = fopen('php://output', 'w');
      fputcsv($out, $metas);

      foreach ($q->posts as $postID) {
        $metadata = get_post_meta($postID);
        $row = [];
        foreach ($metas as $metaID => $metaName) {
          $row[] = isset($metadata[$metaID]) ? $metadata[$metaID][0] : '';
        }
        fputcsv($out, $row);
      }

      fclose($out);
      break;
  }
  wp_reset_query();
  die();
});
