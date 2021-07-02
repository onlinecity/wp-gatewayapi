<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php login_header(__('Register mobile phone number', 'gatewayapi')); ?>

    <div class="step current">

        <p class="message">
            <?php _e('Please add your cellphone number below, so we can send you a one-time verification code.<br />This is mandatory, but free of charge to you.', 'gatewayapi'); ?>
            <br/>
        </p>

        <form method="post" id="gwapi_add_phone_form">

            <input type="hidden" name="gwapi_2f_tmp" value="<?php echo esc_attr(GwapiSecurityTwoFactor::$TMP_TOKEN); ?>">

            <p>
                <label for="mcc"><?php _e('Mobile country code', 'gatewayapi'); ?></label><br>
                <select name="mcc" id="gwapi_mcc" data-gwapi-mobile-cc style="width: 100%"
                        class="input"></select>
            </p>

            <p>
                <label for="gwapi_mno"><?php _e('Mobile number', 'gatewayapi'); ?><br>
                    <input type="number" name="mno" id="gwapi_mno" class="input" value="" size="20">
                </label>
            </p>

            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large"
                       value="<?php esc_attr_e('Verify phone', 'gatewayapi'); ?>" data-loading="<?php esc_attr_e('Sending SMS...', 'gatewayapi'); ?>">
            </p>
        </form>
    </div>


<?php login_footer(); ?>
