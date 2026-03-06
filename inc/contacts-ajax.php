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

        $meta_fields = get_option('gwapi_contact_fields', []);
        if (!is_array($meta_fields)) {
            $meta_fields = json_decode($meta_fields, true) ?: [];
        }
        
        $meta = [];
        foreach ($meta_fields as $field) {
            $meta[$field['meta_key']] = get_post_meta($post->ID, $field['meta_key'], true);
        }

        $contacts[] = [
            'id' => $post->ID,
            'name' => $post->post_title,
            'msisdn' => get_post_meta($post->ID, 'msisdn', true),
            'status' => get_post_meta($post->ID, 'status', true) ?: 'active',
            'tags' => $tags,
            'country' => $country,
            'meta' => $meta,
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
    $meta_fields = get_option('gwapi_contact_fields', []);
    if (!is_array($meta_fields)) {
        $meta_fields = json_decode($meta_fields, true) ?: [];
    }
    
    $meta = [];
    foreach ($meta_fields as $field) {
        $meta[$field['meta_key']] = get_post_meta($post->ID, $field['meta_key'], true);
    }

    wp_send_json_success([
        'id' => $post->ID,
        'name' => $post->post_title,
        'msisdn' => get_post_meta($post->ID, 'msisdn', true),
        'status' => get_post_meta($post->ID, 'status', true) ?: 'active',
        'tags' => $tags,
        'meta' => $meta,
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
    $meta = isset($_POST['meta']) ? (array)$_POST['meta'] : [];

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

    // Save meta fields
    $meta_fields = get_option('gwapi_contact_fields', []);
    if (!is_array($meta_fields)) {
        $meta_fields = json_decode($meta_fields, true) ?: [];
    }
    foreach ($meta_fields as $field) {
        $key = $field['meta_key'];
        if (isset($meta[$key])) {
            update_post_meta($id, $key, sanitize_text_field($meta[$key]));
        }
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
    $replace_existing = isset($_POST['replace_existing']) && $_POST['replace_existing'] === 'true';

    if (empty($contacts)) {
        wp_send_json_error(['message' => 'No contacts provided']);
    }

    if (count($contacts) > 100) {
        wp_send_json_error(['message' => 'Maximum 100 contacts allowed per call']);
    }

    $meta_fields_config = get_option('gwapi_contact_fields', []);
    if (!is_array($meta_fields_config)) {
        $meta_fields_config = json_decode($meta_fields_config, true) ?: [];
    }

    $reserved_import_keys = [
        'name',
        'msisdn',
        'status',
        'tags',
        'country',
        'country_code',
        'mobile country code',
        'mobile number',
        'mobile_country_code',
        'mobile_number',
        'meta',
        'meta_titles'
    ];

    $meta_fields_by_key = [];
    $meta_fields_by_title = [];
    foreach ($meta_fields_config as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $raw_meta_key = isset($field['meta_key']) ? $field['meta_key'] : '';
        $raw_title = isset($field['title']) ? $field['title'] : '';
        $meta_key = sanitize_title($raw_meta_key ?: $raw_title);
        $title = sanitize_text_field($raw_title);

        if (empty($meta_key)) {
            continue;
        }
        if (empty($title)) {
            $title = ucwords(str_replace('-', ' ', $meta_key));
        }

        $meta_fields_config[$index]['meta_key'] = $meta_key;
        $meta_fields_config[$index]['title'] = $title;
        $meta_fields_by_key[$meta_key] = $meta_fields_config[$index];
        $meta_fields_by_title[strtolower(trim($title))] = $meta_key;
    }

    $meta_fields_changed = false;
    $ensure_meta_field = function ($title, $preferred_key = '') use (&$meta_fields_config, &$meta_fields_by_key, &$meta_fields_by_title, &$meta_fields_changed, $reserved_import_keys) {
        $title = sanitize_text_field((string)$title);
        if (empty($title)) {
            return '';
        }

        $title_lookup = strtolower(trim($title));
        if (isset($meta_fields_by_title[$title_lookup])) {
            return $meta_fields_by_title[$title_lookup];
        }

        $base_meta_key = sanitize_title($preferred_key ?: $title);
        if (empty($base_meta_key)) {
            $base_meta_key = 'field';
        }

        $meta_key = $base_meta_key;
        $counter = 1;
        while (isset($meta_fields_by_key[$meta_key]) || in_array($meta_key, $reserved_import_keys, true)) {
            $meta_key = $base_meta_key . '-' . $counter;
            $counter++;
        }

        $new_field = [
            'title' => $title,
            'description' => '',
            'meta_key' => $meta_key
        ];

        $meta_fields_config[] = $new_field;
        $meta_fields_by_key[$meta_key] = $new_field;
        $meta_fields_by_title[$title_lookup] = $meta_key;
        $meta_fields_changed = true;

        return $meta_key;
    };

    $results = [];
    foreach ($contacts as $contact_data) {
        $name = isset($contact_data['name']) ? sanitize_text_field($contact_data['name']) : '-';
        $msisdn = isset($contact_data['msisdn']) ? sanitize_text_field($contact_data['msisdn']) : '';
        if (empty($msisdn)) {
            $mobile_country_code = '';
            if (isset($contact_data['mobile_country_code'])) {
                $mobile_country_code = sanitize_text_field($contact_data['mobile_country_code']);
            } else if (isset($contact_data['mobile country code'])) {
                $mobile_country_code = sanitize_text_field($contact_data['mobile country code']);
            }

            $mobile_number = '';
            if (isset($contact_data['mobile_number'])) {
                $mobile_number = sanitize_text_field($contact_data['mobile_number']);
            } else if (isset($contact_data['mobile number'])) {
                $mobile_number = sanitize_text_field($contact_data['mobile number']);
            }

            $msisdn = $mobile_country_code . $mobile_number;
        }
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

        $id = 0;
        if ($existing_contact) {
            if (!$replace_existing) {
                $results[] = ['success' => false, 'message' => 'A contact with this MSISDN already exists', 'msisdn' => $msisdn];
                continue;
            }
            $id = $existing_contact[0];
        }

        $post_data = [
            'post_title' => $name,
            'post_name' => $msisdn,
            'post_type' => 'gwapi-recipient',
            'post_status' => 'publish'
        ];

        if ($id) {
            $post_data['ID'] = $id;
            $result = wp_update_post($post_data);
        } else {
            $result = wp_insert_post($post_data);
            $id = $result;
        }

        if (is_wp_error($result)) {
            $results[] = ['success' => false, 'message' => $result->get_error_message(), 'msisdn' => $msisdn];
            continue;
        }

        update_post_meta($id, 'msisdn', $msisdn);
        update_post_meta($id, 'status', $status);
        if (!empty($tags)) {
            wp_set_post_terms($id, $tags, 'gwapi-recipient-tag');
        }

        $meta_values_to_save = [];

        // Legacy import format: field value stored directly as lower-cased header.
        foreach ($meta_fields_config as $field) {
            if (!isset($field['title']) || !isset($field['meta_key'])) {
                continue;
            }

            $title_key = strtolower(trim($field['title']));
            if (isset($contact_data[$title_key])) {
                $meta_values_to_save[$field['meta_key']] = sanitize_text_field($contact_data[$title_key]);
            }
        }

        // New import format: explicit meta/meta_titles payload.
        $import_meta = isset($contact_data['meta']) && is_array($contact_data['meta']) ? $contact_data['meta'] : [];
        $import_meta_titles = isset($contact_data['meta_titles']) && is_array($contact_data['meta_titles']) ? $contact_data['meta_titles'] : [];
        foreach ($import_meta as $raw_key => $raw_value) {
            if (is_array($raw_value)) {
                continue;
            }

            $raw_key = (string)$raw_key;
            $title = isset($import_meta_titles[$raw_key]) ? $import_meta_titles[$raw_key] : str_replace('-', ' ', sanitize_title($raw_key));
            $meta_key = $ensure_meta_field($title, $raw_key);
            if (empty($meta_key)) {
                continue;
            }
            $meta_values_to_save[$meta_key] = sanitize_text_field((string)$raw_value);
        }

        // Any unmatched top-level columns become contact meta fields automatically.
        foreach ($contact_data as $raw_key => $raw_value) {
            $raw_key = (string)$raw_key;
            $raw_key_lower = strtolower($raw_key);
            if (in_array($raw_key_lower, $reserved_import_keys, true)) {
                continue;
            }
            if (is_array($raw_value)) {
                continue;
            }

            $value = sanitize_text_field((string)$raw_value);
            if ($value === '') {
                continue;
            }

            $meta_key = $ensure_meta_field($raw_key);
            if (empty($meta_key)) {
                continue;
            }
            $meta_values_to_save[$meta_key] = $value;
        }

        foreach ($meta_values_to_save as $meta_key => $value) {
            update_post_meta($id, $meta_key, $value);
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

    if ($meta_fields_changed) {
        update_option('gwapi_contact_fields', $meta_fields_config);
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

    $meta_fields = get_option('gwapi_contact_fields', []);
    if (!is_array($meta_fields)) {
        $meta_fields = json_decode($meta_fields, true) ?: [];
    }

    foreach ($query->posts as $post) {
        $tags = wp_get_post_terms($post->ID, 'gwapi-recipient-tag', ['fields' => 'names']);
        $country_terms = wp_get_post_terms($post->ID, 'gwapi-recipient-country');
        $country_name = '';
        $country_code = '';
        if (!empty($country_terms) && !is_wp_error($country_terms)) {
            $country_name = $country_terms[0]->name;
            $country_code = $country_terms[0]->slug;
        }

        $contact = [
            'name' => $post->post_title,
            'msisdn' => get_post_meta($post->ID, 'msisdn', true),
            'status' => get_post_meta($post->ID, 'status', true) ?: 'active',
            'tags' => implode(',', $tags),
            'country_name' => $country_name,
            'country_code' => $country_code
        ];

        foreach ($meta_fields as $field) {
            $contact[$field['title']] = get_post_meta($post->ID, $field['meta_key'], true);
        }

        $contacts[] = $contact;
    }

    wp_send_json_success(['contacts' => $contacts]);
});
