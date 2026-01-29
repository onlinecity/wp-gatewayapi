<?php
/*
Plugin Name: GatewayAPI
Plugin URI:  https://wordpress.org/plugins/gatewayapi/
Description: Manage SMS broadcasts via WordPress
Version:     2.0.0
Author:      OnlineCity ApS
Author URI:  http://onlinecity.dk
License:     MIT
License URI: https://opensource.org/licenses/MIT
Text Domain: gatewayapi
Domain Path: /languages
*/
if (!defined('ABSPATH')) die('Cannot be accessed directly!');

require_once( plugin_dir_path( __FILE__ ) . '/libraries/action-scheduler/action-scheduler.php' );

const GATEWAYAPI_VERSION = '2.0.0';

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

add_action('init', function () {
  $D = gatewayapi__dir();

  // public
  require_once("$D/inc/api.php");
  require_once("$D/inc/campaigns-scheduling.php");

  // admin: editor required
  if (!current_user_can('gatewayapi_manage')) return;

  require_once("$D/inc/options.php");
  require_once("$D/inc/admin-ajax.php");
  require_once("$D/inc/contacts-post-type.php");
  require_once("$D/inc/contacts-ajax.php");
  require_once("$D/inc/campaigns-post-type.php");
  require_once("$D/inc/campaigns-ajax.php");
  require_once("$D/inc/admin-menu.php");
}, 9);

if (defined('GATEWAYAPI_DEVSERVER')) {
  require_once(gatewayapi__dir() . "/inc/devserver.php");
}

/**
 * On activation: Add gatewayapi_manage capability to all roles with edit_posts capability.
 */
register_activation_hook(__FILE__, function () {
  $wp_roles = wp_roles();
  foreach ($wp_roles->roles as $role_name => $role_info) {
    if (isset($role_info['capabilities']['edit_posts']) && $role_info['capabilities']['edit_posts']) {
      $wp_roles->add_cap($role_name, 'gatewayapi_manage');
    }
  }
});