<?php

// fields on the SMS editor page
add_action('admin_init', function () {
    add_meta_box('recipient', __('Contact information', 'gwapi'), '_gwapi_recipient', 'gwapi-recipient', 'normal', 'default');
    add_meta_box('custom_fields', __('Custom fields', 'gwapi'), '_gwapi_recipient_fields', 'gwapi-recipient', 'normal', 'default');
});

/**
 * Build the administration fields for editing a single recipient.
 */
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
                       placeholder="<?= esc_attr(__('Phone number - digits only', 'gwapi')) ?>"
                       value="<?= $number ? esc_attr($number) : '' ?>"
                       style="width: 250px">
            </td>
        </tr>
        </tbody>
    </table>
    <?php
}


/**
 * Render all the custom added form fields for a single recipient.
 */
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
add_action('save_post_gwapi-recipient', 'gwapi_save_recipient');

/**
 * Save the contents of a recipients form onto the recipient behind the given ID. Takes data from $_POST['gwapi'] if
 * data is not specified.
 */
function gwapi_save_recipient($ID, $data=null, $force_update=false) {
    if (!$force_update) {
        static $only_save_once;
        if (!$only_save_once) $only_save_once = [];
        if (in_array($ID, $only_save_once)) return; // only update info of same ID once ;-)
        $only_save_once[] = $ID;
    }

    if (!is_array($data)) {
        // do we have data at all?
        if (!isset($_POST['gwapi'])) return;
        $data = isset($_POST['gwapi']) ? $_POST['gwapi'] : false;
        if (!$data) return;
    }

    // get the possible fields
    foreach(_gwapi_all_recipient_fields() as $field) {
        $meta_key = strtolower($field['field_id']);

        // special case: name
        if ($meta_key == 'name' && isset($data['name'])) {
            wp_update_post([
                'ID' => $ID,
                'post_title' => $data[$meta_key],
                'post_type' => 'gwapi-recipient'
            ]);
            continue;
        }

        if (isset($data[ $meta_key ])) update_post_meta($ID, $meta_key, $data[$meta_key]);
        if ($field['type'] == 'checkbox' && !isset($data[ $meta_key ])) update_post_meta($ID, $meta_key, []);
    }
}

function _gwapi_save_recipient_groups($ID, $data, $atts)
{
    $valid_groups = isset($atts['groups']) ? explode(",", $atts['groups']) : false;
    $editable = isset($atts['edit-groups']) ? !!$atts['edit-groups'] : false;
    if (!$valid_groups && !$editable) return; // not using groups

    // if editable, use the selections in UI, otherwise subscribe to all
    $add_groups = $editable ? [] : $valid_groups;

    // selected groups
    if ($editable && isset($data['_gwapi_recipient_groups'])) {
        foreach($data['_gwapi_recipient_groups'] as $group_id) {
            if (!in_array($group_id, $valid_groups)) continue;
            $add_groups[] = $group_id;
        }
    }

    // make sure arrays contains integers
    foreach($valid_groups as &$vg) { $vg = (int)$vg; }
    foreach($add_groups as &$ag) { $ag = (int)$ag; }

    // remove the ones NOT selected for addition - we do it this way instead of just replacing ALL groups, in case the
    // admin has added some private groups to the recipient
    $to_remove = [];
    foreach($valid_groups as $vg_id) {
        if (!in_array($vg_id, $add_groups)) $to_remove[] = $vg_id;
    }
    if ($to_remove) wp_remove_object_terms($ID, $to_remove, 'gwapi-recipient-groups');

    // then we add the new ones
    wp_add_object_terms($ID, $add_groups, 'gwapi-recipient-groups');
}