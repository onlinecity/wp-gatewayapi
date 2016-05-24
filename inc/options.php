<?php

add_action('admin_menu', function() {
    if (!current_user_can('activate_plugins')) return;

    add_submenu_page('options-general.php', __('GatewayAPI Settings','gwapi'), __('GatewayAPI Settings','gwapi'), 'administrator', 'gatewayapi', function() {
       include GWAPI_DIR."/tpl/settings_page.php";
    });
});

add_action('admin_init', function() {
    register_setting( 'gwapi', 'gwapi_key' );
    register_setting( 'gwapi', 'gwapi_secret' );
    register_setting( 'gwapi', 'gwapi_enable_ui' );
    register_setting( 'gwapi', 'gwapi_default_sender' );
});