<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

/**
 * Two-factor authentication logic for GatewayAPI
 */

// Include 2FA logic when initialized
add_action('init', function () {
    // Register AJAX handlers for 2FA settings
    add_action('wp_ajax_gatewayapi_save_2fa_settings', 'gatewayapi_save_2fa_settings');
    add_action('wp_ajax_gatewayapi_get_2fa_settings', 'gatewayapi_get_2fa_settings');

    // Intercept login process
    add_filter('authenticate', 'gatewayapi_2fa_authenticate', 100, 3);
    add_action('login_form_gatewayapi_2fa', 'gatewayapi_2fa_login_form_handler');
    add_action('wp_login_errors', 'gatewayapi_2fa_login_errors');
    add_action('wp_ajax_gatewayapi_2fa_verify', 'gatewayapi_2fa_profile_verify');

    // Profile page integration
    add_action('show_user_profile', 'gatewayapi_2fa_user_profile_fields');
    add_action('edit_user_profile', 'gatewayapi_2fa_user_profile_fields');

    // Admin notice for mandatory 2FA
    add_action('admin_notices', 'gatewayapi_2fa_admin_notice');
});

/**
 * Show admin notice if 2FA is required but not configured
 */
function gatewayapi_2fa_admin_notice() {
    // Only show on dashboard and profile page
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, ['dashboard', 'profile', 'user-edit'])) {
        return;
    }

    if (!get_option('gwapi_2fa_enabled')) {
        return;
    }

    $user = wp_get_current_user();
    if (!$user || !$user->ID) {
        return;
    }

    // Check if user role requires 2FA
    $required_roles = get_option('gwapi_2fa_required_roles', ['administrator', 'editor']);
    $user_roles = (array)$user->roles;
    $requires_2fa = false;
    foreach ($user_roles as $role) {
        if (in_array($role, $required_roles)) {
            $requires_2fa = true;
            break;
        }
    }

    if (!$requires_2fa) {
        return;
    }

    // Check if 2FA is already enabled for the user
    if (get_user_meta($user->ID, 'gwapi_2fa_enabled', true)) {
        return;
    }

    // Get grace period
    $grace_period = get_option('gwapi_2fa_grace_period');
    if (!$grace_period) {
        return;
    }

    $grace_timestamp = strtotime($grace_period);
    $profile_url = admin_url('profile.php#gwapi_2fa_phone');
    $is_past_grace = time() >= $grace_timestamp;

    if ($is_past_grace) {
        $message = sprintf(__('You must enable two-factor authentication immediately or risk being unable to login.', 'gatewayapi'));
    } else {
        $formatted_date = date_i18n(get_option('date_format'), $grace_timestamp);
        $message = sprintf(__('You must enable two-factor authentication before %s or risk being unable to login.', 'gatewayapi'), '<strong>' . $formatted_date . '</strong>');
    }

    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <strong><?php _e('Action Required:', 'gatewayapi'); ?></strong>
            <?php echo $message; ?>
            <a href="<?php echo esc_url($profile_url); ?>"><?php _e('Configure Two-Factor Authentication now', 'gatewayapi'); ?></a>.
        </p>
    </div>
    <?php
}

/**
 * Get 2FA settings
 */
function gatewayapi_get_2fa_settings() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $enabled = get_option('gwapi_2fa_enabled', '0');
    $grace_period = get_option('gwapi_2fa_grace_period', date('Y-m-d', strtotime('+2 weeks')));
    $allowed_countries = get_option('gwapi_2fa_allowed_countries', []);
    $remember_duration = get_option('gwapi_2fa_remember_duration', '0');
    $required_roles = get_option('gwapi_2fa_required_roles', ['administrator', 'editor']);

    wp_send_json_success([
        'enabled' => $enabled === '1',
        'grace_period' => $grace_period,
        'allowed_countries' => $allowed_countries,
        'remember_duration' => $remember_duration,
        'required_roles' => $required_roles,
        'all_roles' => wp_roles()->get_names()
    ]);
}

/**
 * Save 2FA settings
 */
function gatewayapi_save_2fa_settings() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true' ? '1' : '0';
    $grace_period = isset($_POST['grace_period']) ? sanitize_text_field($_POST['grace_period']) : '';
    $allowed_countries = isset($_POST['allowed_countries']) ? (array)$_POST['allowed_countries'] : [];
    $remember_duration = isset($_POST['remember_duration']) ? sanitize_text_field($_POST['remember_duration']) : '0';
    $required_roles = isset($_POST['required_roles']) ? (array)$_POST['required_roles'] : [];

    update_option('gwapi_2fa_enabled', $enabled);
    update_option('gwapi_2fa_grace_period', $grace_period);
    update_option('gwapi_2fa_allowed_countries', array_map('sanitize_text_field', $allowed_countries));
    update_option('gwapi_2fa_remember_duration', $remember_duration);
    update_option('gwapi_2fa_required_roles', array_map('sanitize_text_field', $required_roles));

    wp_send_json_success(['message' => '2FA settings saved successfully']);
}

/**
 * Intercept authentication to check for 2FA
 */
function gatewayapi_2fa_authenticate($user, $username, $password) {
    if (is_wp_error($user) || !get_option('gwapi_2fa_enabled')) {
        return $user;
    }

    // Check if user role requires 2FA
    $required_roles = get_option('gwapi_2fa_required_roles', ['administrator', 'editor']);
    $user_roles = (array)$user->roles;
    $requires_2fa = false;
    foreach ($user_roles as $role) {
        if (in_array($role, $required_roles)) {
            $requires_2fa = true;
            break;
        }
    }

    if (!$requires_2fa) {
        return $user;
    }

    // Check grace period
    $grace_period = get_option('gwapi_2fa_grace_period');
    if ($grace_period && time() < strtotime($grace_period)) {
        return $user;
    }

    // Check if 2FA is remembered for this user
    if (gatewayapi_2fa_is_remembered($user->ID)) {
        return $user;
    }

    // If we reach here, 2FA is required.
    // Use a temporary cookie to store the fact that this user is partially authenticated
    $token = wp_generate_password(32, false);
    set_transient('gwapi_2fa_pending_' . $token, $user->ID, 15 * MINUTE_IN_SECONDS);
    
    // Set a cookie for the 2FA flow
    setcookie('gwapi_2fa_pending', $token, time() + 15 * MINUTE_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

    // Redirect to verification page
    wp_redirect(add_query_arg('action', 'gatewayapi_2fa', wp_login_url()));
    exit;
}

/**
 * Handle the 2FA verification form
 */
function gatewayapi_2fa_login_form_handler() {
    $token = isset($_COOKIE['gwapi_2fa_pending']) ? $_COOKIE['gwapi_2fa_pending'] : '';
    if (!$token) {
        wp_redirect(wp_login_url());
        exit;
    }

    $user_id = get_transient('gwapi_2fa_pending_' . $token);
    if (!$user_id) {
        wp_redirect(wp_login_url());
        exit;
    }

    $user = get_userdata($user_id);
    if (!$user) {
        wp_redirect(wp_login_url());
        exit;
    }

    // Double check if user role still requires 2FA
    $required_roles = get_option('gwapi_2fa_required_roles', ['administrator', 'editor']);
    $user_roles = (array)$user->roles;
    $requires_2fa = false;
    foreach ($user_roles as $role) {
        if (in_array($role, $required_roles)) {
            $requires_2fa = true;
            break;
        }
    }

    if (!$requires_2fa) {
        // Not required anymore, just log them in
        delete_transient('gwapi_2fa_pending_' . $token);
        setcookie('gwapi_2fa_pending', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        wp_set_auth_cookie($user_id, true);
        wp_redirect(admin_url());
        exit;
    }

    $phone = get_user_meta($user_id, 'gwapi_2fa_phone', true);
    $is_enabled = get_user_meta($user_id, 'gwapi_2fa_enabled', true);

    // Handle form submission
    if (isset($_POST['gwapi_2fa_code'])) {
        $code = preg_replace('/\D/', '', sanitize_text_field($_POST['gwapi_2fa_code']));
        $stored_code = get_transient('gwapi_2fa_code_' . $user_id);

        if ($code && $code == $stored_code) {
            // Success!
            delete_transient('gwapi_2fa_code_' . $user_id);
            delete_transient('gwapi_2fa_pending_' . $token);
            setcookie('gwapi_2fa_pending', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);

            if (!$is_enabled) {
                update_user_meta($user_id, 'gwapi_2fa_enabled', '1');
            }

            // Remember me?
            if (isset($_POST['gwapi_2fa_remember'])) {
                $duration_opt = get_option('gwapi_2fa_remember_duration', '0');
                $durations = [
                    'session' => 0,
                    '1day' => DAY_IN_SECONDS,
                    '7days' => 7 * DAY_IN_SECONDS,
                    '15days' => 15 * DAY_IN_SECONDS,
                    '1month' => 30 * DAY_IN_SECONDS
                ];
                $seconds = isset($durations[$duration_opt]) ? $durations[$duration_opt] : 0;
                
                $remember_token = wp_generate_password(32, false);
                $cookie_name = 'gwapi_2fa_remember_' . COOKIEHASH;
                setcookie($cookie_name, $remember_token, $seconds ? time() + $seconds : 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                set_transient('gwapi_2fa_token_' . $remember_token, $user_id, $seconds ?: 0);
            }

            wp_set_auth_cookie($user_id, true);
            $redirect_to = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : admin_url();
            wp_redirect($redirect_to);
            exit;
        } else {
            login_header(__('Two-Factor Authentication', 'gatewayapi'), '', new WP_Error('gwapi_2fa_error', __('Invalid verification code.', 'gatewayapi')));
        }
    } elseif (isset($_POST['gwapi_2fa_phone_setup'])) {
        // First time setup: set phone number and send SMS
        $new_phone = preg_replace('/\D/', '', sanitize_text_field($_POST['gwapi_2fa_phone_setup']));
        
        // Check if country is allowed
        $allowed_countries = get_option('gwapi_2fa_allowed_countries', []);
        if (!empty($allowed_countries)) {
            $is_allowed = false;
            $countries_json = file_get_contents(plugin_dir_path(__DIR__) . 'countries.json');
            $countries_data = json_decode($countries_json, true);
            
            foreach ($allowed_countries as $country_code) {
                $prefix = isset($countries_data['countries'][$country_code]['phone']) ? $countries_data['countries'][$country_code]['phone'] : '';
                if ($prefix && strpos($new_phone, $prefix) === 0) {
                    $is_allowed = true;
                    break;
                }
            }
            if (!$is_allowed) {
                gatewayapi_2fa_render_form($user, $phone, $is_enabled, new WP_Error('gwapi_2fa_error', sprintf(__('%s origin is not allowed.', 'gatewayapi'), esc_html($new_phone))));
                exit;
            }
        }

        update_user_meta($user_id, 'gwapi_2fa_phone', $new_phone);
        $phone = $new_phone;
        gatewayapi_2fa_send_code($user_id, $phone);
        gatewayapi_2fa_render_form($user, $phone, $is_enabled);
        exit;
    }

    // Initial load of 2FA page
    if ($is_enabled && $phone) {
        gatewayapi_2fa_send_code($user_id, $phone);
    }
    
    gatewayapi_2fa_render_form($user, $phone, $is_enabled);
    exit;
}

/**
 * Render the 2FA form
 */
function gatewayapi_2fa_render_form($user, $phone, $is_enabled, $error = null, $message = null) {
    login_header(__('Two-Factor Authentication', 'gatewayapi'), '', $error);
    
    if ($message) {
        echo '<p class="message">' . esc_html($message) . '</p>';
    }

    if (!$is_enabled && !$phone): ?>
        <p><?php _e('Two-factor authentication is required for your account. Please enter your phone number to receive a verification code.', 'gatewayapi'); ?></p>
        <form method="post" action="<?php echo esc_url(add_query_arg('action', 'gatewayapi_2fa', wp_login_url())); ?>">
            <p>
                <label for="gwapi_2fa_phone_setup"><?php _e('Phone Number (MSISDN)', 'gatewayapi'); ?><br />
                <input type="text" name="gwapi_2fa_phone_setup" id="gwapi_2fa_phone_setup" class="input" value="" size="20" placeholder="e.g. 4512345678" /></label>
            </p>
            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Send Verification Code', 'gatewayapi'); ?>" />
            </p>
        </form>
    <?php else: ?>
        <p><?php printf(__('A verification code has been sent to your phone ending in %s.', 'gatewayapi'), esc_html(substr($phone, -4))); ?></p>
        <form method="post" action="<?php echo esc_url(add_query_arg('action', 'gatewayapi_2fa', wp_login_url())); ?>">
            <p>
                <label for="gwapi_2fa_code"><?php _e('Verification Code', 'gatewayapi'); ?><br />
                <input type="text" name="gwapi_2fa_code" id="gwapi_2fa_code" class="input" value="" size="20" /></label>
            </p>
            <?php if (get_option('gwapi_2fa_remember_duration', '0') !== 'none'): ?>
            <p>
                <label><input type="checkbox" name="gwapi_2fa_remember" value="1" /> <?php _e('Remember me on this device', 'gatewayapi'); ?></label>
            </p>
            <?php endif; ?>
            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Verify', 'gatewayapi'); ?>" />
            </p>
        </form>
    <?php endif; ?>
    <?php
    login_footer();
}

/**
 * Send 2FA code via SMS
 */
function gatewayapi_2fa_send_code($user_id, $phone) {
    if (empty($phone)) return false;

    // Rate limiting: 10 times per hour per phone number
    $rate_limit_key = 'gwapi_2fa_rate_' . $phone;
    $attempts = get_transient($rate_limit_key) ?: 0;
    if ($attempts >= 10) {
        return new WP_Error('rate_limit', __('Too many attempts. Please try again later.', 'gatewayapi'));
    }

    $code = sprintf('%08d', mt_rand(0, 99999999));
    set_transient('gwapi_2fa_code_' . $user_id, $code, 15 * MINUTE_IN_SECONDS);

    // Format code with spaces for readability: 12 34 56 78
    $formatted_code = implode(' ', str_split($code, 2));
    $message = sprintf(__('Your verification code is: %s', 'gatewayapi'), $formatted_code);
    $sender = get_option('gwapi_default_sender', 'Auth');
    
    $result = gatewayapi_send_mobile_message($message, $phone, $sender);
    
    if (!is_wp_error($result)) {
        set_transient($rate_limit_key, $attempts + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    return $result;
}

/**
 * Empty errors on login page
 */
function gatewayapi_2fa_login_errors($errors) {
    return $errors;
}

/**
 * Handle verification from profile page
 */
function gatewayapi_2fa_profile_verify() {
    if (!check_ajax_referer('gwapi_2fa_verify', 'nonce', false)) {
        wp_send_json_error(['message' => __('Invalid nonce', 'gatewayapi')]);
    }

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error(['message' => __('Not logged in', 'gatewayapi')]);
    }

    $target_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : $current_user_id;
    if (!$target_user_id) {
        $target_user_id = $current_user_id;
    }

    $user = get_userdata($target_user_id);
    if (!$user) {
        wp_send_json_error(['message' => __('User not found', 'gatewayapi')]);
    }

    $step = isset($_POST['step']) ? $_POST['step'] : '';

    // Permission checks
    if ($step === 'remove') {
        // Only self or administrators can remove phone number
        if ($current_user_id !== $target_user_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to remove this phone number.', 'gatewayapi')]);
        }
    } else {
        // Only the user themselves can change/add phone number
        if ($current_user_id !== $target_user_id) {
            wp_send_json_error(['message' => __('Only the user can change their own phone number.', 'gatewayapi')]);
        }
    }

    // Check if user role requires 2FA
    $required_roles = get_option('gwapi_2fa_required_roles', ['administrator', 'editor']);
    $user_roles = (array)$user->roles;
    $requires_2fa = false;
    foreach ($user_roles as $role) {
        if (in_array($role, $required_roles)) {
            $requires_2fa = true;
            break;
        }
    }

    if (!$requires_2fa) {
        wp_send_json_error(['message' => __('Two-factor authentication is not required for this user role.', 'gatewayapi')]);
    }

    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : get_user_meta($target_user_id, 'gwapi_2fa_phone', true);

    if ($step === 'send') {
        if (!$phone) {
            wp_send_json_error(['message' => __('No phone number set', 'gatewayapi')]);
        }

        // Sanitize phone number (digits only)
        $phone = preg_replace('/\D/', '', $phone);

        // Check if user has reached the maximum number of phone changes for today
        $changes_today = get_user_meta($target_user_id, 'gwapi_2fa_phone_changes_today', true) ?: 0;
        $last_change_date = get_user_meta($target_user_id, 'gwapi_2fa_phone_last_change_date', true);
        $today = date('Y-m-d');

        if ($last_change_date === $today && $changes_today >= 2) {
            wp_send_json_error(['message' => __('You have reached the maximum number of phone number changes for today.', 'gatewayapi')]);
        }

        // Check if country is allowed
        $allowed_countries = get_option('gwapi_2fa_allowed_countries', []);
        if (!empty($allowed_countries)) {
            $is_allowed = false;
            $countries_json = file_get_contents(plugin_dir_path(__DIR__) . 'countries.json');
            $countries_data = json_decode($countries_json, true);

            foreach ($allowed_countries as $country_code) {
                $prefix = isset($countries_data['countries'][$country_code]['phone']) ? $countries_data['countries'][$country_code]['phone'] : '';
                if ($prefix && strpos($phone, $prefix) === 0) {
                    $is_allowed = true;
                    break;
                }
            }
            if (!$is_allowed) {
                wp_send_json_error(['message' => sprintf(__('The country of %s is not allowed.', 'gatewayapi'), esc_html($phone))]);
            }
        }

        // Store the pending phone number for verification
        set_transient('gwapi_2fa_pending_phone_' . $target_user_id, $phone, 15 * MINUTE_IN_SECONDS);

        $res = gatewayapi_2fa_send_code($target_user_id, $phone);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }
        wp_send_json_success(['message' => __('Code sent!', 'gatewayapi')]);
    } elseif ($step === 'verify') {
        $code = isset($_POST['code']) ? preg_replace('/\D/', '', sanitize_text_field($_POST['code'])) : '';
        $stored_code = get_transient('gwapi_2fa_code_' . $target_user_id);

        if ($code && $code == $stored_code) {
            delete_transient('gwapi_2fa_code_' . $target_user_id);
            update_user_meta($target_user_id, 'gwapi_2fa_enabled', '1');
            wp_send_json_success(['message' => __('Verified!', 'gatewayapi')]);
        } else {
            wp_send_json_error(['message' => __('Invalid code', 'gatewayapi')]);
        }
    } elseif ($step === 'verify_and_save') {
        // Verify code and save phone number in one step (for modal workflow)
        $code = isset($_POST['code']) ? preg_replace('/\D/', '', sanitize_text_field($_POST['code'])) : '';
        $new_phone = isset($_POST['phone']) ? preg_replace('/\D/', '', sanitize_text_field($_POST['phone'])) : '';
        $stored_code = get_transient('gwapi_2fa_code_' . $target_user_id);

        if (!$code || $code != $stored_code) {
            wp_send_json_error(['message' => __('Invalid code', 'gatewayapi')]);
        }

        if (!$new_phone) {
            wp_send_json_error(['message' => __('No phone number provided', 'gatewayapi')]);
        }

        // Rate limit phone number changes: at most twice per day
        $changes_today = get_user_meta($target_user_id, 'gwapi_2fa_phone_changes_today', true) ?: 0;
        $last_change_date = get_user_meta($target_user_id, 'gwapi_2fa_phone_last_change_date', true);
        $today = date('Y-m-d');

        if ($last_change_date === $today) {
            if ($changes_today >= 2) {
                wp_send_json_error(['message' => __('You have reached the maximum number of phone number changes for today.', 'gatewayapi')]);
            }
            $changes_today++;
        } else {
            $changes_today = 1;
            update_user_meta($target_user_id, 'gwapi_2fa_phone_last_change_date', $today);
        }
        update_user_meta($target_user_id, 'gwapi_2fa_phone_changes_today', $changes_today);

        // Save the phone number and enable 2FA
        delete_transient('gwapi_2fa_code_' . $target_user_id);
        delete_transient('gwapi_2fa_pending_phone_' . $target_user_id);
        update_user_meta($target_user_id, 'gwapi_2fa_phone', $new_phone);
        update_user_meta($target_user_id, 'gwapi_2fa_enabled', '1');

        wp_send_json_success(['message' => __('Phone number verified and saved!', 'gatewayapi')]);
    } elseif ($step === 'remove') {
        // Remove phone number and disable 2FA
        delete_user_meta($target_user_id, 'gwapi_2fa_phone');
        delete_user_meta($target_user_id, 'gwapi_2fa_enabled');
        delete_transient('gwapi_2fa_code_' . $target_user_id);
        delete_transient('gwapi_2fa_pending_phone_' . $target_user_id);

        wp_send_json_success(['message' => __('Phone number removed.', 'gatewayapi')]);
    }

    wp_send_json_error(['message' => __('Invalid step', 'gatewayapi')]);
}

/**
 * Check if 2FA is remembered for the user
 */
function gatewayapi_2fa_is_remembered($user_id) {
    $cookie_name = 'gwapi_2fa_remember_' . COOKIEHASH;
    if (!isset($_COOKIE[$cookie_name])) {
        return false;
    }

    $token = $_COOKIE[$cookie_name];
    $transient_name = 'gwapi_2fa_token_' . $token;
    $stored_user_id = get_transient($transient_name);

    return $stored_user_id == $user_id;
}

/**
 * Add 2FA fields to user profile
 */
function gatewayapi_2fa_user_profile_fields($user) {
    if (!get_option('gwapi_2fa_enabled')) {
        return;
    }

    // Check if user role requires 2FA
    $required_roles = get_option('gwapi_2fa_required_roles', ['administrator', 'editor']);
    $user_roles = (array)$user->roles;
    $requires_2fa = false;
    foreach ($user_roles as $role) {
        if (in_array($role, $required_roles)) {
            $requires_2fa = true;
            break;
        }
    }

    if (!$requires_2fa) {
        return;
    }

    $phone = get_user_meta($user->ID, 'gwapi_2fa_phone', true);
    $enabled = get_user_meta($user->ID, 'gwapi_2fa_enabled', true);
    $current_user_id = get_current_user_id();
    $is_self = $current_user_id === $user->ID;
    $is_admin = current_user_can('manage_options');
    ?>
    <style>
        #gwapi-2fa-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 100000;
        }
        #gwapi-2fa-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 100001;
            min-width: 350px;
            max-width: 450px;
        }
        #gwapi-2fa-modal h3 {
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        #gwapi-2fa-modal .gwapi-modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }
        #gwapi-2fa-modal .gwapi-modal-close:hover {
            color: #000;
        }
        #gwapi-2fa-modal input[type="text"] {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
        #gwapi-2fa-modal .gwapi-modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        #gwapi-2fa-modal .gwapi-modal-msg {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
        }
        #gwapi-2fa-modal .gwapi-modal-msg.error {
            background: #fef0f0;
            color: #c00;
            border: 1px solid #fcc;
        }
        #gwapi-2fa-modal .gwapi-modal-msg.success {
            background: #f0fef0;
            color: #080;
            border: 1px solid #cfc;
        }
        #gwapi-2fa-modal .gwapi-modal-msg.info {
            background: #f0f0fe;
            color: #008;
            border: 1px solid #ccf;
        }
        .gwapi-2fa-phone-display {
            font-size: 14px;
            font-family: monospace;
            background: #f5f5f5;
            padding: 8px 12px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 10px;
        }
        .gwapi-2fa-buttons {
            margin-top: 10px;
        }
        .gwapi-2fa-buttons .button {
            margin-right: 5px;
        }
        #gwapi-2fa-step-phone, #gwapi-2fa-step-code {
            display: none;
        }
    </style>

    <h3><?php _e("GatewayAPI Two-Factor Authentication", "gatewayapi"); ?></h3>
    <table class="form-table">
        <tr>
            <th><?php _e("Phone Number", "gatewayapi"); ?></th>
            <td id="gwapi_2fa_phone_section">
                <?php if ($phone): ?>
                    <span class="gwapi-2fa-phone-display" id="gwapi-2fa-current-phone"><?php echo esc_html($phone); ?></span>
                    <?php if ($enabled): ?>
                        <span style="color: green; font-weight: bold;">✓ <?php _e("Verified", "gatewayapi"); ?></span>
                    <?php else: ?>
                        <span style="color: orange;">⚠ <?php _e("Not verified", "gatewayapi"); ?></span>
                    <?php endif; ?>
                    <div class="gwapi-2fa-buttons">
                        <?php if ($is_self): ?>
                            <button type="button" class="button" id="gwapi-2fa-change-btn"><?php _e("Change", "gatewayapi"); ?></button>
                        <?php endif; ?>
                        <?php if ($is_self || $is_admin): ?>
                            <button type="button" class="button" id="gwapi-2fa-remove-btn"><?php _e("Remove", "gatewayapi"); ?></button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <span id="gwapi-2fa-no-phone"><?php _e("No phone number configured.", "gatewayapi"); ?></span>
                    <?php if ($is_self): ?>
                        <div class="gwapi-2fa-buttons">
                            <button type="button" class="button button-primary" id="gwapi-2fa-add-btn"><?php _e("Add phone number", "gatewayapi"); ?></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- Modal for phone number management -->
    <div id="gwapi-2fa-modal-overlay">
        <div id="gwapi-2fa-modal">
            <span class="gwapi-modal-close">&times;</span>
            
            <!-- Step 1: Enter phone number -->
            <div id="gwapi-2fa-step-phone">
                <h3><?php _e("Enter Phone Number", "gatewayapi"); ?></h3>
                <div id="gwapi-2fa-phone-msg" class="gwapi-modal-msg" style="display: none;"></div>
                <p><?php _e("Enter your phone number in international format (e.g. 4512345678).", "gatewayapi"); ?></p>
                <input type="text" id="gwapi-2fa-phone-input" placeholder="<?php esc_attr_e("e.g. 4512345678", "gatewayapi"); ?>" />
                <div class="gwapi-modal-buttons">
                    <button type="button" class="button" id="gwapi-2fa-cancel-phone"><?php _e("Cancel", "gatewayapi"); ?></button>
                    <button type="button" class="button button-primary" id="gwapi-2fa-send-code"><?php _e("Send verification code", "gatewayapi"); ?></button>
                </div>
            </div>
            
            <!-- Step 2: Enter verification code -->
            <div id="gwapi-2fa-step-code">
                <h3><?php _e("Enter Verification Code", "gatewayapi"); ?></h3>
                <div id="gwapi-2fa-code-msg" class="gwapi-modal-msg" style="display: none;"></div>
                <p><?php _e("Enter the 8-digit verification code sent to your phone.", "gatewayapi"); ?></p>
                <input type="text" id="gwapi-2fa-code-input" placeholder="<?php esc_attr_e("e.g. 12 34 56 78", "gatewayapi"); ?>" maxlength="11" />
                <div class="gwapi-modal-buttons">
                    <button type="button" class="button" id="gwapi-2fa-back-to-phone"><?php _e("Back", "gatewayapi"); ?></button>
                    <button type="button" class="button button-primary" id="gwapi-2fa-verify-code"><?php _e("Verify", "gatewayapi"); ?></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            var currentPhone = '<?php echo esc_js($phone); ?>';
            var pendingPhone = '';
            var nonce = '<?php echo wp_create_nonce("gwapi_2fa_verify"); ?>';
            var targetUserId = <?php echo intval($user->ID); ?>;
            var isSelf = <?php echo $is_self ? 'true' : 'false'; ?>;
            var isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
            
            // Modal elements
            var $overlay = $('#gwapi-2fa-modal-overlay');
            var $stepPhone = $('#gwapi-2fa-step-phone');
            var $stepCode = $('#gwapi-2fa-step-code');
            var $phoneInput = $('#gwapi-2fa-phone-input');
            var $codeInput = $('#gwapi-2fa-code-input');
            var $phoneMsg = $('#gwapi-2fa-phone-msg');
            var $codeMsg = $('#gwapi-2fa-code-msg');
            
            // Open modal for adding/changing phone
            function openModal() {
                $phoneInput.val('');
                $codeInput.val('');
                $phoneMsg.hide();
                $codeMsg.hide();
                $stepPhone.show();
                $stepCode.hide();
                $overlay.show();
                $phoneInput.focus();
            }
            
            // Close modal
            function closeModal() {
                $overlay.hide();
                $stepPhone.hide();
                $stepCode.hide();
            }
            
            // Show message in modal
            function showMsg($el, msg, type) {
                $el.removeClass('error success info').addClass(type).text(msg).show();
            }
            
            // Update the phone display section
            function updatePhoneDisplay(phone, verified) {
                var $section = $('#gwapi_2fa_phone_section');
                if (phone) {
                    var statusHtml = verified 
                        ? '<span style="color: green; font-weight: bold;">✓ <?php _e("Verified", "gatewayapi"); ?></span>'
                        : '<span style="color: orange;">⚠ <?php _e("Not verified", "gatewayapi"); ?></span>';
                    
                    var buttonsHtml = '<div class="gwapi-2fa-buttons">';
                    if (isSelf) {
                        buttonsHtml += '<button type="button" class="button" id="gwapi-2fa-change-btn"><?php _e("Change", "gatewayapi"); ?></button>';
                    }
                    if (isSelf || isAdmin) {
                        buttonsHtml += '<button type="button" class="button" id="gwapi-2fa-remove-btn"><?php _e("Remove", "gatewayapi"); ?></button>';
                    }
                    buttonsHtml += '</div>';

                    $section.html(
                        '<span class="gwapi-2fa-phone-display" id="gwapi-2fa-current-phone">' + $('<div>').text(phone).html() + '</span>' +
                        statusHtml +
                        buttonsHtml
                    );
                    currentPhone = phone;
                } else {
                    var noPhoneHtml = '<span id="gwapi-2fa-no-phone"><?php _e("No phone number configured.", "gatewayapi"); ?></span>';
                    if (isSelf) {
                        noPhoneHtml += '<div class="gwapi-2fa-buttons">' +
                            '<button type="button" class="button button-primary" id="gwapi-2fa-add-btn"><?php _e("Add phone number", "gatewayapi"); ?></button>' +
                        '</div>';
                    }
                    $section.html(noPhoneHtml);
                    currentPhone = '';
                }
            }
            
            // Button click handlers (using event delegation)
            $(document).on('click', '#gwapi-2fa-add-btn, #gwapi-2fa-change-btn', function() {
                openModal();
            });
            
            $(document).on('click', '#gwapi-2fa-remove-btn', function() {
                var confirmMsg = isSelf 
                    ? '<?php _e("Are you sure you want to remove your phone number? This will disable two-factor authentication.", "gatewayapi"); ?>'
                    : '<?php _e("Are you sure you want to remove this user\'s phone number? This will disable two-factor authentication for them.", "gatewayapi"); ?>';
                if (confirm(confirmMsg)) {
                    $.post(ajaxurl, {
                        action: 'gatewayapi_2fa_verify',
                        step: 'remove',
                        user_id: targetUserId,
                        nonce: nonce
                    }, function(res) {
                        if (res.success) {
                            updatePhoneDisplay('', false);
                        } else {
                            alert(res.data.message || '<?php _e("Failed to remove phone number.", "gatewayapi"); ?>');
                        }
                    });
                }
            });
            
            // Modal close handlers
            $('.gwapi-modal-close, #gwapi-2fa-cancel-phone').on('click', closeModal);
            $overlay.on('click', function(e) {
                if (e.target === this) closeModal();
            });
            
            // Send verification code
            $('#gwapi-2fa-send-code').on('click', function() {
                var phone = $phoneInput.val().replace(/\D/g, '');
                if (!phone) {
                    showMsg($phoneMsg, '<?php _e("Please enter a phone number.", "gatewayapi"); ?>', 'error');
                    return;
                }
                
                pendingPhone = phone;
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e("Sending...", "gatewayapi"); ?>');
                
                $.post(ajaxurl, {
                    action: 'gatewayapi_2fa_verify',
                    step: 'send',
                    phone: phone,
                    user_id: targetUserId,
                    nonce: nonce
                }, function(res) {
                    $btn.prop('disabled', false).text('<?php _e("Send verification code", "gatewayapi"); ?>');
                    if (res.success) {
                        $stepPhone.hide();
                        $stepCode.show();
                        $codeInput.val('').focus();
                        showMsg($codeMsg, '<?php _e("Verification code sent!", "gatewayapi"); ?>', 'success');
                    } else {
                        showMsg($phoneMsg, res.data.message || '<?php _e("Failed to send code.", "gatewayapi"); ?>', 'error');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('<?php _e("Send verification code", "gatewayapi"); ?>');
                    showMsg($phoneMsg, '<?php _e("Network error. Please try again.", "gatewayapi"); ?>', 'error');
                });
            });
            
            // Back to phone step
            $('#gwapi-2fa-back-to-phone').on('click', function() {
                $stepCode.hide();
                $stepPhone.show();
                $phoneInput.focus();
            });
            
            // Verify code
            $('#gwapi-2fa-verify-code').on('click', function() {
                var code = $codeInput.val().replace(/\D/g, '');
                if (!code) {
                    showMsg($codeMsg, '<?php _e("Please enter the verification code.", "gatewayapi"); ?>', 'error');
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e("Verifying...", "gatewayapi"); ?>');
                
                $.post(ajaxurl, {
                    action: 'gatewayapi_2fa_verify',
                    step: 'verify_and_save',
                    phone: pendingPhone,
                    code: code,
                    user_id: targetUserId,
                    nonce: nonce
                }, function(res) {
                    $btn.prop('disabled', false).text('<?php _e("Verify", "gatewayapi"); ?>');
                    if (res.success) {
                        closeModal();
                        updatePhoneDisplay(pendingPhone, true);
                    } else {
                        showMsg($codeMsg, res.data.message || '<?php _e("Invalid code.", "gatewayapi"); ?>', 'error');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('<?php _e("Verify", "gatewayapi"); ?>');
                    showMsg($codeMsg, '<?php _e("Network error. Please try again.", "gatewayapi"); ?>', 'error');
                });
            });
            
            // Allow Enter key to submit
            $phoneInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#gwapi-2fa-send-code').click();
                }
            });
            
            $codeInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#gwapi-2fa-verify-code').click();
                }
            });
        });
    </script>
    <?php
}

