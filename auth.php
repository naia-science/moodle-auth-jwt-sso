<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/auth/jwt_sso/lib/php-jwt/src/JWT.php'); // Include the locally stored JWT library
require_once($CFG->dirroot . '/auth/jwt_sso/lib/php-jwt/src/Key.php'); // Include key handling

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class auth_plugin_jwt_sso extends auth_plugin_base {

    public function __construct() {
        $this->authtype = 'jwt_sso';
        $this->config = get_config('auth_jwt_sso');
    }

    public function pre_loginpage_hook() {
        global $CFG, $SESSION;

        // If the user is already logged in, do nothing.
        if (isloggedin() && !isguestuser()) {
            return;
        }

        $tokenparam = get_config('auth_jwt_sso', 'token_param');
        $secret = $this->config->secretkey;
        if (empty($tokenparam) || empty($secret)) {
            error_log("JWT Plugin: Token parameter or JWT secret is not set in configuration.");
            return;
        }
        
        $token = $_GET[$tokenparam];
        // If no token is found, let Moodle continue with its normal login process.
        if (empty($token)) {
            return;
        }

        try {
            // Decode and verify the token (assumes HS256).
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            if (empty($decoded->username))
                return;

            $username = $decoded->username;

            // Retrieve the Moodle user record by username.
            $user = get_complete_user_data('username', $username);
            if (!$user)
                return;

            // Log the user in.
            complete_user_login($user);

            $originalurl = isset($SESSION->wantsurl) ? $SESSION->wantsurl : '';
            // Redirect to the originally requested (clean) URL or fallback to the homepage.
            $redirecturl = !empty($originalurl) ? $originalurl : $CFG->wwwroot;
            redirect($redirecturl);

        } catch (Exception $e) {
            return;
        }
    }

    /**
     * Not used in JWT-based authentication.
     */
    public function user_login($username, $password) {
        // This authentication method does not support password logins.
        return false;
    }
}
