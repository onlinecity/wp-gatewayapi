<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

/**
 * Save the SMS Autoreply
 */
function _gwapi_receive_action_autoreply_edit_save($ID)
{
    if (!isset($_POST['gwapi']) || !$_POST['gwapi']) return;
    $data = $_POST['gwapi'];

    // update sms autoreply meta data
    if (isset($data['sender'])) update_post_meta($ID, 'sender', $data['sender']);
    if (isset($data['message'])) update_post_meta($ID, 'message', $data['message']);
}

/**
 * Registers the auto reply-option in the UI.
 */
add_filter('gwapi_receive_actions', function ($actions) {
    $actions['autoreply'] = 'Auto reply';
    return $actions;
}, 10, 1);

/**
 * Does the actual "send autoreply" stuff.
 */
add_action('gwapi_received_action_autoreply', function ($sms_ID, $action_post_ID) {

    // passing arguments as array will not resolve into multiple arguments automatically
    if (func_num_args() === 1) {
        $args = func_get_arg(0);
        $sms_ID = $args[0];
        $action_post_ID = $args[1];
    }

    // ignore non-published autoreplies
    if (get_post($action_post_ID)->post_status !== 'publish') {
        return;
    }

    // send reply
    $msisdn = get_post_meta($sms_ID, 'msisdn', true);
    $sender = get_post_meta($action_post_ID, 'sender', true);
    $message = get_post_meta($action_post_ID, 'message', true);

    gwapi_send_sms($message, [$msisdn], $sender);

}, 10);

/**
 * Generate UI for setting up the auto-reply action.
 */
add_action('gwapi_receive_action_ui_autoreply', function ($post) {
    require_once('receive_action_autoreply_ui.php');
    _gwapi_receive_action_autoreply($post);
});

/**
 * Save changes from the UI.
 */
add_action('save_post_gwapi-receive-action', '_gwapi_receive_action_autoreply_edit_save');
add_action('publish_post_gwapi-receive-action', '_gwapi_receive_action_autoreply_edit_save', 9);
