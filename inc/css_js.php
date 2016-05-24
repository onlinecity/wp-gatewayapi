<?php

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();

    if ($screen->post_type && !in_array($screen->post_type, ['gwapi-sms', 'gwapi-recipient'])) return;
    if ($screen->taxonomy && !in_array($screen->taxonomy, ['gwapi-recipient-groups'])) return;

    wp_enqueue_style('gwapi-admin', plugin_dir_url(GWAPI_DIR.'/gatewayapi.php') . 'css/wpadmin.css');
    wp_enqueue_script('gwapi-admin', plugin_dir_url(GWAPI_DIR.'/gatewayapi.php') . 'js/wpadmin.js', [], 2, true);

    wp_enqueue_style('select2', plugin_dir_url(GWAPI_DIR.'/gatewayapi.php') . 'lib/select2/css/select2.min.css');
    wp_enqueue_script('select2', plugin_dir_url(GWAPI_DIR.'/gatewayapi.php') . 'lib/select2/js/select2.min.js', [], 1, true);

    ?>
    <script type="text/javascript">
        var GWAPI_PLUGINDIR = <?= json_encode(plugin_dir_url(GWAPI_DIR.'/gatewayapi.php')); ?>;
        var GWAPI_I18N_DEFAULT_ERROR = <?= json_encode(__('Sorry, but there are errors in your input. Please attend to the highlighted fields below.', 'gwapi'));?>;
    </script>
    <?php
}, 100000);