<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

// save raw data transient
$data = get_transient('gwapi_import_' . get_current_user_id());

// analyse first row: extract column titles
$count = substr_count($data, "\n");
?>

  <p>
    <?php _e('Your import is running. It\'s important that you do not leave this page until it has finished.', 'gatewayapi'); ?>
  </p>

  <table class="form-table" id="importingStatus">
    <tbody>
    <tr>
      <th>
        <?php _e('Status', 'gatewayapi'); ?>
      </th>
      <td>
        <strong style="color: red" class="status_text"><?php _e('Importing...', 'gatewayapi'); ?></strong><br/>
      </td>
    </tr>
    <tr>
      <th>
        <?php _e('Rows', 'gatewayapi') ?>
      </th>
      <td>
        <span class="processed">0</span> / <span class="total"><?php echo esc_html($count); ?></span>
      </td>
    </tr>
    <tr>
      <th>
        <?php _e('Invalid rows', 'gatewayapi'); ?>
      </th>
      <td>
        <strong class="invalid_rows">0</strong>
      </td>
    </tr>
    <tr>
      <th><?php _e('New recipients', 'gatewayapi'); ?></th>
      <td>
        <strong class="count_new">0</strong>
      </td>
    </tr>
    <tr>
      <th><?php _e('Updated recipients', 'gatewayapi'); ?></th>
      <td>
        <strong class="count_updated">0</strong>
      </td>
    </tr>
    </tbody>
  </table>

  <script>
    var import_columns = <?php echo json_encode($_POST['columns']);?>;
    var gwapi_recipient_groups = <?php echo json_enocde($_POST['gwapi_recipient_groups'] ?? []); ?>;
    const _gatewayapi_import_nonce = <?php echo json_encode(wp_create_nonce('gwapi_import')); ?>;
  </script>
<?php wp_enqueue_script('gwapi_import', gatewayapi__url() . '/js/wpadmin-import.js'); ?>
