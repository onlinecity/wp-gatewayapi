<?php
/*
Plugin Name: GatewayAPI
Plugin URI:  https://wordpress.org/plugins/gatewayapi/
Description: Send SMS'es through WordPress.
Version:     1.6.0
Author:      OnlineCity ApS
Author URI:  http://onlinecity.dk
License:     MIT
License URI: https://opensource.org/licenses/MIT
Text Domain: gwapi
Domain Path: /languages
*/
if (!defined('ABSPATH')) die('Cannot be accessed directly!');

const GATEAYAPI_VERSION = '1.6.0';

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

function _gwapi_initialize_cf7_admin()
{
    require_once(_gwapi_dir() . '/inc/integration_contact_form_7.php');
    GwapiContactForm7::getInstance()->initAdmin();
}

function _gwapi_initialize_cf7_shortcodes()
{
    require_once(_gwapi_dir() . '/inc/integration_contact_form_7.php');
    GwapiContactForm7::getInstance()->handleShortcodes();
}

function _gwapi_initialize_cf7_submit($form)
{
    require_once(_gwapi_dir() . '/inc/integration_contact_form_7.php');
    GwapiContactForm7::getInstance()->handleSubmit($form);
}


add_action('init', function () {
    $D = _gwapi_dir();

    // public
    include "$D/inc/api.php";
    include "$D/inc/recipient_forms.php";

    // plugin: contact form 7
    add_action('wpcf7_admin_init', "_gwapi_initialize_cf7_admin", 18);
    add_action('wpcf7_init', "_gwapi_initialize_cf7_shortcodes", 18);
    add_action("wpcf7_before_send_mail", "_gwapi_initialize_cf7_submit");

    if (get_option('gwapi_enable_ui')) {

        include "$D/inc/helpers.php";
        include "$D/inc/cpt_sms.php";
        include "$D/inc/cpt_recipient.php";
        include "$D/inc/tax_recipient.php";
        include "$D/inc/cpt_recipient_ui.php";
        include "$D/inc/validation.php";

        if (!is_admin()) {
            include "$D/inc/shortcode.php";
        }

        if (get_option('gwapi_user_sync_enable')) {
            include "$D/inc/user_sync.php";
        }

        // enable receive-sms?
        if (get_option('gwapi_receive_sms_enable')) {
            include "$D/inc/cpt_receive_sms.php";
            include "$D/inc/cpt_receive_handler.php";
            include "$D/inc/receive_action_autoreply.php";
        }
    }
    if (get_option('gwapi_enable_ui') || (isset($_GET['page']) && $_GET['page'] == 'gatewayapi' && strpos($_SERVER['SCRIPT_NAME'], '/options-general.php') != 0)) {
        require_once("$D/inc/css_js.php");
    }

    if (get_option('gwapi_security_enable')) {
        require_once("$D/inc/css_js.php");
        include "$D/inc/security_two_factor.php";
    }

    // admin: editor required
    if (!current_user_can('edit_others_posts')) return;

    include "$D/inc/options.php";

    if (get_option('gwapi_enable_ui')) {
        include "$D/inc/cpt_recipient_listing_ui.php";
        include "$D/inc/cpt_sms_editor_ui.php";
        include "$D/inc/cpt_sms_listing_ui.php";
        include "$D/inc/recipient_import.php";

        // only include receive-sms ui if receive-sms is enabled
        if (get_option('gwapi_receive_sms_enable')) {
            include "$D/inc/cpt_receive_sms_ui.php";
        }
    }

}, 9);

add_action('plugins_loaded', function() {
    // load translations
    load_plugin_textdomain('gatewayapi', false, 'gatewayapi/languages/');
});
