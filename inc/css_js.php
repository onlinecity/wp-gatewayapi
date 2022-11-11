<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

function gatewayapi__enqueue_uideps($only_register = false)
{
    wp_register_style('select2-4', gatewayapi__url() . 'lib/select2/css/select2.min.css', [], GATEWAYAPI_VERSION);
    wp_register_script('select2-4', gatewayapi__url() . 'lib/select2/js/select2.min.js', ['jquery'], GATEWAYAPI_VERSION, true);
    wp_register_script('gwapi-widgets', gatewayapi__url() . 'js/widgets.js', ['jquery', 'select2-4', 'underscore'], GATEWAYAPI_VERSION, true);
    wp_register_script('punycode-js', gatewayapi__url() . 'lib/punycode.js/punycode.min.js', [], GATEWAYAPI_VERSION, true);

    if ($only_register) return;

    wp_enqueue_script('alpinejs', gatewayapi__url().'lib/alpinejs/alpine.js', [], GATEWAYAPI_VERSION, true);
    wp_enqueue_script('gwapi-widgets');
    wp_enqueue_script('punycode-js');
    wp_enqueue_style('select2-4');

    $default_cc = (int)apply_filters('gwapi_default_country_code', get_option('gwapi_default_country_code', 45));
  ?>
    <script type="text/javascript">
        var GATEWAYAPI_PLUGINDIR = <?php echo json_encode(gatewayapi__url()); ?>;
        var GATEWAYAPI_I18N_DEFAULT_ERROR = <?php echo json_encode(__('Sorry, but there are errors in your input. Please attend to the highlighted fields below.', 'gatewayapi'));?>;
        var GATEWAYAPI_ADMINURL = <?php echo json_encode(admin_url()); ?>;
        var GATEWAYAPI_DEFAULT_CC = <?php echo json_encode($default_cc); ?>;
    </script>
    <?php
}

add_action('wp_enqueue_scripts', function () {
    gatewayapi__enqueue_uideps();
});

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    gatewayapi__enqueue_uideps();

    if ($screen->post_type && !in_array($screen->post_type, ['gwapi-sms', 'gwapi-recipient', 'gwapi-form', 'gwapi-receive-sms', 'gwapi-receive-action'])) return;
    if ($screen->taxonomy && !in_array($screen->taxonomy, ['gwapi-recipient-groups'])) return;

    wp_enqueue_style('gwapi-admin', gatewayapi__url() . 'css/wpadmin.css', [], GATEWAYAPI_VERSION);
    wp_enqueue_script('gwapi-admin', gatewayapi__url() . 'js/wpadmin.js', ['jquery-ui-tooltip'], GATEWAYAPI_VERSION, true);
}, 100000);
