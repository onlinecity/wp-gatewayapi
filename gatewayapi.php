<?php
/*
Plugin Name: GatewayAPI
Plugin URI:  https://wordpress.org/plugins/gatewayapi/
Description: Manage SMS broadcasts via WordPress
Version:     2.1.0
Author:      OnlineCity ApS
Author URI:  http://onlinecity.dk
License:     GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cannot be accessed directly!' );
}

require_once( plugin_dir_path( __FILE__ ) . '/libraries/action-scheduler/action-scheduler.php' );

const GATEWAYAPI_VERSION = '2.1.0';

function gatewayapi__dir() {
	return __DIR__;
}

function gatewayapi__url() {
	static $dir;
	if ( $dir ) {
		return $dir;
	}

	$dir = plugin_dir_url( __FILE__ );

	return $dir;
}

function gatewayapi_ensure_capabilities() {
	if ( ! is_admin() ) {
		return;
	}

	$current_version = get_option( 'gatewayapi_version' );
	if ( $current_version && version_compare( $current_version, '2.0.0', '>=' ) ) {
		return;
	}

	// If the current version is less than 2.0.0, we are upgrading from v1
	if ( $current_version && version_compare( $current_version, '2.0.0', '<' ) ) {
		update_option( 'gatewayapi_show_v2_notice', true );
	}

	$wp_roles = wp_roles();
	foreach ( $wp_roles->roles as $role_name => $role_info ) {
		if ( isset( $role_info['capabilities']['edit_posts'] ) && $role_info['capabilities']['edit_posts'] ) {
			$wp_roles->add_cap( $role_name, 'gatewayapi_manage' );
		}
	}
	update_option( 'gatewayapi_version', GATEWAYAPI_VERSION );
}

add_action( 'init', function () {
	$D = gatewayapi__dir();

	gatewayapi_ensure_capabilities();

	// public
	require_once( "$D/inc/helpers.php" );
	require_once( "$D/inc/api.php" );
	require_once( "$D/inc/two-fa.php" );
	require_once( "$D/inc/campaigns-scheduling.php" );
	require_once( "$D/inc/woocommerce-scheduling.php" );
	require_once( "$D/inc/contacts-post-type.php" );
	require_once( "$D/inc/shortcodes.php" );

	// admin: editor required
	if ( ! current_user_can( 'gatewayapi_manage' ) ) {
		return;
	}

	require_once( "$D/inc/admin-ajax.php" );
	require_once( "$D/inc/contacts-ajax.php" );
	require_once( "$D/inc/campaigns-post-type.php" );
	require_once( "$D/inc/campaigns-ajax.php" );
	require_once( "$D/inc/woocommerce-post-type.php" );
	require_once( "$D/inc/woocommerce-ajax.php" );
	require_once( "$D/inc/admin-menu.php" );

//	if ( get_option( 'gatewayapi_show_v2_notice' ) !== false || get_option('gwapi_enable_ui')) {
	require_once( "$D/inc/migration-tool.php" );
//	}
}, 9 );

if ( defined( 'GATEWAYAPI_DEVSERVER' ) ) {
	require_once( gatewayapi__dir() . "/inc/devserver.php" );
}