<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

add_action('admin_menu', function () {
    $logo_url = gatewayapi__url() . 'img/gatewayapi-logo-only.svg';

    add_menu_page(
        'GatewayAPI',
        'GatewayAPI',
        'gatewayapi_manage',
        'gatewayapi',
        'gatewayapi_admin_page_callback',
        $logo_url,
        45
    );

    add_submenu_page(
        'gatewayapi',
        'Campaigns',
        'Campaigns',
        'gatewayapi_manage',
        'gatewayapi-campaigns',
        'gatewayapi_admin_page_callback'
    );

    add_submenu_page(
        'gatewayapi',
        'Contacts',
        'Contacts',
        'gatewayapi_manage',
        'gatewayapi-contacts',
        'gatewayapi_admin_page_callback'
    );

    if (class_exists('WooCommerce')) {
        add_submenu_page(
                'gatewayapi',
                'WooCommerce',
                'WooCommerce',
                'gatewayapi_manage',
                'gatewayapi-woocommerce',
                'gatewayapi_admin_page_callback'
        );
    }

    add_submenu_page(
        'gatewayapi',
        'Settings',
        'Settings',
        'gatewayapi_manage',
        'gatewayapi-settings',
        'gatewayapi_admin_page_callback'
    );
});

function gatewayapi_admin_page_callback() {
    $page = substr($_GET['page'], 11);

    $url = gatewayapi__url() . 'admin-ui/dist/index.html';
    if (defined('GATEWAYAPI_DEVSERVER')) {
        $url = admin_url('admin-ajax.php?action=gatewayapi_devserver');
    }
    $url .= '#'.$page;

    ?>
    <iframe src="<?= esc_attr($url); ?>" id="gatewayapi-admin-ui" style="width: 100%; height: 100px;" allowtransparency></iframe>
    <?php if (defined('GATEWAYAPI_DEVSERVER')): ?>
        <div style="margin-top: 20px; padding: 20px; border-top: 1px solid #ccc;">
            <button type="button" class="button button-secondary" onclick="window.open('<?= esc_js($url) ?>', '_blank')">
                Open in new window (DevServer)
            </button>
        </div>
    <?php endif; ?>
    <style>
        #wpcontent {
            padding-left: 0;
        }
    </style>
    <script>
        jQuery(($) => {
            const iframe = $('#gatewayapi-admin-ui');
            const wpBodyContent = $('#wpbody-content');
            if (iframe.length && wpBodyContent.length) {
                iframe.css('min-height', wpBodyContent.outerHeight() + 'px');
            }
        });
    </script>
    <?php
}

add_action('admin_notices', function () {
    if (!get_option('gatewayapi_show_v2_notice')) {
        return;
    }

    if (!current_user_can('gatewayapi_manage')) {
        return;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $is_cf7_active = is_plugin_active('contact-form-7/wp-contact-form-7.php');
    ?>
    <div class="notice notice-warning is-dismissible gatewayapi-v2-upgrade-notice">
        <p><strong>GatewayAPI-plugin upgraded to v2</strong></p>
        <p>The new version of GatewayAPI has breaking changes:</p>
        <ul style="list-style: disc; margin-left: 2em;">
            <li>You must re-add credentials, as the plugin now needs a REST API token.</li>
            <li>Campaigns and contacts have not been migrated.</li>
            <li>Two-factor authentication must be reconfigured.</li>
            <?php if ( $is_cf7_active ): ?>
                <li>Contact Form 7-integration has been removed.</li>
            <?php endif; ?>
        </ul>
        <p>We are sorry for the inconvenience.</p>
        <p>If you want to go back, you can <a href="https://downloads.wordpress.org/plugin/gatewayapi.1.8.3.zip">download the last 1.x version
                here</a>.</p>
    </div>
    <script>
        jQuery(document).on('click', '.gatewayapi-v2-upgrade-notice .notice-dismiss', function () {
            jQuery.post(ajaxurl, {
                action: 'gatewayapi_dismiss_v2_notice'
            });
        });
    </script>
    <?php
} );