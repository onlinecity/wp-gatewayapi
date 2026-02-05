<?php
if (!defined('ABSPATH')) {
    die('Cannot be accessed directly!');
}

/**
 * Helper: Get MSISDN from CC and Number
 */
function gatewayapi__get_msisdn($cc, $number)
{
    $cc = preg_replace('/\D+/', '', $cc);
    $number = preg_replace('/\D+/', '', $number);
    if (!$cc || !$number) return false;
    return $cc . $number;
}

/**
 * Helper: Find recipient by CC and Number
 */
function gatewayapi__get_recipient_by_phone($cc, $number)
{
    $args = [
        'post_type' => 'gwapi-recipient',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'cc',
                'value' => $cc,
                'compare' => '='
            ],
            [
                'key' => 'number',
                'value' => $number,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1,
        'post_status' => 'any'
    ];
    
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        return $query->posts[0];
    }
    return null;
}

/**
 * Helper: Check rate limit for a phone number
 * 5 times per hour.
 */
function gatewayapi__check_rate_limit($msisdn)
{
    $limit = 5;
    $window = HOUR_IN_SECONDS;
    $key = 'gwapi_rl_' . $msisdn;
    $data = get_transient($key);

    if ($data === false) {
        $data = [
            'count' => 1,
            'expires' => time() + $window
        ];
        set_transient($key, $data, $window);
        return true;
    }

    if ($data['count'] >= $limit) {
        return new WP_Error('rate_limit_exceeded', 'You have reached the maximum number of attempts for this phone number. Please try again in an hour.');
    }

    $data['count']++;
    $remaining = $data['expires'] - time();
    if ($remaining > 0) {
        set_transient($key, $data, $remaining);
    } else {
        // Should have expired, but for safety:
        $data = [
            'count' => 1,
            'expires' => time() + $window
        ];
        set_transient($key, $data, $window);
    }

    return true;
}

/**
 * Helper: Reset rate limit for a phone number
 */
function gatewayapi__reset_rate_limit($msisdn)
{
    delete_transient('gwapi_rl_' . $msisdn);
}

/**
 * Helper: Load countries from JSON
 */
function gatewayapi__get_countries()
{
    static $countries = null;
    if ($countries !== null) return $countries;

    $json_file = plugin_dir_path(__FILE__) . '../countries.json';
    if (!file_exists($json_file)) return [];

    $json_data = file_get_contents($json_file);
    $data = json_decode($json_data, true);
    if (!$data || !isset($data['countries'])) return [];

    $countries = $data['countries'];
    return $countries;
}
