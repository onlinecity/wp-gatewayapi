<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

add_action('wp_ajax_gatewayapi_devserver', function () {
    if (!current_user_can('gatewayapi_manage')) {
        wp_die('Unauthorized');
    }
    $url = 'http://' . GATEWAYAPI_DEVSERVER;
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        echo "Error fetching from dev server: " . $response->get_error_message();
    } else {
        $body = wp_remote_retrieve_body($response);
        $body = str_replace('src="/', 'src="' . $url . '/', $body);
        $body = str_replace('href="/', 'href="' . $url . '/', $body);
        header('Content-type: text/html; charset=utf-8');
        
        echo $body;
    }
    wp_die();
});
