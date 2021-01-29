<?php



// Hooking up our function to theme setup
/*
* Creating a function to create our CPT
*/

function gwapi_cpt_notification() {


// Set UI labels for Custom Post Type
    $labels = array(
      'name'                => _x( 'Notifications', 'Post Type General Name', 'gatewayapi' ),
      'singular_name'       => _x( 'Notification', 'Post Type Singular Name', 'gatewayapi' ),
      'menu_name'           => __( 'Notifications', 'gatewayapi' ),
      'parent_item_colon'   => __( 'Parent Notification', 'gatewayapi' ),
      'all_items'           => __( 'All Notifications', 'gatewayapi' ),
      'view_item'           => __( 'View Notification', 'gatewayapi' ),
      'add_new_item'        => __( 'Add New Notification', 'gatewayapi' ),
      'add_new'             => __( 'Add New', 'gatewayapi' ),
      'edit_item'           => __( 'Edit Notification', 'gatewayapi' ),
      'update_item'         => __( 'Update Notification', 'gatewayapi' ),
      'search_items'        => __( 'Search Notification', 'gatewayapi' ),
      'not_found'           => __( 'No Notifications found', 'gatewayapi' ),
      'not_found_in_trash'  => __( 'Not found in Trash', 'gatewayapi' ),
    );

// Set other options for Custom Post Type

    $args = array(
      'label'               => __( 'notification', 'gatewayapi' ),
      'description'         => __( 'SMS Notifications for actions', 'gatewayapi' ),
      'labels'              => $labels,
      // Features this CPT supports in Post Editor
      'supports'            => array( 'title'),
      // You can associate this CPT with a taxonomy or custom taxonomy.
      /* A hierarchical CPT is like Pages and can have
      * Parent and child items. A non-hierarchical CPT
      * is like Posts.
      */
      'hierarchical'        => false,
      'public'              => false,
      'show_ui'             => true,
      'show_in_menu'        => false,
      'show_in_nav_menus'   => true,
      'show_in_admin_bar'   => true,
      'menu_position'       => 10,
      'can_export'          => true,
      'has_archive'         => false,
      'exclude_from_search' => true,
      'publicly_queryable'  => false,
      'capability_type'     => 'post',
    );


    // Registering your Custom Post Type
    register_post_type( 'gwapi-notification', $args);

}

function gwapi_cpt_notification_admin_menu() {
    add_submenu_page('edit.php?post_type=gwapi-sms',
      __('Notifications', 'gatewayapi'),
      __('Notifications', 'gatewayapi'),
      'manage_options',
      'edit.php?post_type=gwapi-notification',
      '',
      5
    );
}

/* Hook into the 'init' action so that the function
* Containing our post type registration is not
* unnecessarily executed.
*/

add_action( 'init', 'gwapi_cpt_notification');
add_action('admin_menu', 'gwapi_cpt_notification_admin_menu');

// fields on the SMS editor page
add_action('admin_init', function () {
    add_meta_box('notification_meta_triggers',  __('Trigger', 'gatewayapi'), '_gwapi_notification_meta_triggers',   'gwapi-notification', 'normal', 'default');
    add_meta_box('notification_meta_groups',    __('Message', 'gatewayapi'), '_gwapi_notification_meta_groups',     'gwapi-notification', 'normal', 'default');
    add_meta_box('notification_meta_message',   __('Message', 'gatewayapi'), '_gwapi_notification_meta_message',    'gwapi-notification', 'normal', 'default');
});


add_action('admin_enqueue_scripts', 'gwapi_notification_enqueue_scripts');

function gwapi_notification_enqueue_scripts($hook) {


    wp_enqueue_script('gwapi-wp-notification', _gwapi_url() . '/dist/main.js');


}


/**
 * Build the administration fields for triggers
 */
function _gwapi_notification_meta_triggers(WP_Post $post)
{
    $triggers = _gwapi_get_triggers_grouped();
    _gwapi_render_template('notification/triggers');
}

/**
 * Build the administration fields recipients
 */
function _gwapi_notification_meta_groups(WP_Post $post)
{
    _gwapi_render_template('notification/groups', ['post' => $post]);
}

/**
 * Build the administration fields for the message
 */
function _gwapi_notification_meta_message(WP_Post $post)
{
    _gwapi_render_template('notification/message', ['post' => $post]);
}




// Same handler function...
add_action('wp_ajax_my_action', 'my_action');

function my_action() {


    global $wpdb;
    $test = 'my string is nice';
    $whatever = intval($_POST['whatever']);
    $whatever += 10;

    $args = array(
      'name' => 'gwapi-recipient',
    );

    $defaults = array(
      'numberposts'      => -1,
      'category'         => 0,
      'orderby'          => 'date',
      'order'            => 'DESC',
      'include'          => array(),
      'exclude'          => array(),
      'meta_key'         => 'number',
      'meta_value'       => '',
      'post_type'        => 'gwapi-recipient',
      'suppress_filters' => true,
    );



//    $args = array(
//      'post_type'  => 'gwapi-recipient',
//      'posts_per_page'   => -1,
//
//      "meta_query" => [
//        [
//          'key' => 'number',
//          'value' => '21908089',
//          'compare' => '!='
//
//        ]
//      ],
//
//    );
//    $query = new WP_Query( $args );

    $recipients = [];
    $posts = get_posts($defaults);

    foreach ($posts as $post) {
        $id = $post->ID;
        $cc = get_post_meta($id, 'cc', true);
        $number = get_post_meta($id, 'number', true);

        if (empty($number)) {
            continue;
        }


        $recipient = [
          'id' => $id,
          'name' => $post->post_title ?? $number,
          'cc' =>   $cc,
          'number' => $number,
        ];

        $recipients[] = $recipient;

    }


    echo json_encode($recipients);
    wp_die();
}


