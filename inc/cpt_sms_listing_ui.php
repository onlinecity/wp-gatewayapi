<?php

/**
 * Define which columns we'll need.
 */
add_filter('manage_gwapi-sms_posts_columns', function ($columns) {
    unset($columns['title']);
    $date_text = $columns['date'];
    unset($columns['date']);

    return array_merge($columns, [
        'message' => __('Message', 'gwapi'),
        'sender' => __('Sender', 'gwapi'),
        'recipients' => __('Recipients', 'gwapi'),
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
            echo wp_trim_words(esc_html(get_post_meta($ID, 'message', true) ? : '-'), 8);
            break;

        case 'recipients':
            $count = get_post_meta($ID, 'recipients_count', true);
            if ($count) {
                echo $count;
            } else {
                echo '<p class="description">'.__('Calculated at send', 'gwapi').'</p>';
            }

            break;
    }

}, 10, 2);