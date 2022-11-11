<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php
/**
 * Custom post type for address book / SMS recipients.
 */
add_action('init', function () {
    if (!get_option('gwapi_enable_ui')) return;

    $labels = array(
        'name' => __('Recipients', 'gatewayapi'),
        'singular_name' => __('Recipient', 'gatewayapi'),
        'add_new' => __('Create recipient', 'gatewayapi'),
        'add_new_item' => __('Create new recipient', 'gatewayapi'),
        'edit_item' => __('Edit recipient', 'gatewayapi'),
        'new_item' => __('New recipient', 'gatewayapi'),
        'search_items' => __('Search recipients', 'gatewayapi'),
        'not_found' => __('No recipients found', 'gatewayapi'),
        'not_found_in_trash' => __('No recipients found in trash', 'gatewayapi'),
        'menu_name' => __('Recipients', 'gatewayapi'),
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

        if (!isset($submenu['edit.php?post_type=gwapi-recipient'])) return;

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
        return __('Name of recipient', 'gatewayapi');
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

    add_action( 'delete_post', 'gatewayapi__recipient_sync_delete', 10 );
});

function gatewayapi__recipient_sync_delete($post_id) {
    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix . 'oc_recipients_import', array( 'post_id' => $post_id ));

    if ( ! $result ) {
        return false;
    }
}


// allow blacklisting of phone numbers
bit_register_cpt_status('block', 'gwapi-recipient', 'Blacklistet', false);
