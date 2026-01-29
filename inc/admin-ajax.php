<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

/**
 * Get the key status for GatewayAPI
 */
add_action('wp_ajax_gatewayapi_get_key_status', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $token = get_option('gwapi_token');
    $setup = get_option('gwapi_setup');

    if (empty($token) || empty($setup)) {
        wp_send_json_success([
            'hasKey' => false,
        ]);
    }

    // Determine the API base URL based on setup
    $baseUrl = $setup === 'eu' ? 'https://gatewayapi.eu' : 'https://gatewayapi.com';

    // Test the token by calling the /rest/me endpoint
    $response = wp_remote_get($baseUrl . '/rest/me', [
        'headers' => [
            'Authorization' => 'Token ' . $token,
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_success([
            'hasKey' => true,
            'keyIsValid' => false,
            'message' => $response->get_error_message()
        ]);
    }

    $statusCode = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($statusCode < 200 || $statusCode >= 300) {
        wp_send_json_success([
            'hasKey' => true,
            'keyIsValid' => false,
        ]);
    }

    wp_send_json_success([
        'hasKey' => true,
        'keyIsValid' => true,
        'credit' => isset($body['credit']) ? $body['credit'] : null,
        'currency' => isset($body['currency']) ? $body['currency'] : null,
    ]);
});

/**
 * Save the connection settings for GatewayAPI
 */
add_action('wp_ajax_gatewayapi_save_connection', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $token = isset($_POST['gwapi_token']) ? sanitize_text_field($_POST['gwapi_token']) : '';
    $setup = isset($_POST['gwapi_setup']) ? sanitize_text_field($_POST['gwapi_setup']) : 'com';
    $apiVersion = isset($_POST['gwapi_api_version']) ? sanitize_text_field($_POST['gwapi_api_version']) : 'sms';

    $token_changed = true;
    // If token is just dots, it means the user hasn't changed it (backwards compatibility)
    // Or if token is empty string/null, it means the user hasn't changed it
    if (empty($token)) {
        $token = get_option('gwapi_token');
        $token_changed = false;
    }

    if (empty($token)) {
        wp_send_json_error(['message' => 'Token is required']);
    }

    if (!in_array($setup, ['com', 'eu'])) {
        wp_send_json_error(['message' => 'Invalid setup value']);
    }

    if (!in_array($apiVersion, ['sms', 'messaging'])) {
        wp_send_json_error(['message' => 'Invalid API version']);
    }

    $body = null;
    if ($token_changed) {
        // Determine the API base URL based on setup
        $baseUrl = $setup === 'eu' ? 'https://gatewayapi.eu' : 'https://gatewayapi.com';

        // Test the token by calling the /rest/me endpoint
        $response = wp_remote_get($baseUrl . '/rest/me', [
            'headers' => [
                'Authorization' => 'Token ' . $token,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Failed to connect to GatewayAPI: ' . $response->get_error_message()]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = isset($body['message']) ? $body['message'] : 'Invalid token';
            wp_send_json_error(['message' => $errorMessage]);
        }

        // Token is valid, save the settings
        update_option('gwapi_token', $token);
    }

    update_option('gwapi_setup', $setup);
    update_option('gwapi_api_version', $apiVersion);

    wp_send_json_success([
        'message' => 'Connection settings saved successfully',
        'credit' => isset($body['credit']) ? $body['credit'] : null,
        'currency' => isset($body['currency']) ? $body['currency'] : null,
    ]);
});

/**
 * Save the default settings for GatewayAPI
 */
add_action('wp_ajax_gatewayapi_save_defaults', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $countryCode = isset($_POST['gwapi_default_country_code']) ? sanitize_text_field($_POST['gwapi_default_country_code']) : '45';
    $sender = isset($_POST['gwapi_default_sender']) ? sanitize_text_field($_POST['gwapi_default_sender']) : '';
    $sendSpeed = isset($_POST['gwapi_default_send_speed']) ? intval($_POST['gwapi_default_send_speed']) : 60;

    // Validate sender
    if (!empty($sender)) {
        $is_digits_only = preg_match('/^\d+$/', $sender);
        if ($is_digits_only) {
            if (strlen($sender) > 18) {
                wp_send_json_error(['message' => 'Default sender cannot be more than 18 digits']);
            }
        } else {
            if (strlen($sender) > 11) {
                wp_send_json_error(['message' => 'Default sender cannot be more than 11 characters when it contains non-digit characters']);
            }
        }
    }

    // Validate country code (should be numeric)
    if (!is_numeric($countryCode) || intval($countryCode) < 1) {
        wp_send_json_error(['message' => 'Invalid country code']);
    }

    // Validate send speed (1-1000)
    if ($sendSpeed < 1 || $sendSpeed > 1000) {
        wp_send_json_error(['message' => 'Send speed must be between 1 and 1000']);
    }

    update_option('gwapi_default_country_code', $countryCode);
    update_option('gwapi_default_sender', $sender);
    update_option('gwapi_default_send_speed', $sendSpeed);

    wp_send_json_success([
        'message' => 'Default settings saved successfully',
    ]);
});

/**
 * Get the current settings for GatewayAPI
 */
add_action('wp_ajax_gatewayapi_get_settings', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $token = get_option('gwapi_token');
    $setup = get_option('gwapi_setup', 'com');
    $apiVersion = get_option('gwapi_api_version', 'sms');
    $countryCode = get_option('gwapi_default_country_code', '45');
    $sender = get_option('gwapi_default_sender', '');
    $sendSpeed = get_option('gwapi_default_send_speed', '60');

    wp_send_json_success([
        'hasKey' => !empty($token),
        'gwapi_setup' => $setup,
        'gwapi_api_version' => $apiVersion,
        'gwapi_default_country_code' => $countryCode,
        'gwapi_default_sender' => $sender,
        'gwapi_default_send_speed' => $sendSpeed,
    ]);
});