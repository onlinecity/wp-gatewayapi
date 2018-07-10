<?php

class GwapiSecurityTwoFactor
{
    /**
     * Returns a list of roles with the key being the role attribute name, and the value being a 0-indexed array consiting
     * of the pretty title and the current status (boolean).
     *
     * @return array
     */
    public static function getRoles()
    {
        $roles = wp_roles()->get_names();
        $current_roles = get_option('gwapi_security_required_roles');

        $out = [];
        foreach($roles as $k=>$t) {
            $enabled = $k !== 'subscriber';

            if($current_roles) {
                $enabled = in_array($k, $current_roles);
            }

            $out[$k] = [$t, $enabled];
        }

        return $out;
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
        foreach($user->roles as $role) {
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
        if (isset($_COOKIE['gwapi_2f_token'])&& ($cookie_token = trim($_COOKIE['gwapi_2f_token'])) && $user->gwapi_2f_tokens) {
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
        foreach($user->gwapi_2f_tokens?:[] as $token => $expiry) {
            if (time() < $expiry) $new_tokens[$token] = $expiry;
        }
        if(count($new_tokens) !== count($user->gwapi_2f_tokens)) {
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

        wp_clear_auth_cookie();

        $temp_token = $user->ID.'_'.wp_generate_password(12,false,false);
        set_transient('gwapi_2f_'.$temp_token, ['user' => $user->ID, 'remember' => $remember, 'redirect_to' => $redir], 60*30);
        self::setCookie('gwapi_2f_tmp', $temp_token, time() + 60*30);

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
     * Render the HTML for the two-factor steps. This part of the login, is all in a single-page approach.
     *
     * @param WP_User $user
     */
    private static function renderTwoFactorSteps(\WP_User $user)
    {
        include _gwapi_dir().'/tpl/wp-login.php';
    }
}

add_action('wp_login', ['GwapiSecurityTwoFactor', 'handleLoginHook'], 10, 2);