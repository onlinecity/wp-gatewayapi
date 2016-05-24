<?php

add_action('init', function () {

    register_taxonomy('gwapi-recipient-groups', 'gwapi-recipient', [
        'label' => __('Recipient groups', 'gwapi'),
        'labels' => [
            'name' => __('Recipient groups', 'gwapi'),
            'singular_name' => __('Recipient group', 'gwapi'),
            'menu_name' => __('Groups', 'gwapi'),
            'all_items' => __('All recipient groups', 'gwapi'),
            'edit_item' => __('Edit group', 'gwapi'),
            'view_item' => __('View group', 'gwapi'),
            'update_item' => __('Update group', 'gwapi'),
            'add_new_item' => __('Add new recipient group', 'gwapi'),
            'new_item_name' => __('New group', 'gwapi'),
            'search_items' => __('Search groups', 'gwapi'),
            'popular_items' => __('Popular groups', 'gwapi'),
            'add_or_remove_items' => __('Add or remove groups', 'gwapi'),
            'choose_from_most_used' => __('Choose from most used groups', 'gwapi'),
            'not_found' => __('No groups found', 'gwapi')
        ],
        'public' => true,
        'show_ui' => true,
        'hierarchical' => true
    ]);

});