<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

add_action('init', function () {
    // Register Post Type for WooCommerce Order SMS Templates
    register_post_type('gwapi-woo', [
        'labels' => [
            'name' => 'WooCommerce SMS',
            'singular_name' => 'WooCommerce SMS',
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'supports' => ['title', 'custom-fields'],
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => true,
        'show_in_menu' => false,
        'delete_with_user' => false,
        'capability_type' => 'post',
    ]);
});
