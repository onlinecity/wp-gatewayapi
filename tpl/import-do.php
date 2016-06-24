<?php

// save raw data transient
$data = get_transient('gwapi_import_' . get_current_user_id());

// analyse first row: extract column titles
$count = substr_count($data, "\n");
?>

<p>
    <?php _e('Your import is running. It\'s important that you do not leave this page until it has finished.' ,'gwapi'); ?>
</p>

<table class="form-table" id="importingStatus">
    <tbody>
        <tr>
            <th>
                <?php _e('Status', 'gwapi'); ?>
            </th>
            <td>
                <strong style="color: red" class="status_text"><?php _e('Importing...','gwapi'); ?></strong><br />
            </td>
        </tr>
        <tr>
            <th>
                <?php _e('Rows', 'gwapi') ?>
            </th>
            <td>
                <span class="processed">0</span> / <span class="total"><?= $count ?></span>
            </td>
        </tr>
        <tr>
            <th>
                <?php _e('Invalid rows', 'gwapi'); ?>
            </th>
            <td>
                <strong class="invalid_rows">0</strong>
            </td>
        </tr>
        <tr>
            <th><?php _e('New recipients', 'gwapi'); ?></th>
            <td>
                <strong class="count_new">0</strong>
            </td>
        </tr>
        <tr>
            <th><?php _e('Updated recipients', 'gwapi'); ?></th>
            <td>
                <strong class="count_updated">0</strong>
            </td>
        </tr>
    </tbody>
</table>

    <script>
        var import_columns = <?=json_encode($_POST['columns']);?>;
        var gwapi_recipient_groups = <?= json_encode($_POST['gwapi_recipient_groups']); ?>
    </script>
<?php wp_enqueue_script('gwapi_import', _gwapi_url().'/js/wpadmin-import.js'); ?>