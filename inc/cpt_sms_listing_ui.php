<?php

/**
 * Define which columns we'll need.
 */
add_filter('manage_gwapi-recipient_posts_columns', function ($columns) {
    $date_text = $columns['date'];
    unset($columns['date']);

    return array_merge($columns, [
        'cc' => __('Country code', 'gwapi'),
        'mobile' => __('Mobile number', 'gwapi'),
        'date' => $date_text
    ]);
});


/**
 * Print the content for our custom columns.
 */
add_action('manage_posts_custom_column', function($column, $ID) {
    if (get_post_type($ID) != 'gwapi-recipient') return;

    switch($column) {
        case 'cc':
            echo esc_html('+'.get_post_meta($ID, 'cc', true) ? : '-');
            break;

        case 'mobile':
            echo wp_trim_words(esc_html(get_post_meta($ID, 'number', true) ? : '-'), 8);
            break;
    }

}, 10, 2);