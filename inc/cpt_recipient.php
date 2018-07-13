<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php
/**
 * Custom post type for address book / SMS recipients.
 */
add_action('init', function () {
    if (!get_option('gwapi_enable_ui')) return;

    $labels = array(
        'name' => __('Recipients', 'gwapi'),
        'singular_name' => __('Recipient', 'gwapi'),
        'add_new' => __('Create recipient', 'gwapi'),
        'add_new_item' => __('Create new recipient', 'gwapi'),
        'edit_item' => __('Edit recipient', 'gwapi'),
        'new_item' => __('New recipient', 'gwapi'),
        'search_items' => __('Search recipients', 'gwapi'),
        'not_found' => __('No recipients found', 'gwapi'),
        'not_found_in_trash' => __('No recipients found in trash', 'gwapi'),
        'menu_name' => __('Recipients', 'gwapi'),
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'supports' => ['title'],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 10,
        'show_in_nav_menus' => true,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => false,
        'capability_type' => 'post'
    );

    register_post_type('gwapi-recipient', $args);

    /**
     * Move the recipients into the same submenu as the SMS post type
     */
    add_action( 'admin_menu', function() {
        global $menu;
        global $submenu;

        $target = &$submenu['edit.php?post_type=gwapi-sms'];
        foreach($submenu['edit.php?post_type=gwapi-recipient'] as $idx => $r) {
            $target[$idx+14] = $r;
        }

        // remove original menu
        foreach($menu as $idx=>$val) {
            if ($val[2] == 'edit.php?post_type=gwapi-recipient') unset($menu[$idx]);
        }

        // and submenu
        unset($submenu['edit.php?post_type=gwapi-recipient']);
    });

    /**
     * I18N
     */
    add_filter('enter_title_here', function($title, $post) {
        if (get_post_type($post) !== 'gwapi-recipient') return $title;
        return __('Name of recipient', 'gwapi');
    }, 10, 2);

    /**
     * Searchable phone number.
     */
    add_action('current_screen', function ($current_screen) {
        if ($current_screen->post_type === 'gwapi-recipient') {

            // add support for searching meta data
            bit_admin_add_search_column('gwapi-recipient', 'number');
        }
    });
});

// allow blacklisting of phone numbers
bit_register_cpt_status('block', 'gwapi-recipient', 'Blacklistet', false);