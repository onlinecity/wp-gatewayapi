<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

/**
 * Validation logic when adding/editing recipients.
 */
add_filter('gwapi_validate_recipient', '_gwapi_validate_recipient_basic', 10, 3);
add_filter('gwapi_validate_recipient', '_gwapi_validate_recipient_unique', 10, 3);


function _gwapi_validate_recipient_basic($errors, $data, WP_Post $post)
{
    // validate cc
    if (!$data['cc']) $errors['cc'] = __('No country code entered.', 'gatewayapi');
    if (!ctype_digit($data['cc'])) $errors['cc'] = __('The country code is invalid.', 'gatewayapi');

    // validate number
    if (!$data['number']) $errors['number'] = __('No phone number entered.', 'gatewayapi');

    // validate msisdn
    $msisdn = $data['cc'].$data['number'];
    if (!ctype_digit($msisdn)) $errors['number'] = __('The phone number is invalid.', 'gatewayapi');

    // let's just return the current errors first, before starting on the more expensive ones
    return $errors;
}

function _gwapi_validate_recipient_unique ($errors, $data, WP_Post $post) {
    // check for duplicates
    $dupCheck = new WP_Query([
        "post_type" => "gwapi-recipient",
        "post__not_in" => [ $post->ID ],
        "meta_query" => [
            [
                'key' => 'cc',
                'value' => $data['cc']
            ],
            [
                'key' => 'number',
                'value' => $data['number']
            ]
        ]
    ]);
    if ($dupCheck->have_posts()) $errors['*'] = __('There already exists a recipient with these details.', 'gatewayapi');

    return $errors;
}

function _gwapi_validate_sms($data)
{
    $errors = [];

    // sender
    if ($data['sender']) {
        if (ctype_digit($data['sender'])) {
            if (strlen($data['sender']) > 15) $errors['sender'] = __('Sender must be no longer than 15 characters when using digits.', 'gatewayapi');
        } else {
            if (strlen($data['sender']) > 11) $errors['sender'] = __('Sender must contain at most 11 characters (or 15 digits).', 'gatewayapi');
        }
    }

    return $errors;
}
