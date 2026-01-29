<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

/**
 * Handle campaign scheduling logic
 */
add_action('gatewayapi_schedule_campaign', function ($campaign_id) {
    $campaign = get_post($campaign_id);
    if (!$campaign || $campaign->post_type !== 'gwapi-campaign') {
        return;
    }

    $recipient_tags = get_post_meta($campaign_id, 'recipient_tags', true) ?: [];
    $logic = get_post_meta($campaign_id, 'recipient_tags_logic', true) ?: 'any';

    if (empty($recipient_tags)) {
        update_post_meta($campaign_id, 'status', 'draft');
        return;
    }

    // Update status to sending if it was scheduled or sending
    update_post_meta($campaign_id, 'status', 'sending');
    update_post_meta($campaign_id, 'start_time', current_time('mysql'));

    // Fetch all recipient IDs
    $recipient_ids = (new WP_Query([
        'post_type' => 'gwapi-recipient',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'status',
                'value' => 'active',
                'compare' => '='
            ]
        ],
        'tax_query' => [[
            'taxonomy' => 'gwapi-recipient-tag',
            'field' => 'slug',
            'terms' => $recipient_tags,
            'operator' => $logic === 'all' ? 'AND' : 'IN'
        ]]
    ]))->posts;

    if (empty($recipient_ids)) {
        update_post_meta($campaign_id, 'status', 'sent');
        update_post_meta($campaign_id, 'end_time', current_time('mysql'));
        return;
    }

    // Sort recipients to ensure consistent batching if needed, though not strictly required
    sort($recipient_ids);

    $send_speed = (int)get_option('gwapi_default_send_speed', 100);
    if ($send_speed <= 0) $send_speed = 100;

    $batches = array_chunk($recipient_ids, $send_speed);
    $start_time = time();

    foreach ($batches as $index => $batch_recipients) {
        $scheduled_time = $start_time + ($index * 60);
        as_schedule_single_action($scheduled_time, 'gatewayapi_send_campaign_batch', [
            'campaign_id' => $campaign_id,
            'recipient_ids' => $batch_recipients,
            'is_last_batch' => ($index === count($batches) - 1)
        ], 'gatewayapi');
    }
});

/**
 * Handle sending a single batch of a campaign
 */
add_action('gatewayapi_send_campaign_batch', function ($campaign_id, $recipient_ids, $is_last_batch) {
    $campaign = get_post($campaign_id);
    if (!$campaign || $campaign->post_type !== 'gwapi-campaign') {
        return;
    }

    $message = $campaign->post_content;
    $sender = get_post_meta($campaign_id, 'sender', true);

    if (empty($sender)) {
        $sender = get_option('gwapi_default_sender') ?: 'SMS';
    }

    $messages_to_send = [];
    foreach ($recipient_ids as $recipient_id) {
        $msisdn = get_post_meta($recipient_id, 'msisdn', true);
        if (!$msisdn) continue;

        // Strip non-digits from MSISDN as required by gatewayapi_send_mobile_messages
        $msisdn = preg_replace('/\D/', '', $msisdn);
        if (!$msisdn) continue;

        $messages_to_send[] = [
            'message' => $message,
            'recipient' => $msisdn,
            'sender' => $sender
        ];
    }

    if (!empty($messages_to_send)) {
        $result = gatewayapi_send_mobile_messages($messages_to_send);
        if (is_wp_error($result)) {
            // Log error or handle it. For now, we continue but maybe we should retry?
            // Action Scheduler handles retries if we throw an exception or if it fails.
            // gatewayapi_send_mobile_messages returns WP_Error on tech fail or API fail.
            error_log('GatewayAPI Campaign Send Error: ' . $result->get_error_message());
        }
    }

    if ($is_last_batch) {
        update_post_meta($campaign_id, 'status', 'sent');
        update_post_meta($campaign_id, 'end_time', current_time('mysql'));
    }
}, 10, 3);
