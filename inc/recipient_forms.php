<?php

function _gwapi_all_recipient_fields () {
    $default_fields = [
        [
            'is_builtin' => true,
            'name' => 'Name',
            'field_id' => 'NAME',
            'description' => 'Full name of recipient',
            'required' => true,
            'type' => 'text'
        ], [
            'is_builtin' => true,
            'name' => 'Mobile country code',
            'field_id' => 'CC',
            'description' => 'Mobile country code of recipient',
            'required' => true,
            'type' => 'mobile_cc'
        ], [
            'is_builtin' => true,
            'name' => 'Mobile number',
            'field_id' => 'NUMBER',
            'description' => 'Mobile number of recipient',
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


function _gwapi_all_recipient_field_types($grouped = false)
{
    $types = [
        __('Text', 'gwapi') => [
            'text' => __('Plain text', 'gwapi'),
            'email' => __('E-mail', 'gwapi'),
            'url' => __('URL', 'gwapi'),
            'digits' => __('Digits only', 'gwapi'),
            'textarea' => __('Textarea', 'gwapi'),
        ],
        __('Password','gwapi') => [
            'password_plain' => __('Insecure (visible in backend)', 'gwapi'),
            'password_secure' => __('Secure (safely hashed)', 'gwapi')
        ],
        __('Multiple choices', 'gwapi') => [
            'select' => __('Select (single)','gwapi'),
            'radio' => __('Radio (single)','gwapi'),
            'checkbox' => __('Checkbox (multiple)','gwapi')
        ],
        __('Special', 'gwapi') => [
            'mobile_cc' => __('Mobile country code','gwapi'),
            'hidden' => __('Hidden (only visible in backend)','gwapi')
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
add_filter('pre_update_option_gwapi_recipient_fields', function($new_value) {
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

function gwapi_render_recipient_field($row, WP_Post $recipient)
{
    $render_hidden = apply_filters('gwapi_recipient_render_hidden', is_admin());
    $style = apply_filters('gwapi_recipient_field_style', is_admin() ? 'table' : 'div');

    $base_html = apply_filters('gwapi_recipient_fields_base_'.$style.'_html', '', $style);
    $base_html = apply_filters('gwapi_recipient_fields_base_html', $base_html, $style);

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
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);
    $required = isset($field['required']) && $field['required'];

    return '<input type="text" name="gwapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render an e-mail input field
 */
add_filter('gwapi_recipient_field_email_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);
    $required = isset($field['required']) && $field['required'];

    return '<input type="email" name="gwapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render a URL input field
 */
add_filter('gwapi_recipient_field_url_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);
    $required = isset($field['required']) && $field['required'];

    return '<input type="url" name="gwapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render a number-only input field
 */
add_filter('gwapi_recipient_field_digits_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);
    $required = isset($field['required']) && $field['required'];

    return '<input type="number" step="1" min="0" name="gwapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render a textarea input field
 */
add_filter('gwapi_recipient_field_textarea_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);
    $required = isset($field['required']) && $field['required'];

    return '<textarea rows="5" cols="30" name="gwapi['.strtolower($field['field_id']).']" '.($required?'required':'').'>'.esc_html($recipient_value).'</textarea>';
}, 1, 3);

/**
 * Render an insecure password field
 */
add_filter('gwapi_recipient_field_password_plain_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);
    $required = isset($field['required']) && $field['required'];

    return '<input type="'.(is_admin() ? 'text' : 'password').'" name="gwapi['.strtolower($field['field_id']).']" value="'.esc_attr(is_admin() ? $recipient_value : '').'" placeholder="'.(!is_admin() && $recipient_value ? 'Enter a value to update' : '').'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render a secure password field
 */
add_filter('gwapi_recipient_field_password_secure_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);
    $required = isset($field['required']) && $field['required'];

    return '<input type="password" name="gwapi['.strtolower($field['field_id']).']" placeholder="'.($recipient_value ? esc_attr(__('Enter a new value to update','gwapi')) : '').'" '.($required?'required':'').' />';
}, 1, 3);

/**
 * Render a regular select-field.
 */
add_filter('gwapi_recipient_field_select_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);
    $required = isset($field['required']) && $field['required'];

    $html = '<select name="gwapi['.$field_meta_key.']">';
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
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);

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

        $html .= '<div><label><input required type="radio" name="gwapi['.$field_meta_key.']" value="'.esc_attr($choice).'" '.($is_selected?'checked':'').'>'.esc_html($choice).'</label></div>';
    }

    return $html;
}, 1, 3);

/**
 * Render a group of checkbox fields.
 */
add_filter('gwapi_recipient_field_checkbox_html', function($html, WP_Post $recipient, $field) {
    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);
    $required = isset($field['required']) && $field['required'];

    $choices = explode("\n", $field['choices']);
    foreach($choices as $choice) {
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

        $html .= '<div><label><input '.($required?'required':'').' type="checkbox" name="gwapi['.$field_meta_key.'][]" value="'.esc_attr($choice).'" '.($is_selected?'checked':'').'>'.esc_html($choice).'</label></div>';
    }

    return $html;
}, 1, 3);

/**
 * Render a HIDDEN text input field, ie. only in the backend.
 */
add_filter('gwapi_recipient_field_hidden_html', function($html, WP_Post $recipient, $field) {
    if ($field['type'] == 'hidden' && !is_admin()) return $html;

    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);

    return '<input type="text" name="gwapi['.strtolower($field['field_id']).']" value="'.esc_attr($recipient_value).'" />';
}, 1, 3);

/**
 * Render a list of mobile country codes field.
 */
add_filter('gwapi_recipient_field_mobile_cc_html', function($html, WP_Post $recipient, $field) {
    if ($field['type'] == 'hidden' && !is_admin()) return $html;

    $field_meta_key = strtolower($field['field_id']);
    $recipient_value = get_post_meta($recipient->ID, strtolower($field_meta_key), true);

    $only_ccs = "";
    if (isset($field['mobile_cc_countries']) && $field['mobile_cc_countries']) {
        $ccs = explode("\n", $field['mobile_cc_countries']);
        foreach($ccs as &$cc) { $cc = (int)trim($cc); }
        $only_ccs = ' data-only-ccs="'.esc_attr(implode(',', $ccs)).'"';
    }
    $html = '<select name="gwapi['.strtolower($field['field_id']).']" data-gwapi-mobile-cc'.$only_ccs.'>';

    if ($recipient_value) {
        $html .= '<option selected value="'.esc_attr($recipient_value).'">'.esc_html($recipient_value).'</option>';
    }

    $html .= '</select>';
    return $html;
}, 1, 3);
