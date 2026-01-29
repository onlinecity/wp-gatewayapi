<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

/**
 * Get campaigns list
 */
add_action('wp_ajax_gatewayapi_get_campaigns', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
    $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'any';

    $args = [
        'post_type' => 'gwapi-campaign',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => $status === 'trash' ? 'trash' : ['publish', 'private', 'draft', 'pending', 'future'],
    ];

    if ($search) {
        $args['s'] = $search;
    }

    if (in_array($orderby, ['title', 'date'])) {
        $args['orderby'] = $orderby;
    } else {
        $args['orderby'] = 'meta_value';
        $args['meta_key'] = $orderby;
    }
    $args['order'] = $order;

    if ($status && $status !== 'any' && $status !== 'trash') {
        $args['meta_query'][] = [
            'key' => 'status',
            'value' => $status,
            'compare' => '='
        ];
    }

    $query = new WP_Query($args);
    $campaigns = [];

    foreach ($query->posts as $post) {
        $campaigns[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'sender' => get_post_meta($post->ID, 'sender', true),
            'recipients_count' => (int)get_post_meta($post->ID, 'recipients_count', true),
            'campaign_tags' => get_post_meta($post->ID, 'campaign_tags', true) ?: [],
            'recipient_tags' => get_post_meta($post->ID, 'recipient_tags', true) ?: [],
            'recipient_tags_logic' => get_post_meta($post->ID, 'recipient_tags_logic', true) ?: 'any',
            'start_time' => get_post_meta($post->ID, 'start_time', true),
            'end_time' => get_post_meta($post->ID, 'end_time', true),
            'status' => get_post_meta($post->ID, 'status', true) ?: 'draft',
            'created' => $post->post_date,
            'is_trash' => $post->post_status === 'trash'
        ];
    }

    wp_send_json_success([
        'campaigns' => $campaigns,
        'pagination' => [
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current' => $page
        ]
    ]);
});

/**
 * Get a single campaign
 */
add_action('wp_ajax_gatewayapi_get_campaign', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) wp_send_json_error(['message' => 'Invalid ID']);

    $post = get_post($id);
    if (!$post || $post->post_type !== 'gwapi-campaign') {
        wp_send_json_error(['message' => 'Campaign not found']);
    }

    wp_send_json_success([
        'id' => $post->ID,
        'title' => $post->post_title,
        'sender' => get_post_meta($post->ID, 'sender', true),
        'message' => $post->post_content,
        'campaign_tags' => get_post_meta($post->ID, 'campaign_tags', true) ?: [],
        'recipient_tags' => get_post_meta($post->ID, 'recipient_tags', true) ?: [],
        'recipient_tags_logic' => get_post_meta($post->ID, 'recipient_tags_logic', true) ?: 'any',
        'start_time' => get_post_meta($post->ID, 'start_time', true),
        'status' => get_post_meta($post->ID, 'status', true) ?: 'draft',
        'created' => $post->post_date
    ]);
});

/**
 * Save campaign (create/edit)
 */
add_action('wp_ajax_gatewayapi_save_campaign', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $sender = isset($_POST['sender']) ? sanitize_text_field($_POST['sender']) : '';
    $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';
    $campaign_tags = isset($_POST['campaign_tags']) ? (array)$_POST['campaign_tags'] : [];
    $recipient_tags = isset($_POST['recipient_tags']) ? (array)$_POST['recipient_tags'] : [];
    $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'draft';

    if (empty($title)) {
        wp_send_json_error(['message' => 'Title is required']);
    }

    if (!empty($sender)) {
        $is_digits_only = preg_match('/^\d+$/', $sender);
        if ($is_digits_only) {
            if (strlen($sender) > 18) {
                wp_send_json_error(['message' => 'Sender cannot be more than 18 digits']);
            }
        } else {
            if (strlen($sender) > 11) {
                wp_send_json_error(['message' => 'Sender cannot be more than 11 characters when it contains non-digit characters']);
            }
        }
    }

    $post_data = [
        'post_title' => $title,
        'post_content' => $message,
        'post_type' => 'gwapi-campaign',
        'post_status' => 'publish'
    ];

	if ( $id ) {
		$existing_post = get_post( $id );
		if ( ! $existing_post || $existing_post->post_type !== 'gwapi-campaign' ) {
			wp_send_json_error( [ 'message' => 'Campaign not found' ] );
		}
		$post_data['ID'] = $id;
        $result = wp_update_post($post_data);
    } else {
        $result = wp_insert_post($post_data);
        $id = $result;
    }

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    update_post_meta($id, 'sender', $sender);
    update_post_meta($id, 'campaign_tags', $campaign_tags);
    update_post_meta($id, 'recipient_tags', $recipient_tags);
    update_post_meta($id, 'recipient_tags_logic', isset($_POST['recipient_tags_logic']) ? sanitize_text_field($_POST['recipient_tags_logic']) : 'any');
    update_post_meta($id, 'start_time', $start_time);
    update_post_meta($id, 'status', $status);

    // Calculate recipients count
    $recipients_count = 0;
    if (!empty($recipient_tags)) {
        $logic = isset($_POST['recipient_tags_logic']) ? sanitize_text_field($_POST['recipient_tags_logic']) : 'any';
        $recipients_count = (new WP_Query([
            'post_type' => 'gwapi-recipient',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [[
                'taxonomy' => 'gwapi-recipient-tag',
                'field' => 'slug',
                'terms' => $recipient_tags,
                'operator' => $logic === 'all' ? 'AND' : 'IN'
            ]]
        ]))->found_posts;
    }
    update_post_meta($id, 'recipients_count', $recipients_count);

    // Schedule campaign if status is scheduled or sending
    if (in_array($status, ['scheduled', 'sending'])) {
        // Clear any existing scheduled actions for this campaign to avoid duplicates if re-saved
        as_unschedule_all_actions('gatewayapi_schedule_campaign', [$id], 'gatewayapi');

        $schedule_time = time();
        if ($status === 'scheduled' && !empty($start_time)) {
            $schedule_time = strtotime($start_time);
            if ($schedule_time < time()) {
                $schedule_time = time();
            }
        }

        as_schedule_single_action($schedule_time, 'gatewayapi_schedule_campaign', [$id], 'gatewayapi');
    }

    wp_send_json_success(['id' => $id, 'message' => 'Campaign saved successfully']);
});

/**
 * Get all existing campaign tags
 */
add_action('wp_ajax_gatewayapi_get_campaign_tags', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    global $wpdb;
    $results = $wpdb->get_results("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'campaign_tags'");

    $tags_count = [];
    foreach ($results as $row) {
        $tags = maybe_unserialize($row->meta_value);
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (empty($tag)) continue;
                if (!isset($tags_count[$tag])) {
                    $tags_count[$tag] = 0;
                }
                $tags_count[$tag]++;
            }
        }
    }

    $formatted_tags = [];
    foreach ($tags_count as $name => $count) {
        $formatted_tags[] = [
            'name' => $name,
            'count' => $count
        ];
    }

    // Sort by count DESC
    usort($formatted_tags, function ($a, $b) {
        return $b['count'] - $a['count'];
    });

    wp_send_json_success($formatted_tags);
});

/**
 * Count recipients for given tags and logic
 */
add_action('wp_ajax_gatewayapi_count_recipients', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $recipient_tags = isset($_GET['recipient_tags']) ? (array)$_GET['recipient_tags'] : [];
    $logic = isset($_GET['recipient_tags_logic']) ? sanitize_text_field($_GET['recipient_tags_logic']) : 'any';

    $recipients_count = 0;
    if (!empty($recipient_tags)) {
        $recipients_count = (new WP_Query([
            'post_type' => 'gwapi-recipient',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [[
                'taxonomy' => 'gwapi-recipient-tag',
                'field' => 'slug',
                'terms' => $recipient_tags,
                'operator' => $logic === 'all' ? 'AND' : 'IN'
            ]]
        ]))->found_posts;
    }

    wp_send_json_success(['count' => $recipients_count]);
});

/**
 * Trash/Delete campaign
 */
add_action('wp_ajax_gatewayapi_delete_campaign', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

	$id    = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	$force = isset( $_POST['force'] ) && $_POST['force'] === 'true';

	if ( ! $id ) {
		wp_send_json_error( [ 'message' => 'Invalid ID' ] );
	}

	$post = get_post( $id );
	if ( ! $post || $post->post_type !== 'gwapi-campaign' ) {
		wp_send_json_error( [ 'message' => 'Campaign not found' ] );
	}

	if (!$force) $result = wp_trash_post($id);
	else wp_delete_post($id, true);

    if (!$result) {
        wp_send_json_error(['message' => 'Failed to delete campaign']);
    }

    wp_send_json_success(['message' => $force ? 'Campaign deleted permanently' : 'Campaign moved to trash']);
});

/**
 * Restore campaign from trash
 */
add_action('wp_ajax_gatewayapi_restore_campaign', function () {
    if (!current_user_can('gatewayapi_manage')) {
	    wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }

	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	if ( ! $id ) {
		wp_send_json_error( [ 'message' => 'Invalid ID' ] );
	}

	$post = get_post( $id );
	if ( ! $post || $post->post_type !== 'gwapi-campaign' ) {
		wp_send_json_error( [ 'message' => 'Campaign not found' ] );
	}

	$result = wp_untrash_post($id);

    if (!$result) {
        wp_send_json_error(['message' => 'Failed to restore campaign']);
    }

    wp_send_json_success(['message' => 'Campaign restored']);
});

/**
 * Get server time
 */
add_action('wp_ajax_gatewayapi_get_server_time', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    wp_send_json_success([
        'current_time' => wp_date('Y-m-d\TH:i'),
        'timezone' => wp_timezone_string()
    ]);
});

/**
 * Test SMS
 */
add_action('wp_ajax_gatewayapi_test_sms', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $recipient = isset($_POST['recipient']) ? sanitize_text_field($_POST['recipient']) : '';
    $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';
    $sender = isset($_POST['sender']) ? sanitize_text_field($_POST['sender']) : '';

    if (empty($recipient)) {
        wp_send_json_error(['message' => 'Recipient is required']);
    }
    if (empty($message)) {
        wp_send_json_error(['message' => 'Message is required']);
    }
    if (empty($sender)) {
        $sender = get_option('gwapi_default_sender') ?: 'SMS';
    }

    $is_digits_only = preg_match('/^\d+$/', $sender);
    if ($is_digits_only) {
        if (strlen($sender) > 18) {
            wp_send_json_error(['message' => 'Sender cannot be more than 18 digits']);
        }
    } else {
        if (strlen($sender) > 11) {
            wp_send_json_error(['message' => 'Sender cannot be more than 11 characters when it contains non-digit characters']);
        }
    }

    $result = gatewayapi_send_mobile_message($message, $recipient, $sender);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success(['message' => 'Test SMS sent successfully', 'data' => $result]);
});
