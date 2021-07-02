<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

$ID = $post->ID;
$published = $post->post_status == 'publish';

?>
<div class="gwapi-star-errors"></div>
<table width="100%"
       class="form-table">
  <tbody>
  <tr>
    <th width="25%">
        <?php _e('Sender', 'gatewayapi'); ?>
    </th>
    <td>
      <input type="text"
             name="gatewayapi[sender]"
             size="15"
             value="<?php echo esc_attr(get_post_meta($ID, 'sender', true)); ?>">
      <p
        class="description"><?php _e('The sender can be either 11 characters or 15 digits in total.', 'gatewayapi'); ?></p>
    </td>
  </tr>
  <tr>
    <th width="25%"
        class="vtop-5">
        <?php _e('Type of SMS', 'gatewayapi'); ?>
    </th>
    <td>
        <?php $destaddr = get_post_meta($ID, 'destaddr', true); ?>
      <label>
        <input type="radio"
               name="gatewayapi[destaddr]"
               value="MOBILE" <?php echo ($destaddr == 'MOBILE' || !$destaddr) ? 'checked' : ''; ?>>
          <?php _e('Regular SMS', 'gatewayapi'); ?>
      </label>
      <br/>
      <label>
        <input type="radio"
               name="gatewayapi[destaddr]"
               value="DISPLAY"<?php echo $destaddr == 'DISPLAY' ? 'checked' : ''; ?>>
        <abbr
          title="<?php esc_attr_e('Message is displayed immediately and usually not saved in the normal message inbox. Also knows as a Flash SMS.', 'gatewayapi'); ?>">
          <?php _e('Display SMS', 'gatewayapi'); ?></abbr>
      </label>
    </td>
  </tr>
  <tr>
    <th width="25%"
        class="vtop-5">
        <?php _e('Encoding', 'gatewayapi'); ?><br/>
      <a href="https://gatewayapi.com/docs/appendix.html#term-mcc"><small><?php _e('More information', 'gatewayapi'); ?></small></a>
    </th>
    <td>
        <?php $destaddr = get_post_meta($ID, 'encoding', true); ?>

      <table cellspacing="0"
             cellpadding="0"
             border="0"
             class="tiny-padding-table">
        <tr data-encoding="GSM0338">
          <td>
            <label>
              <input type="radio"
                     name="gatewayapi[encoding]"
                     value="GSM0338" <?php echo ($destaddr === 'GSM0338' || !$destaddr) ? 'checked' : ''; ?>>
              <abbr title="<?php esc_attr_e('160 characters for 1-page SMS. 153 characters for multi-page SMS. Limited special characters, no emoji-support.', 'gatewayapi'); ?>"><?php _e('Default',
                    'gatewayapi'); ?></abbr>
            </label>
          </td>
          <td>
            <span class="GSM0338-recommended"
                  title="<?php esc_attr_e('All characters in the message are within GSM 03.38.',
                    'gatewayapi'); ?> <?php esc_attr_e('You should use GSM 03.38, as it roughly doubles the characters available per page over UCS2.', 'gatewayapi'); ?>">✅</span>
            <span class="UCS2-recommended hidden"
                  title="<?php esc_attr_e('There are characters beyond GSM 03.38. These will not be properly displayed.', 'gatewayapi'); ?>">🛑</span>
          </td>
          <td>
            <a class="UCS2-recommended hidden"
               href="#gwapi-show-invalid-chars"
               data-pretext="<?php esc_attr_e('The following characters are not valid GSM 03.38 characters:', 'gatewayapi'); ?>"><?php _e('Show invalid characters', 'gatewayapi'); ?></a>
          </td>
        </tr>
        <tr data-encoding="UCS2">
          <td>
            <label>
              <input type="radio"
                     name="gatewayapi[encoding]"
                     value="UCS2" <?php echo ($destaddr === 'UCS2') ? 'checked' : ''; ?>>
              <abbr title="<?php esc_attr_e('70 characters for 1-page SMS. 67 characters for multi-page SMS. Supports most special characters and emojis.',
                'gatewayapi'); ?>"><?php _e('Special characters', 'gatewayapi'); ?></abbr>
            </label>
          </td>
          <td>
            <span class="UCS2-recommended hidden"
                  title="<?php esc_attr_e('Some special characters used require this encoding to be displayed properly.', 'gatewayapi'); ?>">✅</span>
            <span class="GSM0338-recommended"
                  title="<?php esc_attr_e('All characters in SMS are within GSM 03.38.',
                    'gatewayapi'); ?> <?php esc_attr_e('You should use GSM 03.38, as it roughly doubles the characters available per page over UCS2.', 'gatewayapi'); ?>">🛑</span>
          </td>
          <td></td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <th>
        <?php _e('Message', 'gatewayapi') ?>
    </th>
    <td>
        <?php
        $counterI18N = [
          'character'  => __('character', 'gatewayapi'),
          'characters' => __('characters', 'gatewayapi'),
          'sms'        => __('SMS', 'gatewayapi'),
          'smses'      => __('SMS\'es', 'gatewayapi'),
        ];
        ?>
      <textarea name="gatewayapi[message]"
                rows="10"
                style="width: 100%"
                placeholder="<?php esc_attr_e(__('Enter your SMS message here.', 'gatewayapi')); ?>"
                data-counter-i18n="<?php echo esc_attr(json_encode($counterI18N)); ?>"><?php echo esc_attr(get_post_meta($ID, 'message', true));
                ?></textarea>
      <br>
      <div>
        <p><?php _e('Writing one of the following tags (including both &percnt;-signs) will result in each recipient receiving a personalized text:', 'gatewayapi'); ?></p>
        <ul>
            <?php foreach (gatewayapi__all_tags() as $tag => $description): ?>
              <li>
                <strong><?php echo esc_html($tag); ?></strong> - <?php echo esc_html($description); ?>
              </li>
            <?php endforeach; ?>
        </ul>
      </div>
    </td>
  </tr>
  </tbody>
</table>
