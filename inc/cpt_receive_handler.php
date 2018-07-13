<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php
/**
 * Custom post type for address book / SMS recipients.
 */
add_action('init', function () {
    if (!get_option('gwapi_enable_ui')) return;

    $labels = array(
        'name' => __('Receive actions', 'gwapi'),
        'singular_name' => __('Receive action', 'gwapi'),
        'add_new' => __('Create receive action', 'gwapi'),
        'add_new_item' => __('Create new receive action', 'gwapi'),
        'edit_item' => __('Edit receive action', 'gwapi'),
        'new_item' => __('New receive action', 'gwapi'),
        'search_items' => __('Search receive actions', 'gwapi'),
        'not_found' => __('No receive actions found', 'gwapi'),
        'not_found_in_trash' => __('No receive actions found in trash', 'gwapi'),
        'menu_name' => __('Receive actions', 'gwapi'),
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'supports' => ['title', 'page-attributes'],
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

    register_post_type('gwapi-receive-action', $args);

    /**
     * Move the recipients into the same submenu as the SMS post type
     */
    add_action('admin_menu', function () {
        global $menu;
        global $submenu;

        $target = &$submenu['edit.php?post_type=gwapi-sms'];
        foreach ($submenu['edit.php?post_type=gwapi-receive-action'] as $idx => $r) {
            $target[$idx + 15] = $r;
        }

        // remove original menu
        foreach ($menu as $idx => $val) {
            if ($val[2] == 'edit.php?post_type=gwapi-receive-action') unset($menu[$idx]);
        }

        // and submenu
        unset($submenu['edit.php?post_type=gwapi-receive-action']);
    });

    /**
     * I18N
     */
    add_filter('enter_title_here', function ($title, $post) {
        if (get_post_type($post) !== 'gwapi-receive-action') return $title;
        return __('Name of receive action', 'gwapi');
    }, 10, 2);
});

add_action('add_meta_boxes_gwapi-receive-action', function ($post) {
    add_meta_box('receive-action', __('Receive action', 'gwapi'), '_gwapi_receive_action_ui', 'gwapi-receive-action', 'normal', 'default');

    if ($action = get_post_meta($post->ID, 'action', true)) {
        $actions = apply_filters('gwapi_receive_actions', []);
        add_meta_box('receive-action-'.$action, $actions[$action], function() use ($post, $action) {
            do_action('gwapi_receive_action_ui_'.$action, [$post]);
        }, 'gwapi-receive-action', 'normal', 'default');
    }
}, 10);

function _gwapi_receive_action_ui()
{
    add_action('admin_footer', function() {
        ?>
        <script>
            jQuery(function($) {
                $('select[name="gwapi[action]"]').change(function() {
                    $(this).closest('form').submit();
                });
            });
        </script>
        <?php
    }, 20);
    $ID = get_the_ID();

    ?>
    <table width="100%" class="form-table">
        <tbody>
        <tr>
            <th width="25%">
                <?php _e('Phone number', 'gwapi'); ?>
            </th>
            <td>
                <input type="text" name="gwapi[receiver]" size="25" placeholder="451204"
                       value="<?= esc_attr(get_post_meta($ID, 'receiver', true)); ?>">
                <p class="description"><?php _e('Which number are SMS\'es sent to?', 'gwapi'); ?></p>
            </td>
        </tr>
        <tr>
            <th width="25%">
                <?php _e('Keyword', 'gwapi'); ?>
            </th>
            <td>
                <input type="text" name="gwapi[keyword]" size="25"
                       value="<?= esc_attr(get_post_meta($ID, 'keyword', true)); ?>">
                <p class="description"><?php _e('Enter the keyword which triggers this action.', 'gwapi'); ?></p>
            </td>
        </tr>
        <tr>
            <th width="25%">
                <?php _e('Receive action', 'gwapi'); ?>
            </th>
            <td>
                <select name="gwapi[action]" style="width: 100%">
                    <?php if (!get_post_meta($ID, 'action', true)): ?>
                        <option value="" disabled selected></option>
                    <?php endif; ?>
                    <?php
                    $actions = apply_filters('gwapi_receive_actions', []);
                    foreach($actions as $aid => $name):
                    ?>
                        <option value="<?= $aid ?>" <?= get_post_meta($ID, 'action', true) === $aid ? 'selected' : ''; ?>><?= $name; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Pick an action describing what will happen, when this receive action is triggered.', 'gwapi'); ?></p>
            </td>
        </tr>
        </tbody>
    </table>
    <?php
}

add_action('save_post_gwapi-receive-action', function($post_ID) {

    if (!isset($_POST['gwapi'])) return;

    if (isset($_POST['gwapi']['action'])) update_post_meta($post_ID, 'action', $_POST['gwapi']['action']);
    if (isset($_POST['gwapi']['keyword'])) update_post_meta($post_ID, 'keyword', $_POST['gwapi']['keyword']);
    if (isset($_POST['gwapi']['receiver'])) update_post_meta($post_ID, 'receiver', $_POST['gwapi']['receiver'] ?: '451204');

}, 10);