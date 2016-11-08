<?php

add_action('admin_menu', function () {
    if (!current_user_can('activate_plugins')) return;

    add_submenu_page('options-general.php', __('GatewayAPI Settings', 'gwapi'), __('GatewayAPI Settings', 'gwapi'), 'administrator', 'gatewayapi', function () {
        wp_enqueue_script('gwapi-settings', _gwapi_url() . '/js/wpadmin-settings.js');
        wp_enqueue_script('jquery-ui-tooltip');
        wp_enqueue_script('jquery-ui-sortable');
        include _gwapi_dir() . "/tpl/settings_page.php";
    });
});

add_action('admin_init', function () {
    register_setting('gwapi', 'gwapi_key');
    register_setting('gwapi', 'gwapi_secret');
    register_setting('gwapi', 'gwapi_enable_ui');
    register_setting('gwapi', 'gwapi_recaptcha_site_key');
    register_setting('gwapi', 'gwapi_recaptcha_secret_key');
    register_setting('gwapi', 'gwapi_default_sender');
    register_setting('gwapi', 'gwapi_recipient_fields');

    register_setting('gwapi', 'gwapi_user_sync_enable', 'intval');
    register_setting('gwapi', 'gwapi_user_sync_meta_number');
    register_setting('gwapi', 'gwapi_user_sync_meta_countrycode');
    register_setting('gwapi', 'gwapi_user_sync_meta_default_countrycode', 'intval');
    register_setting('gwapi', 'gwapi_user_sync_meta_other_fields');
    register_setting('gwapi', 'gwapi_user_sync_group_map');

    register_setting('gwapi', 'gwapi_sms_inbox_enable', 'intval');

});