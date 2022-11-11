<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

/**
 * Define which columns we'll need.
 */
add_filter('manage_gwapi-sms_posts_columns', function ($columns) {
    unset($columns['title']);
    $date_text = $columns['date'];
    unset($columns['date']);

    return array_merge($columns, [
        'message' => __('Message', 'gatewayapi'),
        'sender' => __('Sender', 'gatewayapi'),
        'recipients' => __('Recipients', 'gatewayapi'),
        'date' => $date_text
    ]);
});


/**
 * Print the content for our custom columns.
 */
add_action('manage_posts_custom_column', function($column, $ID) {
    if (get_post_type($ID) != 'gwapi-sms') return;

    switch($column) {
        case 'sender':
            echo esc_html(get_post_meta($ID, 'sender', true) ? : '-');
            break;

        case 'message':
            echo esc_html(wp_trim_words(get_post_meta($ID, 'message', true) ? : '-', 8));
            break;

        case 'recipients':
            $count = get_post_meta($ID, 'recipients_count', true);
            if ($count) {
                echo esc_html($count);
            } else {
                echo '<p class="description">'.__('Calculated at send', 'gatewayapi').'</p>';
            }

            break;
    }

}, 10, 2);
