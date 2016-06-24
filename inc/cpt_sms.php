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
    if (in_array('groups',$sources) || !$sources) {
        $groups = get_post_meta($ID, 'recipient_groups', true);

        $recipientsQ = [
            "post_type" => "gwapi-recipient",
            "fields" => "ids",
            "posts_per_page" => -1
        ];

        if (in_array('groups', $sources)) {
            $recipientsQ["tax_query"] = [
                [
                    'taxonomy' => 'gwapi-recipient-groups',
                    'field' => 'term_id',
                    'terms' => $groups
                ]
            ];
        }

        // fetch recipient ids and prepare for use in SQL
        $recipientIDs = implode(',', (new WP_Query($recipientsQ))->posts);

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

        // other tags to fetch from the database
        $tags_meta_keys = [];
        foreach($tags as $tag) {
            $meta_key = strtolower(trim($tag, '%'));
            if ($meta_key == 'name') continue; // special
            $tags_meta_keys[] = $meta_key;
        }

        if ($tags_meta_keys) {
            $meta_keys_safe = [];
            foreach($tags_meta_keys as $k) { $meta_keys_safe[] = $wpdb->_real_escape($k); }
            foreach($wpdb->get_results($q="SELECT post_ID, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_ID IN ($recipientIDs) AND meta_key IN ('".implode("','",$meta_keys_safe)."')") as $row) {
                $msisdn = $recipientsByID[$row->post_ID];
                $tag = '%'.strtoupper($row->meta_key).'%';
                $tag_def = gwapi_get_tag_specification($tag);
                $recipientsByNumber[$msisdn][$tag] = apply_filters('gwapi_format_tag_'.$tag_def['type'], $row->meta_value, $tag_def);
            }
        }
    }

    // manually added
    foreach(get_post_meta($ID, 'single_recipient') as $sr) {
        $msisdn = $sr['cc'].$sr['number'];
        if (isset($recipientsByNumber[$msisdn])) continue;

        $recipientsByNumber[$msisdn] = [];

        if (in_array('%NAME%', $tags)) {
            $recipientsByNumber[$msisdn]['%NAME%'] = $sr['name'];
        }
    }

    // ensure everybody has all tags, at least with just empty strings
    foreach($tags as $t) {
        foreach($recipientsByNumber as &$n) {
            if (!isset($n[$t])) {
                $n[$t] = '';
            }
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
    if (wp_is_post_revision($ID)) return; // no reason to spend any more time on a revision
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

/**
 * Checkbox-tag: Unserialize the raw database value and comma-separate the list.
 */
add_filter('gwapi_format_tag_checkbox', function($value, $def) {
    $value = unserialize($value);

    if (!$value) return __('None', 'gwapi');
    return implode(', ',$value);
}, 5, 2);