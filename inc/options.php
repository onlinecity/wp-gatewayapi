<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

add_action('admin_init', function () {
    register_setting('gatewayapi', 'gwapi_token'); // NEW
    register_setting('gatewayapi', 'gwapi_setup'); // eu or com
    register_setting('gatewayapi', 'gwapi_default_sender'); // Info
    register_setting('gatewayapi', 'gwapi_default_send_speed'); // Messages per minute (1-1000)
    register_setting('gatewayapi', 'gwapi_contact_fields');
    register_setting('gatewayapi', 'gwapi_woocommerce_enabled');
    register_setting('gatewayapi', 'gwapi_woocommerce_allowed_countries');
});
