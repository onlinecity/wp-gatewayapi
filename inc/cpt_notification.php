<?php

use OnlineCity\GatewayAPI\TriggerStore;

define('PLUGIN_DIRECTORY', _gwapi_dir());

require_once PLUGIN_DIRECTORY."/src/classes/Notification.php";
require_once PLUGIN_DIRECTORY."/src/classes/Trigger.php";
require_once PLUGIN_DIRECTORY."/src/classes/TriggerStore.php";

// Hooking up our function to theme setup
/*
* Creating a function to create our CPT
*/

function gwapi_cpt_notification() {

//    gwapi_callback_autocomplete_recipient();
// Set UI labels for Custom Post Type
    $labels = [
      'name'               => _x('Notifications', 'Post Type General Name', 'gatewayapi'),
      'singular_name'      => _x('Notification', 'Post Type Singular Name', 'gatewayapi'),
      'menu_name'          => __('Notifications', 'gatewayapi'),
      'parent_item_colon'  => __('Parent Notification', 'gatewayapi'),
      'all_items'          => __('All Notifications', 'gatewayapi'),
      'view_item'          => __('View Notification', 'gatewayapi'),
      'add_new_item'       => __('Add New Notification', 'gatewayapi'),
      'add_new'            => __('Add New', 'gatewayapi'),
      'edit_item'          => __('Edit Notification', 'gatewayapi'),
      'update_item'        => __('Update Notification', 'gatewayapi'),
      'search_items'       => __('Search Notification', 'gatewayapi'),
      'not_found'          => __('No Notifications found', 'gatewayapi'),
      'not_found_in_trash' => __('Not found in Trash', 'gatewayapi'),
    ];

// Set other options for Custom Post Type

    $args = [
      'label'               => __('notification', 'gatewayapi'),
      'description'         => __('SMS Notifications for actions', 'gatewayapi'),
      'labels'              => $labels,
      // Features this CPT supports in Post Editor
      'supports'            => ['title'],
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
    ];


    // Registering your Custom Post Type
    register_post_type('gwapi-notification', $args);
    TriggerStore::listen();
}

/**
 * Define which columns we'll need.
 */
add_filter('manage_gwapi-notification_posts_columns', function ($columns) {
    $date_text = $columns['date'];
    unset($columns['date']);

    return array_merge($columns, [
      'trigger' => __('Trigger', 'gatewayapi'),
      //      'recipients' => __('Recipients', 'gatewayapi'),
      //      'sender' => __('Sender', 'gatewayapi'),
      'message' => __('Message', 'gatewayapi'),
      'date'    => $date_text,
    ]);
});

/**
 * Print the content for our custom columns.
 */
add_action('manage_posts_custom_column', function ($column, $id) {
    if (get_post_type($id) !== 'gwapi-notification') {
        return;
    }
    switch ($column) {
        case 'trigger':
            echo esc_html(get_post_meta($id, 'triggers', true));
            break;
//        case 'recipients':
//            echo esc_html(get_post_meta($id, 'recipient_type', true));
//            break;
//        case 'sender':
//            echo esc_html(get_post_meta($id, 'sender', true));
            break;
        case 'message':
            $msg = get_post_meta($id, 'message', true) ?: '-';
            echo esc_html(mb_strlen($msg) > 50 ? mb_substr($msg, 0, 50).'...' : $msg);
            break;
    }
}, 10, 2);


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

add_action('init', 'gwapi_cpt_notification');
add_action('admin_menu', 'gwapi_cpt_notification_admin_menu');

// fields on the SMS editor page
add_action('admin_init', function () {
    add_meta_box('notification_meta_triggers', __('Triggers', 'gatewayapi'), '_gwapi_notification_meta_triggers', 'gwapi-notification', 'normal', 'default');
    add_meta_box('notification_meta_groups', __('Recipients', 'gatewayapi'), '_gwapi_notification_meta_groups', 'gwapi-notification', 'normal', 'default');
    add_meta_box('notification_meta_message', __('Message', 'gatewayapi'), '_gwapi_notification_meta_message', 'gwapi-notification', 'normal', 'default');
});

add_action('admin_enqueue_scripts', 'gwapi_notification_enqueue_scripts');

function gwapi_notification_enqueue_scripts($hook) {

    wp_enqueue_script('gwapi-wp-notification', _gwapi_url().'/dist/main.js');
    wp_enqueue_style('gwapi-wp-notification', _gwapi_url().'/css/gwapi-notification.css');

    $transient_name = 'gwapi_notification_posts';

    $cached_posts_titles = [];

    // check if cached post titles are available in the transient.
    $cached_posts = get_transient($transient_name);
    if ($cached_posts) {
        foreach ($cached_posts as $index => $post) {
            $cached_posts_titles[$index] = $post['title'];
        }
    }

    $params = [
      'cached_post_titles' => $cached_posts_titles,
    ];

//    wp_enqueue_script( 'nds_advanced_search', plugin_dir_url( __FILE__ ) . 'js/nds-advanced-search.js', array( 'jquery', 'jquery-ui-autocomplete' ), $this->version, true );
    wp_localize_script('gwapi_notification', 'params', $params);


}


/**
 * Build the administration fields for triggers
 */
function _gwapi_notification_meta_triggers(WP_Post $post) {
    $triggers = _gwapi_get_triggers_grouped();
    _gwapi_render_template('notification/triggers', ['post' => $post]);
}

/**
 * Build the administration fields recipients
 */
function _gwapi_notification_meta_groups(WP_Post $post) {
    _gwapi_render_template('notification/groups', ['post' => $post]);
}

/**
 * Build the administration fields for the message
 */
function _gwapi_notification_meta_message(WP_Post $post) {
    _gwapi_render_template('notification/message', ['post' => $post]);
}

/**
 * Save recipient meta data
 */
add_action('save_post_gwapi-notification', 'gwapi_save_notification');

/**
 * Save the contents of a recipients form onto the recipient behind the given ID. Takes data from $_POST['gatewayapi'] if
 * data is not specified.
 *
 * @param  int  $post_id
 * @param  \WP_Post  $post
 * @param  bool  $update
 */
function gwapi_save_notification(int $post_id, WP_Post $post = null, bool $update = false) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }


    $data = $_POST['gatewayapi'] ?? null;

    if (!$data) {
        return;
    }

    foreach ($data as $key => $value) {
        update_post_meta($post_id, $key, $value);
    }
}


// Same handler function...
add_action('wp_ajax_gwapi_callback_autocomplete_recipient', 'gwapi_callback_autocomplete_recipient');

function gwapi_callback_autocomplete_recipient() {
    $recipients = [];
    $search_term = $_POST['search'] ?? '';
    $transient_name = 'gwapi_notification_posts';

    // retrieve the post types to search from the plugin settings.
    $post_types = 'gwapi-recipient';

// check if cached posts are available.
    $cached_posts = get_transient($transient_name);
    if (false === $cached_posts) {
        // retrieve posts for the specified post types by running get_posts and cache the posts as well.
        $cached_posts = gwapi_cache_posts_in_post_types();
    }

// extract the cached post ids from the transient into an array.
    $cached_post_ids = array_column($cached_posts, 'id');

// run a new query against the search key and the cached post ids for the seleted post types.
    $args = [
      'post_type'           => $post_types,
      'posts_per_page'      => -1,
      'no_found_rows'       => true, // as we don't need pagination.
      //      'post__in'            => $cached_post_ids, // use post ids that were cached in the query earlier.
      'ignore_sticky_posts' => true,
      'meta_key'            => 'number',
      's'                   => $search_term
      //      's'                   => $search_term,  // the keyword/phrase to search.
      //      'sentence'            => true, // perform a phrase search.
    ];

//    $loop = new \WP_Query( $args );

    $posts = get_posts($args);

    foreach ($posts as $post) {
        $ID = $post->ID;

        if (empty($post->post_title)) {
            continue;
        }

        $recipient = [
          'id'     => $ID,
          'name'   => $post->post_title,
          'cc'     => get_post_meta($ID, 'cc', true),
          'number' => get_post_meta($ID, 'number', true),
        ];

        $recipients[] = $recipient;
    }

    echo json_encode($recipients);
    wp_die();
}


/**
 * Cache WordPress posts for post types that are specified in the
 * plugin setting to be included in the custom search.
 */
function gwapi_cache_posts_in_post_types() {
    $transient_name = 'gwapi_notification_posts';
    $transient_expiration = 'gwapi_notification_posts_expiration';

    // check the transient for existing cached data.
    $cached_posts = get_transient($transient_name);
    if (false === $cached_posts) {
        $args = [
          'post_type'           => 'gwapi-recipient',
          'post_status'         => 'publish',
          'posts_per_page'      => 50,
          'no_found_rows'       => true, // true by default.
          'suppress_filters'    => false, // true by default.
          'ignore_sticky_posts' => true, // true by default.
        ];

        // get_posts() to retrieve posts belonging to the required post types.
        $posts_in_required_post_types = get_posts($args);

        // Check if posts were found.
        if ($posts_in_required_post_types) {
            foreach ($posts_in_required_post_types as $key => $post) {

                // cache the post titles and post ids.
                $cached_post = [
                  'id'    => $post->ID,
                  'title' => esc_html($post->post_title),
                ];
                $cached_posts[] = $cached_post;
            }

            /**
             * Save the post data in a transient.
             * Cache only the post ids, titles instead of the entire WP Query object.
             */
            set_transient($transient_name, $cached_posts, 3600);
        }
    }
    return $cached_posts;
}

function gwapi_notification_get_notifications() {
    $args = [
      'post_type'           => 'gwapi-notification',
      'post_status'         => 'publish',
      'posts_per_page'      => -1,
      'no_found_rows'       => true, // true by default.
      'suppress_filters'    => true, // true by default.
      'ignore_sticky_posts' => true, // true by default.
    ];

    // get_posts() to retrieve posts belonging to the required post types.
    $posts = get_posts($args);

    return $posts;
}

function gwapi_notification_execute_trigger($post_ID, $post_after, $post_before, $trigger = null) {

    if ($post_after->post_type == 'gwapi-recipient' && $post_after->post_status == 'published') {
        $meta = get_post_meta($post_ID);


        // send the SMS now
        update_post_meta($post_ID, 'api_status', 'about_to_send');
        _gwapi_prepare_sms($post_ID);
    }
}

/**
 * Prepare the SMS to be sent and start sending.
 */
function _gwapi_notification_prepare_sms($ID) {
    if (wp_is_post_revision($ID)) {
        return;
    } // no reason to spend any more time on a revision

    if (get_post_meta($ID, 'api_status', true) !== 'about_to_send') {
        return;
    } // got here some wrong way
    update_post_meta($ID, 'api_status', 'sending');

    $sender = get_post_meta($ID, 'sender', true);
    $message = get_post_meta($ID, 'message', true);
    $destaddr = get_post_meta($ID, 'destaddr', true) ?: 'MOBILE';


    // don't send invalid sms
    if ($errors = _gwapi_validate_sms([
      'sender'   => $sender,
      'message'  => $message,
      'destaddr' => $destaddr,
    ])
    ) {
        update_post_meta($ID, 'api_status', 'bail');
        update_post_meta($ID, 'api_error', __('Validation of the SMS failed prior to sending with the following errors:', 'gatewayapi')."\n- ".implode("\n- ", $errors));
        return;
    }

    // missing secret etc.?
    if (!get_option('gwapi_key') || !get_option('gwapi_secret')) {
        update_post_meta($ID, 'api_status', 'bail');
        $no_api_error = strtr(__("You have not entered your OAuth key and secret yet. Go to :link to complete the setup.", 'gatewayapi'),
          [':link' => '<a href="options-general.php?page=gatewayapi">'.__('GatewayAPI Settings', 'gatewayapi').'</a>']);
        update_post_meta($ID, 'api_error', $no_api_error);
        return;
    }

    // Extract all tags
    $allTags = _gwapi_extract_tags_from_message($message);

    // Prepare the recipients
    $recipients = _gwapi_create_recipients_for_sms($ID, $allTags);

    if (!$recipients) {
        update_post_meta($ID, 'api_status', 'bail');
        update_post_meta($ID, 'api_error', 'No recipients added.');
        return;
    }

    // preflight
    $preflight = wp_remote_post(admin_url('admin-ajax.php'), [
      'body'    => [
        'action'  => 'gwapi_send_next_batch_preflight',
        'post_ID' => $ID,
      ],
      'timeout' => 5,
    ]);

    // yea, we can defer to "background-process"
    if (trim(wp_remote_retrieve_body($preflight)) === 'success') {
        wp_remote_post(admin_url('admin-ajax.php'), [
          'body'    => [
            'action'  => 'gwapi_send_next_batch',
            'post_ID' => $ID,
          ],
          'timeout' => 5,
        ]);
    } else { // we can't background it, so just do in same thread
        set_time_limit(-1);
        do_action('wp_ajax_nopriv_gwapi_send_next_batch', false);
    }
}
