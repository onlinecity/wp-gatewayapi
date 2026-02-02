<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

add_action('init', function () {
    // Register Post Type for Campaigns
    register_post_type('gwapi-campaign', [
        'labels' => [
            'name' => 'Campaigns',
            'singular_name' => 'Campaign',
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
