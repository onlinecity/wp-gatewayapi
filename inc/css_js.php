<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

function _gwapi_enqueue_uideps($only_register = false)
{
    wp_register_style('select2-4', _gwapi_url() . 'lib/select2/css/select2.min.css');
    wp_register_script('select2-4', _gwapi_url() . 'lib/select2/js/select2.min.js', ['jquery'], 1, true);
    wp_register_script('gwapi-widgets', _gwapi_url() . 'js/widgets.js', ['jquery', 'select2-4', 'underscore'], 3, true);

    if ($only_register) return;

    wp_enqueue_script('gwapi-widgets');
    wp_enqueue_style('select2-4');

    ?>
    <script type="text/javascript">
        var GWAPI_PLUGINDIR = <?= json_encode(_gwapi_url()); ?>;
        var GWAPI_I18N_DEFAULT_ERROR = <?= json_encode(__('Sorry, but there are errors in your input. Please attend to the highlighted fields below.', 'gwapi'));?>;
        var GWAPI_ADMINURL = <?= json_encode(admin_url()); ?>;
    </script>
    <?php
}

add_action('wp_enqueue_scripts', function () {
    _gwapi_enqueue_uideps();
});

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    _gwapi_enqueue_uideps();

    if ($screen->post_type && !in_array($screen->post_type, ['gwapi-sms', 'gwapi-recipient', 'gwapi-form', 'gwapi-receive-sms', 'gwapi-receive-action'])) return;
    if ($screen->taxonomy && !in_array($screen->taxonomy, ['gwapi-recipient-groups'])) return;

    wp_enqueue_style('gwapi-admin', _gwapi_url() . 'css/wpadmin.css');
    wp_enqueue_script('gwapi-admin', _gwapi_url() . 'js/wpadmin.js', ['jquery-ui-tooltip'], 2, true);
}, 100000);