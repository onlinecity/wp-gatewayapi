<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

/**
 * WooCommerce Order Status Change Hook
 */
add_action('woocommerce_order_status_changed', function ($order_id, $old_status, $new_status) {
    if (!class_exists('WooCommerce')) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    // Find all enabled templates for this status
    $args = [
        'post_type' => 'gwapi-woo',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'enabled',
                'value' => '1',
                'compare' => '='
            ],
            [
                'key' => 'order_state',
                'value' => $new_status,
                'compare' => '='
            ]
        ]
    ];

    $query = new WP_Query($args);
    if (!$query->have_posts()) return;

    foreach ($query->posts as $post) {
        $phone_field = get_post_meta($post->ID, 'phone_field', true) ?: 'billing_phone';
        $countries = get_post_meta($post->ID, 'countries', true) ?: [];

        $recipient_phones = [];
        $recipient_country = '';

        if ($phone_field === 'shipping_phone') {
            $recipient_phones[] = $order->get_shipping_phone();
            $recipient_country = $order->get_shipping_country();
        } elseif ($phone_field === 'fixed') {
            $fixed_phones = get_post_meta($post->ID, 'fixed_phone_numbers', true) ?: '';
            $recipient_phones = array_filter(array_map('trim', explode("\n", $fixed_phones)));
        } else {
            $recipient_phones[] = $order->get_billing_phone();
            $recipient_country = $order->get_billing_country();
        }

        if (empty($recipient_phones)) continue;

        // Check if country matches (if limited) - only for non-fixed numbers
        if ($phone_field !== 'fixed' && !empty($countries) && !in_array($recipient_country, $countries)) continue;

        // Prepare message with replacement tags
        $message = $post->post_content;
        $tags = [
            '%ORDER_ID%' => $order->get_id(),
            '%ORDER_NUMBER%' => $order->get_order_number(),
            '%ORDER_TOTAL%' => $order->get_total(),
            '%ORDER_STATUS%' => wc_get_order_status_name($new_status),
            '%BILLING_NAME%' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            '%BILLING_FIRST_NAME%' => $order->get_billing_first_name(),
            '%BILLING_LAST_NAME%' => $order->get_billing_last_name(),
            '%BILLING_ADDRESS%' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() . ', ' . $order->get_billing_city(),
            '%SHIPPING_NAME%' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            '%SHIPPING_ADDRESS%' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() . ', ' . $order->get_shipping_city(),
        ];

        $message = str_replace(array_keys($tags), array_values($tags), $message);
        
        $sender = get_post_meta($post->ID, 'sender', true);
        if (!$sender) {
            $sender = get_option('gwapi_default_sender', 'Info');
        }

        $phones_to_send = [];
        foreach ($recipient_phones as $recipient_phone) {
            // Numbers must start with +
            if (strpos($recipient_phone, '+') === 0) {
                $phones_to_send[] = $recipient_phone;
            }
        }

        if (empty($phones_to_send)) continue;

        // Send the SMS
        gatewayapi_send_sms($message, $phones_to_send, $sender);

        // Add order note
        $target_name = __('billing phone', 'gatewayapi');
        if ($phone_field === 'shipping_phone') {
            $target_name = __('shipping phone', 'gatewayapi');
        } elseif ($phone_field === 'fixed') {
            $target_name = __('fixed numbers', 'gatewayapi');
        }

        $note = sprintf(__("SMS sent to %s:\n---\n%s", 'gatewayapi'), $target_name, $message);
        $order->add_order_note($note);
    }
}, 10, 3);
