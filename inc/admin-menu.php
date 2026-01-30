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

    if (get_option('gwapi_woocommerce_enabled') === '1') {
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
