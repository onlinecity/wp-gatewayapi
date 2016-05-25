<?php
/*
Plugin Name: GatewayAPI
Plugin URI:  https://wordpress.org/plugins/gatewayapi/
Description: Send SMS'es through WordPress.
Version:     1.0.1
Author:      OnlineCity ApS
Author URI:  http://onlinecity.dk
License:     GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: gwapi
*/

const GWAPI_DIR = __DIR__;

add_action('init', function () {
    // load translations
    load_plugin_textdomain( 'gwapi', false, 'gatewayapi/languages' );

    // public
    include GWAPI_DIR . "/inc/api.php";

    if (get_option('gwapi_enable_ui')) {
        include GWAPI_DIR . "/inc/helpers.php";
        include GWAPI_DIR . "/inc/cpt_sms.php";
        include GWAPI_DIR . "/inc/cpt_recipient.php";
        include GWAPI_DIR . "/inc/tax_recipient.php";
        include GWAPI_DIR . "/inc/validation.php";
    }

    // admin: editor required
    if (!current_user_can('edit_others_posts')) return;

    include GWAPI_DIR . "/inc/options.php";
    include GWAPI_DIR . "/inc/css_js.php";

    if (get_option('gwapi_enable_ui')) {
        include GWAPI_DIR . "/inc/cpt_recipient_ui.php";
        include GWAPI_DIR . "/inc/cpt_sms_editor_ui.php";
        include GWAPI_DIR . "/inc/cpt_sms_listing_ui.php";
    }
}, 9);