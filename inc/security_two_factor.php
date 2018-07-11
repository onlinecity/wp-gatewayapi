<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

class GwapiSecurityTwoFactor
{
    public static $TMP_TOKEN = null;

    /**
     * Returns a list of roles with the key being the role attribute name, and the value being a 0-indexed array consiting
     * of the pretty title and the current status (boolean).
     *
     * @return array
     */
    public static function getRoles()
    {
        $roles = wp_roles()->get_names();
        $current_roles = get_option('gwapi_security_required_roles', []);

        $out = [];
        foreach ($roles as $k => $t) {
            $enabled = in_array($k, $current_roles);
            $out[$k] = [$t, $enabled];
        }

        return $out;
    }

    public static function getBypassCode()
    {
        $code = get_option('gwapi_security_bypass_code');
        if (!$code) {
            $parts = [];
            for ($i = 0; $i < 6; $i++) {
                $parts[] = strtolower(wp_generate_password(3, false, false));
            }
            $code = implode('-', $parts);
            update_option('gwapi_security_bypass_code', $code, false);
        }

        return $code;
    }

    /**
     * Hooks into wp_login and intercepts, if all two-factor steps are not passed.
     *
     * @param $username
     * @param WP_User $user
     */
    public static function handleLoginHook($username, \WP_User $user)
    {
        if (!self::userNeedsTwoFactor($user)) return;
        if (self::userHasValidTwofactorCookie($user)) return;
        self::replaceLoginCookieWithTempCookie($user);
        self::renderTwoFactorSteps($user);

        die();
    }

    /**
     * Returns true if the user provided requires two-factor authentication.
     *
     * @param WP_User $user
     * @return bool
     */
    public static function userNeedsTwoFactor(\WP_User $user)
    {
        $all_roles = self::getRoles();
        foreach ($user->roles as $role) {
            if (!isset($all_roles[$role])) continue; // a new unknown role
            if ($all_roles[$role][1]) return true;
        }
        return false;
    }

    /**
     * Returns true if the list of cookies contains a valid two-factor cookie.
     *
     * @param WP_User $user
     * @return bool
     */
    private static function userHasValidTwofactorCookie(\WP_User $user)
    {
        if (isset($_COOKIE['gwapi_2f_token']) && ($cookie_token = trim($_COOKIE['gwapi_2f_token'])) && $user->gwapi_2f_tokens) {
            self::refreshExpiryOfUserTwoFactorTokens($user);
            $tokens = $user->gwapi_2f_tokens;
            if (isset($tokens[$cookie_token])) return true;
        }

        return false;
    }

    /**
     * Make sure that the WP_User's gwapi_2f_tokens-array only contains non-expired tokens.
     *
     * @param WP_User $user
     */
    private static function refreshExpiryOfUserTwoFactorTokens(\WP_User $user)
    {
        $new_tokens = [];
        foreach ($user->gwapi_2f_tokens ?: [] as $token => $expiry) {
            if (time() < $expiry) $new_tokens[$token] = $expiry;
        }
        if (count($new_tokens) !== count($user->gwapi_2f_tokens)) {
            update_user_meta($user->ID, 'gwapi_2f_tokens', $new_tokens);
            $user->gwapi_2f_tokens = $new_tokens; // @todo is this needed? oh well, better safe than sorry
        }
    }

    /**
     * Clears the auth cookie and remembers the current user and other login settings in a transient, giving the
     * transients key via a cookie.
     *
     * @param WP_User $user
     * @return string
     */
    private static function replaceLoginCookieWithTempCookie(\WP_User $user)
    {
        $remember = isset($_POST['rememberme']) && $_POST['rememberme'] == 'forever';
        $redir = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : get_admin_url();
        $code = rand(100000, 999999);

        wp_clear_auth_cookie();

        $temp_token = $user->ID . '_' . wp_generate_password(12, false, false);
        set_transient('gwapi_2f_' . $temp_token, ['user' => $user->ID, 'remember' => $remember, 'redirect_to' => $redir, 'code' => $code], 60 * 30);
        self::$TMP_TOKEN = $temp_token;

        return $temp_token;
    }

    /**
     * Sets a regular cookie on the client, but heavily inspired by how WordPress sets cookies - ie. forcing security,
     * using WP's PATH's etc.
     *
     * @param $key
     * @param $value
     * @param $expiration
     */
    public static function setCookie($key, $value, $expiration)
    {
        $secure = is_ssl();
        setcookie($key, $value, $expiration, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
    }

    /**
     * Render the HTML for the two-factor steps.
     *
     * @param WP_User $user
     */
    private static function renderTwoFactorSteps(\WP_User $user)
    {
        $throttle = GwapiSecurityTwoFactor::checkIfThrottled($user->ID, GwapiSecurityTwoFactorAddMobile::THROTTLE_ACTION, GwapiSecurityTwoFactorAddMobile::THROTTLE_MAX_TRIES, GwapiSecurityTwoFactorAddMobile::THROTTLE_EXPIRES);
        if (is_wp_error($throttle)) wp_die($throttle);

        if (!$user->gwapi_mcc || !$user->gwapi_mno) {
            GwapiSecurityTwoFactorAddMobile::handleAddMobile($user);
        } else {

        }
    }

    /**
     * Returns the array about the current user, redirect url etc. based on the temp token given.
     *
     * @param $temp_token
     * @return mixed|WP_Error
     */
    public static function getLoginDataByTempToken($temp_token)
    {
        $parts = explode('-', $temp_token, 2);;
        if (!count($parts) == 2) return new WP_Error('INVALID_TOKEN', __('Error: The format of the temporary token is invalid.', 'gwapi'));

        $token_data = get_transient('gwapi_2f_' . $temp_token);
        if (!$token_data) return new WP_Error('EXPIRED', __('Error: Your token seems to have expired. Please start over.', 'gwapi'));

        return $token_data;
    }

    public static function jsonFail(\WP_Error $wp_error)
    {
        http_response_code(422);
        die(json_encode([
            'message' => $wp_error->get_error_message(),
            'code' => $wp_error->get_error_code()
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Register an attempt at using a throttled action. If the threshold has been reached, then a WP_Error
     * is returned, otherwise the method returns nothing.
     */
    public static function failOnThrottle($user_ID, $action, $max_attempts, $expires_after_seconds)
    {
        $throttle_key = "_gwapi_throttle_" . $action;

        $err = self::checkIfThrottled($user_ID, $action, $max_attempts, $expires_after_seconds);
        if (is_wp_error($err)) return $err;

        $throttle = get_user_meta($user_ID, $throttle_key, true) ?: [];

        // add the attempt
        $throttle[] = time() + $expires_after_seconds;
        update_user_meta($user_ID, $throttle_key, $throttle);
    }

    /**
     * Just check if the action is throttled.
     */
    public static function checkIfThrottled($user_ID, $action, $max_attempts, $expires_after_seconds)
    {
        $throttle_key = "_gwapi_throttle_" . $action;
        $orig_throttle = get_user_meta($user_ID, $throttle_key, true) ?: [];

        // remove expired
        $throttle = array_filter($orig_throttle, function ($ts) use ($expires_after_seconds) {
            return $ts > time();
        });

        // too many attempts already?
        if (count($throttle) >= $max_attempts) {
            $time_left = "<strong>" . human_time_diff(time(), current($throttle)) . "</strong>";
            $error_msg = apply_filters('gwapi_throttle_error', __('Your account is temporarily locked, due to too many verification attempts in a short time span.<br /><br />Please wait at least :time_left before trying again.', 'gwapi'), $user_ID, $action, $max_attempts, $expires_after_seconds);
            return new WP_Error('TOO_MANY_ATTEMPTS', strtr($error_msg, [':time_left' => $time_left]));
        }

        update_user_meta($user_ID, $throttle_key, $throttle);
    }
}

/**
 * This class handles the flow related to a person logging in, who does not yet have a mobile phone attached to the
 * user profile. This runs a flow pairing the mobile phone with the account, using a one time token.
 */
class GwapiSecurityTwoFactorAddMobile
{
    const THROTTLE_ACTION = 'add_mobile';
    const THROTTLE_MAX_TRIES = 3;
    const THROTTLE_EXPIRES = 60 * 60;


    private static function enqueueCssJs()
    {
        _gwapi_enqueue_uideps(true);

        add_action('login_enqueue_scripts', function () {
            _gwapi_enqueue_uideps(false);
            wp_enqueue_script('gwapi-widgets');
            wp_enqueue_script('gwapi-wp-login', _gwapi_url() . '/js/wp-login.js');
            wp_enqueue_style('gwapi-wp-login', _gwapi_url() . '/css/wp-login.css');

            $i18n = [
                'ajax_tech_error' => __('An unknown technical error occured while processing the request. Please try again or contact your administrator.', 'gwapi')
            ];
            ?>
            <script>
                var GWAPI_I18N = <?= json_encode($i18n, JSON_PRETTY_PRINT); ?>;
            </script>
            <?php
        });
    }

    public static function handleAddMobile(\WP_user $user)
    {
        self::enqueueCssJs();

        include _gwapi_dir() . '/tpl/wp-login-add-phone.php';
    }

    public static function sendInitialSms()
    {
        header("Content-type: application/json");

        // token
        $tmp_token = trim($_POST['gwapi_2f_tmp']);

        // phone number
        $mcc = preg_replace('/[^0-9]+/', '', $_POST['mcc']);
        $mno = preg_replace('/[^0-9]+/', '', $_POST['mno']);
        $msisdn = preg_replace('/[^0-9]+/', '', $mcc . $mno);

        // validate the token
        $login_info = GwapiSecurityTwoFactor::getLoginDataByTempToken($tmp_token);
        if (is_wp_error($login_info)) GwapiSecurityTwoFactor::jsonFail($login_info);

        // save this attempt on the user
        $user_ID = $login_info['user'];
        $maybe_throttle = GwapiSecurityTwoFactor::failOnThrottle($user_ID, self::THROTTLE_ACTION, self::THROTTLE_MAX_TRIES, self::THROTTLE_EXPIRES);
        if (is_wp_error($maybe_throttle)) GwapiSecurityTwoFactor::jsonFail($maybe_throttle);

        // send the initial SMS
        $sender = get_bloginfo();
        $home_url = url_shorten(get_home_url());

        $message = strtr(__("Verification code: :code\nKind regards, :sender\n:home_url", 'gwapi'), [':code' => $login_info['code'], ':sender' => $sender, ':home_url' => $home_url]);
        $status = gwapi_send_sms($message, $msisdn);

        // save the phone number
        set_transient('gwapi_2f_'.$tmp_token.'_phone', [ $mcc, $mno ], 60 * 30);

        if (is_wp_error($status)) {
            if ($status->get_error_code() == 'GWAPI_FAIL') {
                $reason = $status->get_error_message();
                list ($human, $tech) = explode("\n", $reason, 2);
                $reason = $human . "<br /><br /><small>" . nl2br($tech) . "</small>";

                GwapiSecurityTwoFactor::jsonFail(new WP_Error('GWAPI', strtr(__('The SMS could not be sent, due to the SMS-service rejecting to send the message.<br><br>Technical reason:<br />- :reason', 'gwapi'), [':reason' => $reason])));
            } else {
                GwapiSecurityTwoFactor::jsonFail(new WP_Error('GWAPI', __('The SMS could not be sent due to a technical error/misconfiguration of the GatewayAPI-plugin.<br /><br />You could try again, but probably this can only be resolved by your administrator.', 'gwapi')));
            }
        }

        // vars for HTML
        $html = (function () use ($mcc, $mno, $tmp_token) {
            ob_start();
            include _gwapi_dir() . '/tpl/wp-login-confirm-phone.php';
            return ob_get_clean();
        })();

        die(json_encode(['success' => true, 'html' => $html]));
    }

    public static function confirmSms()
    {
        header("Content-type: application/json");

        // token
        $tmp_token = trim($_POST['gwapi_2f_tmp']);

        // validate the token
        $login_info = GwapiSecurityTwoFactor::getLoginDataByTempToken($tmp_token);
        if (is_wp_error($login_info)) GwapiSecurityTwoFactor::jsonFail($login_info);

        // save this attempt on the user
        $user_ID = $login_info['user'];
        add_filter('gwapi_throttle_error', function ($e) {
            return __('You have tried to enter the confirmation token too many times. Please start over to try again.', 'gwapi');
        });
        $maybe_throttle = GwapiSecurityTwoFactor::failOnThrottle($user_ID, 'confirm_' . $tmp_token, self::THROTTLE_MAX_TRIES, self::THROTTLE_EXPIRES);
        if (is_wp_error($maybe_throttle)) {
            GwapiSecurityTwoFactor::jsonFail($maybe_throttle);
        }

        // is the code correct?
        $user_code = preg_replace('/[^0-9]+/', '', $_POST['code']);
        $correct_code = $login_info['code'];
        if ($user_code != $correct_code) {
            GwapiSecurityTwoFactor::jsonFail(new WP_Error('BAD_CODE', __('The code you have entered, is invalid. Please double check the SMS we sent you and try again.', 'gwapi')));
        }

        // SUCCESS!
        // save the phone number on the profile
        list($mcc, $mno) = get_transient('gwapi_2f_'.$tmp_token.'_phone');
        update_user_meta($user_ID, 'gwapi_mcc', $mcc);
        update_user_meta($user_ID, 'gwapi_mno', $mno);

        // and log the user in
        wp_set_auth_cookie($user_ID, $login_info['remember']);

        // then show a message of the happy success
        $redirect_to = $login_info['redirect_to'];
        $html = (function () use ($redirect_to) {
            ob_start();
            include _gwapi_dir() . '/tpl/wp-login-confirmed-phone.php';
            return ob_get_clean();
        })();

        die(json_encode(['success' => true, 'html' => $html]));
    }
}

add_action('wp_login', ['GwapiSecurityTwoFactor', 'handleLoginHook'], 10, 2);
add_action('wp_ajax_nopriv_gwapi_security_add_phone', ['GwapiSecurityTwoFactorAddMobile', 'sendInitialSms']);
add_action('wp_ajax_nopriv_gwapi_security_confirm_phone', ['GwapiSecurityTwoFactorAddMobile', 'confirmSms']);