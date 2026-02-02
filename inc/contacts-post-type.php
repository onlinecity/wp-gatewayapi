<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

add_action('init', function () {
    // Register Taxonomy for Contact Tags
    register_taxonomy('gwapi-recipient-tag', 'gwapi-recipient', [
        'labels' => [
            'name' => 'Contact Tags',
            'singular_name' => 'Contact Tag',
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'hierarchical' => false,
    ]);

    // Register Taxonomy for Contact Countries
    register_taxonomy('gwapi-recipient-country', 'gwapi-recipient', [
        'labels' => [
            'name' => 'Contact Countries',
            'singular_name' => 'Contact Country',
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'hierarchical' => false,
    ]);

    // Register Post Type for Contacts
    register_post_type('gwapi-recipient', [
        'labels' => [
            'name' => 'Contacts',
            'singular_name' => 'Contact',
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'supports' => ['title', 'custom-fields'],
        'taxonomies' => ['gwapi-recipient-tag'],
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => true,
        'show_in_menu' => false,
        'delete_with_user' => false,
    ]);
});
