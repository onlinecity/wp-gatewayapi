<?php

/**
 * Registers the auto reply-option in the UI.
 */
add_filter('gwapi_receive_actions', function($actions) {
    $actions['autoreply'] = 'Auto reply';
    return $actions;
}, 10, 1);

/**
 * Does the actual "send autoreply" stuff.
 */
add_action('gwapi_received_action_autoreply', function($sms_ID, $action_post_ID) {

    // @todo auto-reply stuff!

});

/**
 * Generate UI for setting up the auto-reply action.
 */
add_action('gwapi_receive_action_ui_autoreply', function($action_post) {
    echo "bingo!";
});

/**
 * Save changes from the UI.
 */
add_action('save_post_gwapi-receive-action', function($post_ID) {
    
});