<?php

// post type SMS'es
add_action('init', function () {

    $labels = array(
        'name' => __('SMS\'es', 'gwapi'),
        'singular_name' => __('SMS', 'gwapi'),
        'add_new' => __('Create SMS', 'gwapi'),
        'add_new_item' => __('Create new SMS', 'gwapi'),
        'edit_item' => __('Edit SMS', 'gwapi'),
        'new_item' => __('New SMS', 'gwapi'),
        'search_items' => __('Search SMS\'es', 'gwapi'),
        'not_found' => __('No SMS\'es found', 'gwapi'),
        'not_found_in_trash' => __('No SMS\'es found in trash', 'gwapi'),
        'menu_name' => __('SMS\'es', 'gwapi'),
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'supports' => false,
        'public' => false,
        'show_ui' => get_option('gwapi_enable_ui'),
        'show_in_menu' => true,
        'menu_position' => 10,
        'menu_icon' => 'dashicons-format-chat',
        'show_in_nav_menus' => true,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => false,
        'capability_type' => 'post'
    );

    register_post_type('gwapi-sms', $args);
});

/**
 * Send SMS when the post type is published.
 */
add_action('publish_gwapi-sms', function($ID) {
    // already sent or anything else API-related?
    if (get_post_meta($ID, 'api_status', true)) return;

    // send the SMS now
    update_post_meta($ID, 'api_status', 'about_to_send');
    do_action('gwapi_send_sms', $ID);
});

/**
 * Create recipients for the SMS.
 * Extract all the recipients from the database and inserts them into the gwapi_sms_recipients-table
 *
 * @internal
 */
function _gwapi_create_recipients_for_sms($ID, $tags)
{
    $sources = get_post_meta($ID, 'recipients', true);

    $recipientsByNumber = [];
    $recipientsByID = [];

    // by recipient group
    if (in_array('groups',$sources)) {
        $groups = get_post_meta($ID, 'recipient_groups', true);

        // fetch recipient ids and prepare for use in SQL
        $recipientIDs = implode(',', (new WP_Query($q=[
            "post_type" => "gwapi-recipient",
            "fields" => "ids",
            "tax_query" => [
                [
                    'taxonomy' => 'gwapi-recipient-groups',
                    'field' => 'term_id',
                    'terms' => $groups
                ]
            ],
            "posts_per_page" => -1
        ]))->posts);

        // fetch the phone numbers
        global $wpdb; /** @var $wpdb wpdb  */
        $tmp = [];
        foreach($wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($recipientIDs) AND meta_key IN ('number', 'cc');") as $row) {
            $tmp[$row->post_id][$row->meta_key] = $row->meta_value;
        };
        foreach($tmp as $recID => $row) {
            if (!$row['cc'] || !$row['number']) continue;
            $msisdn = $row['cc'].$row['number'];
            $recipientsByNumber[$msisdn] = [];
            $recipientsByID[$recID] = $msisdn;
        }

        if (in_array('%NAME%', $tags)) {
            // fetch the names
            foreach($wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($recipientIDs)") as $row) {
                $msisdn = $recipientsByID[$row->ID];
                $recipientsByNumber[$msisdn]['%NAME%'] = $row->post_title;
            }
        }
    }

    // manually added
    foreach(get_post_meta($ID, 'single_recipient') as $sr) {
        $msisdn = $sr['cc'].$sr['number'];
        if (isset($recipientsByNumber[$msisdn])) continue;

        $recipientsByNumber[$msisdn] = [];

        if (in_array('%NAME%', $tags)) {
            $recipientsByNumber[$msisdn]['%NAME%'] = $sr->name;
        }
    }

    // save amount of recipients on the post
    update_post_meta($ID, 'recipients_count', count($recipientsByNumber));

    return $recipientsByNumber;
}

/**
 * Do the actual sending.
 *
 * @internal This hook is NOT protected against multiple calls and should NOT be called directly.
 */
add_action('gwapi_send_sms', function($ID) {
    if (get_post_meta($ID, 'api_status', true) != 'about_to_send') return; // got here some wrong way
    update_post_meta($ID, 'api_status', 'sending');

    $sender = get_post_meta($ID, 'sender', true);
    $message = get_post_meta($ID, 'message', true);
    $destaddr = get_post_meta($ID, 'destaddr', true) ? : 'MOBILE';

    // don't send invalid sms
    if ($errors = _gwapi_validate_sms([
        'sender' => $sender,
        'message' => $message,
        'destaddr' => $destaddr
    ])) {
        update_post_meta($ID, 'api_status', 'bail');
        update_post_meta($ID, 'api_error', __('Validation of the SMS failed prior to sending with the following errors:', 'gwapi')."\n- ".implode("\n- ",$errors));
        return;
    }

    // missing secret etc.?
    if (!get_option('gwapi_key') || !get_option('gwapi_secret')) {
        update_post_meta($ID, 'api_status', 'bail');
        $no_api_error = strtr(__("You have not entered your OAuth key and secret yet. Go to :link to complete the setup.", 'gwapi'), [ ':link' => '<a href="options-general.php?page=gatewayapi">'.__('GatewayAPI Settings', 'gwapi').'</a>' ]);
        update_post_meta($ID, 'api_error', $no_api_error);
        return;
    }

    // Extract all tags
    $allTags = _gwapi_extract_tags_from_message($message);

    // Prepare the recipients
    $recipients = _gwapi_create_recipients_for_sms($ID, $allTags);

    if (!$recipients) {
        update_post_meta($ID, 'api_status', 'bail');
        update_post_meta($ID, 'api_error', 'No recipients added.');
        return;
    }

    $send_req = gwapi_send_sms($message, $recipients, $sender, $destaddr);

    if (!is_wp_error($send_req)) {
        update_post_meta($ID, 'api_status', 'is_sent');
        update_post_meta($ID, 'api_ids', $send_req);
    } else {
        /** @var $send_req WP_Error */
        update_post_meta($ID, 'api_status', 'tech_error');
        update_post_meta($ID, 'api_error', json_encode($send_req->get_error_message()));
    }
});