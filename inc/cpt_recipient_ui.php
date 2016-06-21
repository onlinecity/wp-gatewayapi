<?php

// fields on the SMS editor page
add_action('admin_init', function () {
    add_meta_box('recipient', __('Contact information', 'gwapi'), '_gwapi_recipient', 'gwapi-recipient', 'normal', 'default');
    add_meta_box('custom_fields', __('Custom fields', 'gwapi'), '_gwapi_recipient_fields', 'gwapi-recipient', 'normal', 'default');
});

function _gwapi_recipient(WP_Post $post)
{
    $ID = $post->ID;
    $cc = get_post_meta($ID, 'cc', true);
    $number = get_post_meta($ID, 'number', true);

    ?>
    <div class="gwapi-star-errors"></div>
    <table width="100%" class="form-table">
        <tbody>
        <tr>
            <th width="25%">
                <?php _e('Country code', 'gwapi') ?>
            </th>
            <td>
                <select
                    name="gwapi[cc]"><?= $cc ? '<option value="' . $cc . '">' . $cc . '</option>' : '' ?></select>
            </td>
        </tr>
        <tr>
            <th width="25%">
                <?php _e('Phone number', 'gwapi') ?>
            </th>
            <td>
                <input type="number" name="gwapi[number]"
                       placeholder="Phone number - digits only"
                       value="<?= $number ? esc_attr($number) : '' ?>"
                       style="width: 250px">
            </td>
        </tr>
        </tbody>
    </table>
    <?php
}


function _gwapi_recipient_fields(WP_Post $post)
{
    $ID = $post->ID;
    $fields = get_option('gwapi_recipient_fields');
    ?>
    <div class="gwapi-star-errors"></div>
    <table width="100%" class="form-table">
        <tbody>
        <?php foreach($fields as $row): ?>
            <?php if (in_array($row['field_id'], ['CC', 'NUMBER', 'NAME'])) continue; ?>
            <?php gwapi_render_recipient_field($row, $post); ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php
}


// validate recipient
add_action('wp_ajax_gatewayapi_validate_recipient', function () {
    header("Content-type: application/json");

    $data = [];
    parse_str($_POST['form_data'], $data);
    $post = get_post((int) $data['post_ID'] );

    $errors = [];
    $errors = apply_filters('gwapi_validate_recipient', $errors, $data['gwapi'], $post);

    if ($errors) {
        die(json_encode(['success' => false, 'failed' => $errors]));
    } else {
        die(json_encode(['success' => true]));
    }
});


/**
 * Save recipient meta data
 */
add_action('save_post_gwapi-recipient', function ($ID) {
    if (!isset($_POST['gwapi'])) return;
    $data = isset($_POST['gwapi']) ? $_POST['gwapi'] : false;
    if (!$data) return;

    // get the possible fields
    foreach(_gwapi_all_recipient_fields() as $field) {
        $meta_key = strtolower($field['field_id']);
        if (isset($data[ $meta_key ])) update_post_meta($ID, $meta_key, $data[$meta_key]);
        if ($field['type'] == 'checkbox' && !isset($data[ $meta_key ])) update_post_meta($ID, $meta_key, []);
    }
});