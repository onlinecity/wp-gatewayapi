<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

add_action('admin_menu', function () {
    if (!current_user_can('activate_plugins')) return;

    add_submenu_page('options-general.php', __('GatewayAPI Settings', 'gatewayapi'), __('GatewayAPI Settings', 'gatewayapi'), 'administrator', 'gatewayapi', function () {
        wp_enqueue_script('gwapi-settings', _gwapi_url() . '/js/wpadmin-settings.js');
        wp_enqueue_script('jquery-ui-tooltip');
        wp_enqueue_script('jquery-ui-sortable');
        include _gwapi_dir() . "/tpl/settings_page.php";
    });
});

add_action('admin_init', function () {
    register_setting('gatewayapi', 'gwapi_key');
    register_setting('gatewayapi', 'gwapi_secret');
    register_setting('gatewayapi', 'gwapi_enable_ui');
    register_setting('gatewayapi', 'gwapi_recaptcha_site_key');
    register_setting('gatewayapi', 'gwapi_recaptcha_secret_key');
    register_setting('gatewayapi', 'gwapi_default_country_code');
    register_setting('gatewayapi', 'gwapi_default_sender');
    register_setting('gatewayapi', 'gwapi_recipient_fields');

    register_setting('gatewayapi', 'gwapi_user_sync_enable', 'intval');
    register_setting('gatewayapi', 'gwapi_user_sync_meta_number');
    register_setting('gatewayapi', 'gwapi_user_sync_meta_countrycode');
    register_setting('gatewayapi', 'gwapi_user_sync_meta_default_countrycode', 'intval');
    register_setting('gatewayapi', 'gwapi_user_sync_meta_other_fields');
    register_setting('gatewayapi', 'gwapi_user_sync_group_map');

    register_setting('gatewayapi', 'gwapi_security_enable');
    register_setting('gatewayapi', 'gwapi_security_required_roles');
    register_setting('gatewayapi', 'gwapi_security_cookie_lifetime');
    register_setting('gatewayapi', 'gwapi_security_bypass_code');

    register_setting('gatewayapi', 'gwapi_receive_sms_enable', 'intval');

});
