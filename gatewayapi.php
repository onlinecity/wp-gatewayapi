<?php
/*
Plugin Name: GatewayAPI
Plugin URI:  https://wordpress.org/plugins/gatewayapi/
Description: Manage SMS broadcasts via WordPress
Version:     1.8.0
Author:      OnlineCity ApS
Author URI:  http://onlinecity.dk
License:     MIT
License URI: https://opensource.org/licenses/MIT
Text Domain: gatewayapi
Domain Path: /languages
*/
if (!defined('ABSPATH')) die('Cannot be accessed directly!');

const GATEWAYAPI_VERSION = '1.8.0';
global $gatewayapi_db_version;
$gatewayapi_db_version = '1.0';

function gatewayapi__dir()
{
  return __DIR__;
}

function gatewayapi__url()
{
  static $dir;
  if ($dir) return $dir;

  $dir = plugin_dir_url(__FILE__);
  return $dir;
}

function gatewayapi_install()
{
  global $wpdb;
  global $gatewayapi_db_version;

  gatewayapi_recipients_create_db();
  add_option('gatewayapi_db_version', $gatewayapi_db_version);
}

function gatewayapi_update_db_check()
{
  global $gatewayapi_db_version;
  $db_version = get_site_option('gatewayapi_db_version');
  if (get_site_option('gatewayapi_db_version') != $gatewayapi_db_version) {
    gatewayapi_install();
  }
}

add_action('plugins_loaded', 'gatewayapi_update_db_check');

function gatewayapi_recipients_create_db()
{
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

  $table_name = $wpdb->prefix . 'oc_recipients_import';

  $sql = "
		CREATE TABLE $table_name (
		  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		  `phone_number` int(12) NOT NULL,
		  `country_code` int(2) NOT NULL,
		  `post_id` int(11) NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `${table_name}_phone_number_IDX` (`phone_number`),
		  KEY `${table_name}_country_code_IDX` (`country_code`)
) $charset_collate";

  dbDelta($sql);
}


register_activation_hook(__FILE__, 'gatewayapi_recipients_create_db');

function gatewayapi__initialize_cf7_admin()
{
  require_once(gatewayapi__dir() . '/inc/integration_contact_form_7.php');
  GwapiContactForm7::getInstance()->initAdmin();
}

function gatewayapi__initialize_cf7_shortcodes()
{
  require_once(gatewayapi__dir() . '/inc/integration_contact_form_7.php');
  GwapiContactForm7::getInstance()->handleShortcodes();
}

function gatewayapi__initialize_cf7_submit($form)
{
  require_once(gatewayapi__dir() . '/inc/integration_contact_form_7.php');
  GwapiContactForm7::getInstance()->handleSubmit($form);
}


add_action('init', function () {
  $D = gatewayapi__dir();

  // public
  require_once("$D/inc/api.php");
  require_once("$D/inc/recipient_forms.php");
  require_once("$D/inc/triggers.php");
  require_once("$D/inc/helpers.php");

  // plugin: contact form 7
  add_action('wpcf7_admin_init', "gatewayapi__initialize_cf7_admin", 18);
  add_action('wpcf7_init', "gatewayapi__initialize_cf7_shortcodes", 18);
  add_action("wpcf7_before_send_mail", "gatewayapi__initialize_cf7_submit");

  if (get_option('gwapi_enable_ui')) {
    require_once("$D/inc/cpt_sms.php");
    require_once("$D/inc/cpt_recipient.php");
    require_once("$D/inc/tax_recipient.php");
    require_once("$D/inc/cpt_recipient_ui.php");
    require_once("$D/inc/validation.php");
    require_once("$D/inc/cpt_notification.php");


    if (!is_admin()) {
      require_once("$D/inc/shortcode.php");
    }

    if (get_option('gwapi_user_sync_enable')) {
      require_once("$D/inc/user_sync.php");
    }

    // enable receive-sms?
    if (get_option('gwapi_receive_sms_enable')) {
      require_once("$D/inc/cpt_receive_sms.php");
    }
  }
  if (get_option('gwapi_enable_ui') || (isset($_GET['page']) && $_GET['page'] == 'gatewayapi' && strpos($_SERVER['SCRIPT_NAME'], '/options-general.php') != 0)) {
    require_once("$D/inc/css_js.php");
  }

  if (get_option('gwapi_security_enable')) {
    require_once("$D/inc/css_js.php");
    require_once("$D/inc/security_two_factor.php");
  }

  // admin: editor required
  if (!current_user_can('edit_others_posts')) return;

  require_once("$D/inc/options.php");

  if (get_option('gwapi_enable_ui')) {
    require_once("$D/inc/cpt_recipient_listing_ui.php");
    require_once("$D/inc/cpt_sms_editor_ui.php");
    require_once("$D/inc/cpt_sms_listing_ui.php");
    require_once("$D/inc/recipient_import.php");

    // only require receive-sms ui if receive-sms is enabled
    if (get_option('gwapi_receive_sms_enable')) {
      require_once("$D/inc/cpt_receive_sms_ui.php");
    }
  }
}, 9);

add_action('plugins_loaded', function () {
  // load translations
  load_plugin_textdomain('gatewayapi', false, 'gatewayapi/languages/');
});

/**
 * Set body class for recipients
 * @param String $classes Current body classes.
 * @return String          Altered body classes.
 */
add_filter('admin_body_class', function ($classes) {
  $current_screen = get_current_screen();
  $page_id = str_replace('edit-', '', $current_screen->id);
  $classes .= " {$page_id}-ui";
  return $classes;
});
