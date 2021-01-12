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
    add_meta_box('notification', __('Trigger action', 'gatewayapi'), '_gwapi_notification', 'gwapi-notification', 'normal', 'default');
//    add_meta_box('custom_fields', __('Custom fields', 'gatewayapi'), '_gwapi_notification_fields', 'gwapi-notification', 'normal', 'default');
});

function gwapi_cpt_notification_add_custom_box() {
    $screens = [ 'post', 'gwapi-notification' ];
    foreach ( $screens as $screen ) {
        add_meta_box(
          'gwapi_notification_metabox',                 // Unique ID
          'Notification section',      // Box title
          'gwapi_notification_metabox_html',  // Content callback, must be of type callable
          $screen                            // Post type
        );
    }
}
add_action( 'add_meta_boxes', 'gwapi_cpt_notification_add_custom_box' );

function gwapi_notification_metabox_html() {
  ?>

    <p>Additional fields goes here...</p>
  <?php
}
/**
 * Build the administration fields for editing a single recipient.
 */
function _gwapi_notification(WP_Post $post)
{
    $triggers = _gwapi_get_triggers_grouped();

    ?>
    <div class="gwapi-star-errors"></div>
    <table width="100%" class="form-table">
        <tbody>
        <tr>
            <th width="25%">
                <?php _e('Trigger', 'gatewayapi') ?>
            </th>
            <td>
              <select id="select-trigger" class="trigger-default"  placeholder="Select trigger...">

                <option></option>
                <?php foreach ($triggers as $group => $subtriggers): ?>

                    <optgroup label="<?php echo esc_attr( $group ); ?>">

                        <?php foreach ( $subtriggers as $slug => $trigger ) : ?>


                          <option value="<?php echo esc_attr( $slug ); ?>"
                                  data-id="<?php echo $trigger->getId(); ?>"
                                  data-title="<?php echo $trigger->getName(); ?>"
                                  data-text="<?php echo $trigger->getDescription(); ?>"
                          >
                              <?php  echo esc_html( $trigger->getName() ); ?>
                              <div>
                                  <?php $description = $trigger->getDescription(); ?>
                                  <?php if ( ! empty( $description ) ) : ?>
                                    ||<?php echo esc_html( $description ); ?>
                                  <?php endif ?>
                              </div>

                          </option>

                        <?php endforeach; ?>

                    </optgroup>

                  <?php endforeach; ?>


              </select>
            </td>
        </tr>
        </tbody>
    </table>
    <?php
}


//function _gwapi_notification_triggers() {
//    // Get hooks as JSON:
//    $actions_file = _gwapi_dir() . '/vendor/johnbillion/wp-hooks/hooks/actions.json';
//    $filters_file = _gwapi_dir() . '/vendor/johnbillion/wp-hooks/hooks/filters.json';
//
//    $actions_json = file_get_contents( $actions_file );
//    $filters_json = file_get_contents( $filters_file );
//
//// Get hooks as PHP:
//    $actions = json_decode( $actions_json, true )['hooks'];
//    $filters = json_decode( $filters_json, true )['hooks'];
//
//    // Search for filters matching a string:
//    $search = 'page';
//    $results = array_filter( $filters, function( array $hook ) use ( $search ) {
//        return ( false !== strpos( $hook['name'], $search ) );
//    } );
//
////    var_dump( $results );
////    die();
//}



///**
// * Gets all registered triggers in a grouped array
// *
// * @since  5.0.0
// * @return array grouped triggers
// */
//function _gwapi_get_triggers_grouped() {
//
//    $return = array();
//
//    foreach ( _gwapi_get_triggers() as $trigger ) {
//
//        if ( ! isset( $return[ $trigger->get_group() ] ) ) {
//            $return[ $trigger->get_group() ] = array();
//        }
//
//        $return[ $trigger->get_group() ][ $trigger->get_slug() ] = $trigger;
//
//    }
//
//    return $return;
//
//}
//
