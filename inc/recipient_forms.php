<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

function gatewayapi__all_recipient_fields () {
    $default_fields = [
        [
            'is_builtin' => true,
            'name' => __('Name', 'gatewayapi'),
            'field_id' => 'NAME',
            'description' => __('Full name of recipient','gatewayapi'),
            'required' => true,
            'type' => 'text'
        ], [
            'is_builtin' => true,
            'name' => __('Mobile country code', 'gatewayapi'),
            'field_id' => 'CC',
            'description' => __('Mobile country code of recipient', 'gatewayapi'),
            'required' => true,
            'type' => 'mobile_cc'
        ], [
            'is_builtin' => true,
            'name' => __('Mobile number', 'gatewayapi'),
            'field_id' => 'NUMBER',
            'description' => __('Mobile number of recipient', 'gatewayapi'),
            'required' => true,
            'type' => 'digits'
        ]
    ];

    $cur_fields = get_option('gwapi_recipient_fields') ? : [];

    // any fields missing?
    $must_add = ['NAME','CC','NUMBER'];
    $is_added = [];
    foreach($cur_fields as $cf) {
        if (in_array($cf['field_id'], $must_add)) $is_added[] = $cf['field_id'];
    }

    $must_add = array_diff($must_add, $is_added);
    foreach($must_add as $ma) {
        foreach($default_fields as $df) {
            if ($df['field_id'] == $ma) $cur_fields[] = $df;
        }
    }

    return $cur_fields;
}


function gatewayapi__all_recipient_field_types($grouped = false)
{
    $types = [
        __('Text', 'gatewayapi') => [
            'text' => __('Plain text', 'gatewayapi'),
            'email' => __('E-mail', 'gatewayapi'),
            'url' => __('URL', 'gatewayapi'),
            'digits' => __('Digits only', 'gatewayapi'),
            'textarea' => __('Textarea', 'gatewayapi'),
            'password_plain' => __('Insecure (visible in backend and SMS\'es)', 'gatewayapi'),
        ],
        __('Multiple choices', 'gatewayapi') => [
            'select' => __('Select (single)','gatewayapi'),
            'radio' => __('Radio (single)','gatewayapi'),
            'checkbox' => __('Checkbox (multiple)','gatewayapi')
        ],
        __('Special', 'gatewayapi') => [
            'mobile_cc' => __('Mobile country code','gatewayapi'),
            'hidden' => __('Hidden (only visible in backend)','gatewayapi')
        ]
    ];

    if ($grouped) return $types;

    static $flat;
    if (isset($flat)) return $flat;

    $flat = [];
    foreach($types as $group) {
        $flat = array_merge($flat, $group);
    }

    return $flat;
}

/**
 * Prepare the data for serialization, if necessary.
 */
add_filter('pre_update_option_gatewayapi_recipient_fields', function($new_value) {
    // do we need to do anything about it?
    if (!isset($new_value['name'])) return $new_value;

    $serialized = [];
    foreach($new_value as $field_name => $values) {
        foreach($values as $field_no => $value) {
            $serialized[$field_no][$field_name] = $value;
        }
    }

    return $serialized;
});

function gatewayapi__render_recipient_field($row, WP_Post $recipient)
{
    $render_hidden = apply_filters('gwapi_recipient_render_hidden', is_admin());
    $style = apply_filters('gwapi_recipient_field_style', is_admin() ? 'table' : 'div');

    $base_html = apply_filters('gwapi_recipient_fields_base_'.$style.'_html', '', $style);
    $base_html = apply_filters('gwapi_recipient_fields_base_html', $base_html, $style);

    $row = apply_filters('gwapi_recipient_row_attributes', $row);
    $field_html = apply_filters('gwapi_recipient_field_'.$row['type'].'_html', '', $recipient, $row);
    $field_html = apply_filters('gwapi_recipient_field_html', $field_html, $recipient, $row);

    echo strtr($base_html, [
        ':label' => $row['name'],
        ':description' => $row['description'],
        ':field' => $field_html
    ]);
}

/**
 * Base HTML for table-style.
 */
add_filter('gwapi_recipient_fields_base_table_html', function($html, $style) {
    return '<tr><th width="25%">:label<div class="info hidden">:description</div></th><td>:field</td></tr>';
}, 1, 2);

/**
 * Base HTML for div-style.
 */
add_filter('gwapi_recipient_fields_base_div_html', function($html, $style) {
    return '<div class="form-group"><label>:label <div class="info hidden">:description</div></label><div class="form-field">:field</div></div>';
}, 1, 2);

/**
 * Render a text input field
 */
add_filter('gwapi_recipient_field_text_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);
    $required = isset($field['required']) && $field['required'];
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    return '<input'.$disabled.' type="text" name="gatewayapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render an e-mail input field
 */
add_filter('gwapi_recipient_field_email_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);
    $required = isset($field['required']) && $field['required'];
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    return '<input'.$disabled.' type="email" name="gatewayapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render a URL input field
 */
add_filter('gwapi_recipient_field_url_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);
    $required = isset($field['required']) && $field['required'];
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    return '<input'.$disabled.' type="url" name="gatewayapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render a number-only input field
 */
add_filter('gwapi_recipient_field_digits_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);
    $required = isset($field['required']) && $field['required'];
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    return '<input'.$disabled.' type="number" step="1" min="0" name="gatewayapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render a textarea input field
 */
add_filter('gwapi_recipient_field_textarea_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);
    $required = isset($field['required']) && $field['required'];
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    return '<textarea'.$disabled.' rows="5" cols="30" name="gatewayapi['.strtolower($field['field_id']).']" '.($required?'required':'').'>'.esc_html($recipient_value).'</textarea>';
}, 1, 3);

/**
 * Render an insecure password field
 */
add_filter('gwapi_recipient_field_password_plain_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);
    $required = isset($field['required']) && $field['required'];
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    return '<input'.$disabled.' type="'.(is_admin() ? 'text' : 'password').'" name="gatewayapi['.strtolower($field['field_id']).']" value="'.esc_attr(is_admin() ? $recipient_value : '').'" placeholder="'.(!is_admin() && $recipient_value ? 'Enter a value to update' : '').'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render a regular select-field.
 */
add_filter('gwapi_recipient_field_select_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);
    $required = isset($field['required']) && $field['required'];
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    $html = '<select'.$disabled.' name="gatewayapi['.$field_meta_key.']">';
    if (!$required) {
        $html .= '<option></option>';
    }

    $choices = explode("\n", $field['choices']);
    foreach($choices as $choice) {
        $is_selected = strpos($choice, '!!') === 0;
        if ($is_selected) {
            $choice = substr($choice, 2);
        }
        $choice = trim($choice);
        if (!$choice) continue;

        if ($recipient_value) {
            $is_selected = $recipient_value == $choice;
        }

        $html .= '<option value="'.esc_attr($choice).'" '.($is_selected?'selected':'').'>'.esc_html($choice).'</option>';
    }
    $html .= '</select>';

    return $html;
}, 1, 3);

/**
 * Render a group of radio fields.
 */
add_filter('gwapi_recipient_field_radio_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    $choices = explode("\n", $field['choices']);
    foreach($choices as $choice) {
        $is_selected = strpos($choice, '!!') === 0;
        if ($is_selected) {
            $choice = substr($choice, 2);
        }
        $choice = trim($choice);
        if (!$choice) continue;

        if ($recipient_value) {
            $is_selected = $recipient_value == $choice;
        }

        $html .= '<div><label><input'.$disabled.' required type="radio" name="gatewayapi['.$field_meta_key.']" value="'.esc_attr($choice).'" '.($is_selected?'checked':'').'>'.esc_html($choice).'</label></div>';
    }

    return $html;
}, 1, 3);

/**
 * Render a group of checkbox fields.
 */
add_filter('gwapi_recipient_field_checkbox_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);
    $required = isset($field['required']) && $field['required'];
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    $choices = explode("\n", $field['choices']);
    foreach($choices as $i => $choice) {
        $is_selected = strpos($choice, '!!') === 0;
        if ($is_selected) {
            $choice = substr($choice, 2);
        }
        $choice = trim($choice);
        if (!$choice) continue;

        if (is_array($recipient_value)) {
            if (!$recipient_value) {
                $is_selected = false;
            } else {
                $is_selected = in_array($choice, $recipient_value);
            }
        }

        $key = isset($field['choices_keys']) ? $field['choices_keys'][$i] : $choice;

        $html .= '<div><label><input'.$disabled.' '.($required?'required':'').' type="checkbox" name="gatewayapi['.$field_meta_key.'][]" value="'.esc_attr($key).'" '.($is_selected?'checked':'').'>'.esc_html($choice).'</label></div>';
    }

    return $html;
}, 1, 3);

/**
 * Render a HIDDEN text input field, ie. only in the backend.
 */
add_filter('gwapi_recipient_field_hidden_html', function($html, WP_Post $recipient, $field) {
    if ($field['type'] == 'hidden' && !is_admin()) return $html;

    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);

    return '<input type="text" name="gatewayapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" />';
}, 1, 3);

/**
 * Render a list of mobile country codes field.
 */
add_filter('gwapi_recipient_field_mobile_cc_html', function($html, WP_Post $recipient, $field) {
    if ($field['type'] == 'hidden' && !is_admin()) return $html;
    $disabled = isset($field['disabled']) && $field['disabled'] ? ' disabled' : '';

    wp_enqueue_script('gwapi-widgets');
    wp_enqueue_style('select2-4');

    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = apply_filters('gwapi_recipient_value', get_post_meta($recipient->ID, strtolower($field_meta_key), true), $recipient, $field);

    $only_ccs = "";
    if (isset($field['mobile_cc_countries']) && $field['mobile_cc_countries']) {
        $ccs = explode("\n", $field['mobile_cc_countries']);
        foreach($ccs as &$cc) { $cc = (int)trim($cc); }
        $only_ccs = ' data-only-ccs="'.esc_attr(implode(',', $ccs)).'"';
    }
    $html = '<select'.$disabled.' name="gatewayapi['.strtolower($field['field_id']).']" data-gwapi-mobile-cc'.$only_ccs.'>';

    if ($recipient_value) {
        $html .= '<option selected value="'.esc_attr($recipient_value).'">'.esc_html($recipient_value).'</option>';
    }

    $html .= '</select>';
    return $html;
}, 1, 3);

add_filter('gwapi_recipient_value', function($value, $recipient, $field) {
    if ($field['field_id'] == 'NAME') {
        return get_the_title($recipient->ID);
    }
    return $value;
}, 10, 3);

function gatewayapi__render_recipient_editable_groups($atts, WP_Post $post = null) {

    $limit_groups = isset($atts['groups']) ? explode(",", $atts['groups']) : [];
    $groups_deselected = isset($atts["groups-deselected"]);

    if (!$limit_groups) {
        $limit_groups = get_terms([
            'hide_empty' => false,
            'taxonomy' => 'gwapi-recipient-groups',
            'fields' => 'ids'
        ]);
    }

    // render the groups as a checkbox widget
    $data = '';
    $groups = get_terms([
        'taxonomy' => 'gwapi-recipient-groups',
        'hide_empty' => false,
        'include' => $limit_groups
    ]);

    // current groups?
    $current_groups = [];
    if ($post) {
        $current_groups = wp_get_object_terms($post->ID, 'gwapi-recipient-groups', ['fields' => 'ids']);
    }

    $choices = [];
    $choices_keys = [];
    foreach($groups as $group) {
        $select_this = !$groups_deselected ? : in_array($group->term_id, $current_groups);
        $choices[] = ($select_this ? '!! ' : '').$group->name;
        $choices_keys[] = $group->term_id;
    }

    gatewayapi__render_recipient_field([
        'type' => 'checkbox',
        'name' => __('Groups', 'gatewayapi'),
        'description' => $atts['action'] == 'send_sms' ? __('Select the groups you would like to send to. If none is selected, the SMS will be sent to everyone.', 'gatewayapi') : __('Select the groups with has the content that you would like to receive.', 'gatewayapi'),
        'choices' => implode("\n", $choices),
        'choices_keys' => $choices_keys,
        'field_id' => '_gatewayapi_recipient_groups'
    ], $post?:new WP_Post(new stdClass()));
}
