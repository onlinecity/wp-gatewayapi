<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

/**
 * Get contacts list
 */
add_action('wp_ajax_gatewayapi_get_contacts', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $search_by = isset($_GET['search_by']) ? sanitize_text_field($_GET['search_by']) : 'name';
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
    $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'any';
    $tag = isset($_GET['tag']) ? sanitize_text_field($_GET['tag']) : '';
    $country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';

    $args = [
        'post_type' => 'gwapi-recipient',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => $status === 'trash' ? 'trash' : ['publish', 'private', 'draft', 'pending', 'future'],
    ];

    if ($search) {
        if ($search_by === 'msisdn') {
            $args['meta_query'][] = [
                'key' => 'msisdn',
                'value' => $search,
                'compare' => 'LIKE'
            ];
        } else {
            $args['s'] = $search;
        }
    }

    if ($orderby === 'msisdn') {
        $args['meta_key'] = 'msisdn';
        $args['orderby'] = 'meta_value';
    } else if ($orderby === 'status') {
        $args['meta_key'] = 'status';
        $args['orderby'] = 'meta_value';
    } else if (in_array($orderby, ['name', 'title'])) {
        $args['orderby'] = 'title';
    } else {
        $args['orderby'] = 'date';
    }
    $args['order'] = $order;

    if ($status && $status !== 'any' && $status !== 'trash') {
        $args['meta_query'][] = [
            'key' => 'status',
            'value' => $status,
            'compare' => '='
        ];
    }

    if ($tag) {
        $args['tax_query'][] = [
            'taxonomy' => 'gwapi-recipient-tag',
            'field' => 'slug',
            'terms' => $tag
        ];
    }

    if ($country) {
        $args['tax_query'][] = [
            'taxonomy' => 'gwapi-recipient-country',
            'field' => 'slug',
            'terms' => $country
        ];
    }

    if (isset($args['tax_query']) && count($args['tax_query']) > 1) {
        $args['tax_query']['relation'] = 'AND';
    }

    $query = new WP_Query($args);
    $contacts = [];

    foreach ($query->posts as $post) {
        $tags = wp_get_post_terms($post->ID, 'gwapi-recipient-tag', ['fields' => 'names']);
        $country_terms = wp_get_post_terms($post->ID, 'gwapi-recipient-country');
        $country = null;
        if (!empty($country_terms) && !is_wp_error($country_terms)) {
            $country = [
                'name' => $country_terms[0]->name,
                'slug' => $country_terms[0]->slug
            ];
        }

        $contacts[] = [
            'id' => $post->ID,
            'name' => $post->post_title,
            'msisdn' => get_post_meta($post->ID, 'msisdn', true),
            'status' => get_post_meta($post->ID, 'status', true) ?: 'active',
            'tags' => $tags,
            'country' => $country,
            'created' => $post->post_date,
            'is_trash' => $post->post_status === 'trash'
        ];
    }

    wp_send_json_success([
        'contacts' => $contacts,
        'pagination' => [
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current' => $page
        ]
    ]);
});

/**
 * Get a single contact
 */
add_action('wp_ajax_gatewayapi_get_contact', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) wp_send_json_error(['message' => 'Invalid ID']);

    $post = get_post($id);
    if (!$post || $post->post_type !== 'gwapi-recipient') {
        wp_send_json_error(['message' => 'Contact not found']);
    }

    $tags = wp_get_post_terms($post->ID, 'gwapi-recipient-tag', ['fields' => 'names']);

    wp_send_json_success([
        'id' => $post->ID,
        'name' => $post->post_title,
        'msisdn' => get_post_meta($post->ID, 'msisdn', true),
        'status' => get_post_meta($post->ID, 'status', true) ?: 'active',
        'tags' => $tags,
        'created' => $post->post_date
    ]);
});

/**
 * Save contact (create/edit)
 */
add_action('wp_ajax_gatewayapi_save_contact', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $msisdn = isset($_POST['msisdn']) ? sanitize_text_field($_POST['msisdn']) : '';
    $msisdn = preg_replace('/\D/', '', $msisdn);
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
    $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';
    $tags = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

    if (empty($name) || empty($msisdn)) {
        wp_send_json_error(['message' => 'Name and msisdn are required']);
    }

    // Check for duplicate MSISDN (excluding trash)
    $existing_contact = get_posts([
        'post_type' => 'gwapi-recipient',
        'name' => $msisdn,
        'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
        'fields' => 'ids',
        'posts_per_page' => 1
    ]);

    if ($existing_contact && (! $id || $existing_contact[0] != $id)) {
        wp_send_json_error(['message' => 'A contact with this MSISDN already exists']);
    }

    $post_data = [
        'post_title' => $name,
        'post_name' => $msisdn,
        'post_type' => 'gwapi-recipient',
        'post_status' => 'publish'
    ];

	if ( $id ) {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'gwapi-recipient' ) {
			wp_send_json_error( [ 'message' => 'Invalid contact ID' ] );
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

    update_post_meta($id, 'msisdn', $msisdn);
    update_post_meta($id, 'status', $status);
	wp_set_post_terms( $id, $tags, 'gwapi-recipient-tag' );

	if ($country) {
        $term = get_term_by('slug', $country_code, 'gwapi-recipient-country');
        if (!$term) {
            $term_info = wp_insert_term($country, 'gwapi-recipient-country', [
                'slug' => $country_code
            ]);
        }
		wp_set_post_terms($id, $country_code, 'gwapi-recipient-country');
	}

	wp_send_json_success(['id' => $id, 'message' => 'Contact saved successfully']);
});

/**
 * Trash/Delete contact
 */
add_action('wp_ajax_gatewayapi_delete_contact', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $force = isset($_POST['force']) && $_POST['force'] === 'true';

    if (!$id) wp_send_json_error(['message' => 'Invalid ID']);

	$post = get_post( $id );
	if ( ! $post || $post->post_type !== 'gwapi-recipient' ) {
		wp_send_json_error( [ 'message' => 'Invalid contact ID' ] );
	}

	$result = $force ? wp_delete_post($id, true) : wp_trash_post($id);


    if (!$result) {
        wp_send_json_error(['message' => 'Failed to delete contact']);
    }

    wp_send_json_success(['message' => $force ? 'Contact deleted permanently' : 'Contact moved to trash']);
});

/**
 * Restore contact from trash
 */
add_action('wp_ajax_gatewayapi_restore_contact', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) wp_send_json_error(['message' => 'Invalid ID']);

	$post = get_post( $id );
	if ( ! $post || $post->post_type !== 'gwapi-recipient' ) {
		wp_send_json_error( [ 'message' => 'Invalid contact ID' ] );
	}

	$msisdn = get_post_meta( $id, 'msisdn', true );
	if ( $msisdn ) {
		$existing_contact = get_posts( [
			'post_type'      => 'gwapi-recipient',
			'name'           => $msisdn,
			'post_status'    => [ 'publish', 'private', 'draft', 'pending', 'future' ],
			'fields'         => 'ids',
			'posts_per_page' => 1
		] );

		if ( $existing_contact ) {
			wp_send_json_error( [ 'message' => 'A contact with this MSISDN already exists' ] );
		}
	}

    $result = wp_untrash_post($id);
	wp_publish_post($id);

	if ( ! $result ) {
		wp_send_json_error( [ 'message' => 'Failed to restore contact' ] );
	}

    wp_send_json_success(['message' => 'Contact restored']);
});

/**
 * Get all tags
 */
add_action('wp_ajax_gatewayapi_get_tags', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $terms = get_terms([
        'taxonomy' => 'gwapi-recipient-tag',
        'hide_empty' => true,
        'orderby' => 'count',
        'order' => 'DESC'
    ]);

    wp_send_json_success(array_map(function($term) {
        return [
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'count' => $term->count
        ];
    }, $terms));
});

/**
 * Get all countries
 */
add_action('wp_ajax_gatewayapi_get_countries', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $terms = get_terms([
        'taxonomy' => 'gwapi-recipient-country',
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC'
    ]);

    wp_send_json_success(array_map(function($term) {
        return [
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'count' => (int)$term->count
        ];
    }, $terms));
});

/**
 * Bulk save contacts
 */
add_action('wp_ajax_gatewayapi_bulk_save_contacts', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $contacts = isset($_POST['contacts']) ? (array)$_POST['contacts'] : [];
    if (empty($contacts)) {
        wp_send_json_error(['message' => 'No contacts provided']);
    }

    if (count($contacts) > 100) {
        wp_send_json_error(['message' => 'Maximum 100 contacts allowed per call']);
    }

    $results = [];
    foreach ($contacts as $contact_data) {
        $name = isset($contact_data['name']) ? sanitize_text_field($contact_data['name']) : '-';
        $msisdn = isset($contact_data['msisdn']) ? sanitize_text_field($contact_data['msisdn']) : '';
        $msisdn = preg_replace('/\D/', '', $msisdn);
        $status = isset($contact_data['status']) ? sanitize_text_field($contact_data['status']) : 'active';
        $country = isset($contact_data['country']) ? sanitize_text_field($contact_data['country']) : '';
        $country_code = isset($contact_data['country_code']) ? sanitize_text_field($contact_data['country_code']) : '';
        $tags = isset($contact_data['tags']) ? (array)$contact_data['tags'] : [];

        if (empty($msisdn)) {
            $results[] = ['success' => false, 'message' => 'MSISDN is required', 'msisdn' => $msisdn];
            continue;
        }

        // Check for duplicate MSISDN (excluding trash)
        $existing_contact = get_posts([
            'post_type' => 'gwapi-recipient',
            'name' => $msisdn,
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
            'fields' => 'ids',
            'posts_per_page' => 1
        ]);

        if ($existing_contact) {
            $results[] = ['success' => false, 'message' => 'A contact with this MSISDN already exists', 'msisdn' => $msisdn];
            continue;
        }

        $post_data = [
            'post_title' => $name,
            'post_name' => $msisdn,
            'post_type' => 'gwapi-recipient',
            'post_status' => 'publish'
        ];

        $id = wp_insert_post($post_data);

        if (is_wp_error($id)) {
            $results[] = ['success' => false, 'message' => $id->get_error_message(), 'msisdn' => $msisdn];
            continue;
        }

        update_post_meta($id, 'msisdn', $msisdn);
        update_post_meta($id, 'status', $status);
        if (!empty($tags)) {
            wp_set_post_terms($id, $tags, 'gwapi-recipient-tag');
        }

        if ($country) {
            $term = get_term_by('slug', $country_code, 'gwapi-recipient-country');
            if (!$term) {
                $term_info = wp_insert_term($country, 'gwapi-recipient-country', [
                    'slug' => $country_code
                ]);
            }
            wp_set_post_terms($id, $country_code, 'gwapi-recipient-country');
        }

        $results[] = ['success' => true, 'id' => $id, 'msisdn' => $msisdn];
    }

    wp_send_json_success(['results' => $results]);
});

/**
 * Get all contacts for export
 */
add_action('wp_ajax_gatewayapi_get_contacts_export', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $search_by = isset($_GET['search_by']) ? sanitize_text_field($_GET['search_by']) : 'name';
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
    $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'any';
    $tag = isset($_GET['tag']) ? sanitize_text_field($_GET['tag']) : '';
    $country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';

    $args = [
        'post_type' => 'gwapi-recipient',
        'posts_per_page' => -1,
        'post_status' => $status === 'trash' ? 'trash' : ['publish', 'private', 'draft', 'pending', 'future'],
    ];

    if ($search) {
        if ($search_by === 'msisdn') {
            $args['meta_query'][] = [
                'key' => 'msisdn',
                'value' => $search,
                'compare' => 'LIKE'
            ];
        } else {
            $args['s'] = $search;
        }
    }

    if ($orderby === 'msisdn') {
        $args['meta_key'] = 'msisdn';
        $args['orderby'] = 'meta_value';
    } else if ($orderby === 'status') {
        $args['meta_key'] = 'status';
        $args['orderby'] = 'meta_value';
    } else if (in_array($orderby, ['name', 'title'])) {
        $args['orderby'] = 'title';
    } else {
        $args['orderby'] = 'date';
    }
    $args['order'] = $order;

    if ($status && $status !== 'any' && $status !== 'trash') {
        $args['meta_query'][] = [
            'key' => 'status',
            'value' => $status,
            'compare' => '='
        ];
    }

    if ($tag) {
        $args['tax_query'][] = [
            'taxonomy' => 'gwapi-recipient-tag',
            'field' => 'slug',
            'terms' => $tag
        ];
    }

    if ($country) {
        $args['tax_query'][] = [
            'taxonomy' => 'gwapi-recipient-country',
            'field' => 'slug',
            'terms' => $country
        ];
    }

    if (isset($args['tax_query']) && count($args['tax_query']) > 1) {
        $args['tax_query']['relation'] = 'AND';
    }

    $query = new WP_Query($args);
    $contacts = [];

    foreach ($query->posts as $post) {
        $tags = wp_get_post_terms($post->ID, 'gwapi-recipient-tag', ['fields' => 'names']);
        $country_terms = wp_get_post_terms($post->ID, 'gwapi-recipient-country');
        $country_name = '';
        $country_code = '';
        if (!empty($country_terms) && !is_wp_error($country_terms)) {
            $country_name = $country_terms[0]->name;
            $country_code = $country_terms[0]->slug;
        }

        $contacts[] = [
            'name' => $post->post_title,
            'msisdn' => get_post_meta($post->ID, 'msisdn', true),
            'status' => get_post_meta($post->ID, 'status', true) ?: 'active',
            'tags' => implode(',', $tags),
            'country_name' => $country_name,
            'country_code' => $country_code
        ];
    }

    wp_send_json_success(['contacts' => $contacts]);
});
