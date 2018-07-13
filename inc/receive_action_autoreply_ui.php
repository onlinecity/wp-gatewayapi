<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php
/**
 * SMS Autoreply Editor Block: The messaging area
 */
function _gwapi_receive_action_autoreply(WP_Post $post)
{
    $ID = $post->ID;
    ?>
    <div class="gwapi-star-errors"></div>
    <table width="100%" class="form-table">
        <tbody>
        <tr>
            <th width="25%">
                <?php _e('Sender', 'gatewayapi'); ?>
            </th>
            <td>
                <input type="text" name="gatewayapi[sender]" size="15"
                    value="<?= esc_attr(get_post_meta($ID, 'sender', true)); ?>">
                <p class="description"><?php _e('The sender can be either 11 characters or 15 digits in total.', 'gatewayapi'); ?></p>
            </td>
        </tr>
        <tr>
            <th>
                <?php _e('Message', 'gatewayapi') ?>
            </th>
            <td>
                <?php
                $counterI18N = [
                    'character' => __('character', 'gatewayapi'),
                    'characters' => __('characters', 'gatewayapi'),
                    'sms' => __('SMS', 'gatewayapi'),
                    'smses' => __('SMS\'es', 'gatewayapi')
                ];
                ?>
                <textarea name="gatewayapi[message]" rows="10" style="width: 100%"
                    placeholder="<?= esc_attr(__('Enter your SMS message here.', 'gatewayapi')); ?>" data-counter-i18n="<?= esc_attr(json_encode($counterI18N)); ?>"><?= esc_attr(get_post_meta($ID, 'message', true)); ?>
                </textarea>
            </td>
        </tr>
        </tbody>
    </table>
    <?php
}