<?php
/*
Plugin Name: GatewayAPI
Plugin URI:  https://wordpress.org/plugins/gatewayapi/
Description: Send SMS'es through WordPress.
Version:     1.1.0
Author:      OnlineCity ApS
Author URI:  http://onlinecity.dk
License:     GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: gwapi
*/

function _gwapi_dir()
{
    return __DIR__;
}

function _gwapi_url()
{
    static $dir;
    if ($dir) return $dir;

    $dir = plugin_dir_url(__FILE__);
    return $dir;
}


add_action('init', function () {
    $D = _gwapi_dir();

    // load translations
    load_plugin_textdomain( 'gwapi', false, 'gatewayapi/languages' );

    // public
    include "$D/inc/api.php";
    include "$D/inc/recipient_forms.php";

    if (get_option('gwapi_enable_ui')) {
        include "$D/inc/helpers.php";
        include "$D/inc/cpt_sms.php";
        include "$D/inc/cpt_recipient.php";
        include "$D/inc/tax_recipient.php";
        include "$D/inc/cpt_recipient_ui.php";
        include "$D/inc/validation.php";
        include "$D/inc/css_js.php";

        if (!is_admin()) {
            include "$D/inc/shortcode.php";
        }

        if (get_option('gwapi_user_sync_enable')) {
            include "$D/inc/user_sync.php";
        }
    }

    // admin: editor required
    if (!current_user_can('edit_others_posts')) return;

    include "$D/inc/options.php";

    if (get_option('gwapi_enable_ui')) {
        include "$D/inc/cpt_recipient_listing_ui.php";
        include "$D/inc/cpt_sms_editor_ui.php";
        include "$D/inc/cpt_sms_listing_ui.php";
        include "$D/inc/recipient_import.php";
    }
}, 9);