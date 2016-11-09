<?php

add_action('init', function () {
    $args = array(
        'labels' => array(
            'name' => __("Inbox", 'gwapi'),
            'singular_name' => __('Inbox', 'gwapi'),
            'menu_name' => __('Inbox', 'gwapi'),
        ),
        'hierarchical' => false,
        'supports' => false,
        'public' => false,
        'show_ui' => get_option('gwapi_enable_ui'),
        'show_in_menu' => 'edit.php?post_type=gwapi-sms',
        'menu_position' => 10,
        'show_in_nav_menus' => true,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => false,
        'map_meta_cap' => true,
        'capability_type' => 'post',
        'capabilities' => array('create_posts' => false)
    );
    register_post_type('gwapi-receive-sms', $args);
});


