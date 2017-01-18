<?php

/**
 * Receive SMS UI
 */
add_action('admin_init', function () {
    add_meta_box('receive-sms-meta', __('Received SMS', 'gwapi'), '_gwapi_receive_sms_box', 'gwapi-receive-sms', 'normal', 'default');
});

/**
 * Receive SMS UI Block: Show meta information for the received sms
 */
function _gwapi_receive_sms_box(WP_Post $post)
{
    $metas = [
        'id' => 'The ID of the MO SMS',
        'msisdn' => 'The MSISDN of the mobile device who sent the SMS.',
        'receiver' => 'The short code on which the SMS was received.',
        'message' => 'The body of the SMS, incl. keyword.',
        'senttime' => 'The UNIX Timestamp when the SMS was sent.',
        'webhook_label' => 'Label of the webhook who matched the SMS.',
        'sender' => 'If SMS was sent with a text based sender, then this field is set. Optional.',
        'mcc' => 'MCC, mobile country code. Optional.',
        'mnc' => 'MNC, mobile network code. Optional.',
        'validity_period' => 'How long the SMS is valid. Optional.',
        'encoding' => 'Encoding of the received SMS. Optional.',
        'udh' => 'User data header of the received SMS. Optional.',
        'payload' => 'Binary payload of the received SMS. Optional.'
    ];
    ?>
    <table width="100%" class="form-table gwapi-receive-sms-table">
        <tbody>
        <?php foreach ($metas as $key => $description): ?>
            <tr>
                <th width="25%">
                    <abbr title="<?php echo esc_attr(__($description, 'gwapi')); ?>"><?php echo $key; ?></abbr>
                </th>
                <td>
                    <?php
                    $value = get_post_meta($post->ID, $key, true);
                    if ($value && in_array($key, ['senttime'])) {
                        $value = esc_attr(date_i18n(get_option('date_format'), $value) . ' @ ' . date_i18n(get_option('time_format'), $value));
                    }
                    echo nl2br(esc_attr($value) ?: '-');
                    ?>
                </td>
            </tr>

        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
