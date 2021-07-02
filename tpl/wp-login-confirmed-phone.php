<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>

<p class="message">
    <?php _e('Success! Your mobile is now connected to your profile, which helps secure the integrity of your account and this website.', 'gatewayapi'); ?>
    <br/><br/>
    <strong><?php _e('You have also been logged in 😄', 'gatewayapi'); ?></strong>
    <br><br>

    <span class="submit">
        <a href="<?php echo esc_attr($redirect_to); ?>" class="button button-primary button-large"><?php _e('Continue', 'gatewayapi'); ?></a>
    </span>
    <br>
    <br>
</p>

