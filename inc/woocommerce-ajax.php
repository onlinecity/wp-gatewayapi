<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

/**
 * Get WooCommerce SMS list
 */
add_action('wp_ajax_gatewayapi_get_woo_smss', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    $args = [
        'post_type' => 'gwapi-woo',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
    ];

    if ($search) {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);
    $smss = [];

    foreach ($query->posts as $post) {
        $smss[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'enabled' => get_post_meta($post->ID, 'enabled', true) === '1',
            'sender' => get_post_meta($post->ID, 'sender', true) ?: '',
            'order_state' => get_post_meta($post->ID, 'order_state', true),
            'countries' => get_post_meta($post->ID, 'countries', true) ?: [],
            'message' => $post->post_content,
            'created' => $post->post_date,
        ];
    }

    wp_send_json_success([
        'smss' => $smss,
        'pagination' => [
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current' => $page
        ]
    ]);
});

/**
 * Get a single WooCommerce SMS
 */
add_action('wp_ajax_gatewayapi_get_woo_sms', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) wp_send_json_error(['message' => 'Invalid ID']);

    $post = get_post($id);
    if (!$post || $post->post_type !== 'gwapi-woo') {
        wp_send_json_error(['message' => 'WooCommerce SMS not found']);
    }

    wp_send_json_success([
        'id' => $post->ID,
        'title' => $post->post_title,
        'enabled' => get_post_meta($post->ID, 'enabled', true) === '1',
        'sender' => get_post_meta($post->ID, 'sender', true) ?: '',
        'order_state' => get_post_meta($post->ID, 'order_state', true),
        'phone_field' => get_post_meta($post->ID, 'phone_field', true) ?: 'billing_phone',
        'fixed_phone_numbers' => get_post_meta($post->ID, 'fixed_phone_numbers', true) ?: '',
        'countries' => get_post_meta($post->ID, 'countries', true) ?: [],
        'message' => $post->post_content,
        'created' => $post->post_date
    ]);
});

/**
 * Save WooCommerce SMS (create/edit)
 */
add_action('wp_ajax_gatewayapi_save_woo_sms', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $message = isset($_POST['message']) ? stripslashes($_POST['message']) : '';
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true' ? '1' : '0';
    $sender = isset($_POST['sender']) ? sanitize_text_field($_POST['sender']) : '';
    $order_state = isset($_POST['order_state']) ? sanitize_text_field($_POST['order_state']) : '';
    $phone_field = isset($_POST['phone_field']) ? sanitize_text_field($_POST['phone_field']) : 'billing_phone';
    $fixed_phone_numbers = isset($_POST['fixed_phone_numbers']) ? sanitize_textarea_field($_POST['fixed_phone_numbers']) : '';
    $countries = isset($_POST['countries']) ? (array)$_POST['countries'] : [];

    if (empty($title)) {
        wp_send_json_error(['message' => 'Title is required']);
    }

    $post_data = [
        'post_title' => $title,
        'post_content' => $message,
        'post_type' => 'gwapi-woo',
        'post_status' => 'publish',
    ];

    if ($id) {
        $post_data['ID'] = $id;
        $post_id = wp_update_post($post_data);
    } else {
        $post_id = wp_insert_post($post_data);
    }

    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => $post_id->get_error_message()]);
    }

    update_post_meta($post_id, 'enabled', $enabled);
    update_post_meta($post_id, 'sender', $sender);
    update_post_meta($post_id, 'order_state', $order_state);
    update_post_meta($post_id, 'phone_field', $phone_field);
    update_post_meta($post_id, 'fixed_phone_numbers', $fixed_phone_numbers);
    update_post_meta($post_id, 'countries', $countries);
    update_post_meta($post_id, 'encoding', gatewayapi_is_ucs2($message) ? 'UCS2' : 'UTF8');

    wp_send_json_success(['id' => $post_id]);
});

/**
 * Delete WooCommerce SMS
 */
add_action('wp_ajax_gatewayapi_delete_woo_sms', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) wp_send_json_error(['message' => 'Invalid ID']);

    $result = wp_delete_post($id, true);
    if ($result) {
        wp_send_json_success(['message' => 'Deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete']);
    }
});

/**
 * Toggle WooCommerce SMS
 */
add_action('wp_ajax_gatewayapi_toggle_woo_sms', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true' ? '1' : '0';

    if (!$id) wp_send_json_error(['message' => 'Invalid ID']);

    update_post_meta($id, 'enabled', $enabled);

    wp_send_json_success(['message' => 'Toggled successfully']);
});

/**
 * Get WooCommerce Order Statuses
 */
add_action('wp_ajax_gatewayapi_get_woo_statuses', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $statuses = [];
    if (function_exists('wc_get_order_statuses')) {
        foreach (wc_get_order_statuses() as $key => $label) {
            $statuses[] = [
                'key' => substr($key, 3), // remove 'wc-'
                'label' => $label
            ];
        }
    } else {
        // Fallback or generic statuses if WC is not active (though the menu is only shown if enabled)
        $statuses = [
            ['key' => 'pending', 'label' => 'Pending payment'],
            ['key' => 'processing', 'label' => 'Processing'],
            ['key' => 'on-hold', 'label' => 'On hold'],
            ['key' => 'completed', 'label' => 'Completed'],
            ['key' => 'cancelled', 'label' => 'Cancelled'],
            ['key' => 'refunded', 'label' => 'Refunded'],
            ['key' => 'failed', 'label' => 'Failed'],
        ];
    }

    wp_send_json_success($statuses);
});

/**
 * Get WooCommerce Countries
 */
add_action('wp_ajax_gatewayapi_get_woo_countries', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $countries = [];
    if (class_exists('WooCommerce')) {
        $wc_countries = new WC_Countries();
        foreach ($wc_countries->get_countries() as $code => $name) {
            $prefix = '';
            if (method_exists($wc_countries, 'get_country_calling_code')) {
                $prefix = $wc_countries->get_country_calling_code($code);
            }
            $countries[] = [
                'slug' => $code,
                'name' => $name,
                'prefix' => $prefix ? (strpos($prefix, '+') === 0 ? $prefix : '+' . $prefix) : ''
            ];
        }
    } else {
        // Fallback
        $countries = [
            ['slug' => 'DK', 'name' => 'Denmark', 'prefix' => '+45'],
            ['slug' => 'US', 'name' => 'United States', 'prefix' => '+1'],
            ['slug' => 'GB', 'name' => 'United Kingdom', 'prefix' => '+44'],
        ];
    }

    wp_send_json_success($countries);
});
