<div class="wrap">
    <h2><?php _e('GatewayAPI Settings', 'gwapi'); ?></h2>

    <form method="post" action="options.php">
        <?php settings_fields( 'gwapi' ); ?>
        <?php do_settings_sections( 'gwapi' ); ?>

        <p>
            <?php $link = [':link' => '<a href="https://GatewayAPI.com" target="_blank"><strong>GatewayAPI.com</strong></a>']; ?>
            <?= strtr(__('Please enter your OAuth Key and OAuth Secret below. You find this information by logging into :link and then navigate to <strong>Settings » OAuth Keys</strong>.', 'gwapi'), $link); ?>
        </p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('OAuth Key', 'gwapi'); ?></th>
                <td><input type="text" name="gwapi_key" value="<?php echo esc_attr( get_option('gwapi_key') ); ?>" size="32" /></td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('OAuth Secret', 'gwapi'); ?></th>
                <td><input type="text" name="gwapi_secret" value="<?php echo esc_attr( get_option('gwapi_secret') ); ?>" size="64" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Enable sending UI', 'gwapi'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="gwapi_enable_ui" <?= get_option('gwapi_enable_ui') ? 'checked' : ''; ?>>
                        <?php _e('Yes, enable the SMS sending UI', 'gwapi'); ?>
                    </label>
                    <p class="help-block">
                        <?php _e('Enabling this adds a new menu for sending SMS\'es and listing sent SMS\'es, as well as managing an address book.', 'gwapi'); ?>

                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Default sender', 'gwapi'); ?></th>
                <td>
                    <label>
                        <input type="text" maxlength="15" name="gwapi_default_sender" value="<?= esc_attr(get_option('gwapi_default_sender')); ?>">
                    </label>
                    <p class="help-block">
                        <?php _e('Must consist of either 11 characters or 15 digits.', 'gwapi'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>

    </form>
</div>
