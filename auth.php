<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/auth/jwt_sso/lib/php-jwt/src/JWT.php');
require_once($CFG->dirroot . '/auth/jwt_sso/lib/php-jwt/src/Key.php');
require_once($CFG->dirroot . '/auth/jwt_sso/lib/php-jwt/src/JWK.php');

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

/**
 * Logs a user in from a Firebase ID token passed on the URL, provisioning
 * their Moodle account on first sign-in if it doesn't exist yet.
 */
class auth_plugin_jwt_sso extends auth_plugin_base {

    /** Google's published JWKS for verifying Firebase ID tokens. */
    const JWKS_URL = 'https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com';

    public function __construct() {
        $this->authtype = 'jwt_sso';
        $this->config = get_config('auth_jwt_sso');
    }

    public function pre_loginpage_hook() {
        global $CFG, $SESSION;

        if (isloggedin() && !isguestuser()) {
            return;
        }

        $projectid = trim($this->config->firebase_project_id ?? '');
        if ($projectid === '') {
            debugging('auth_jwt_sso: firebase_project_id is not configured.', DEBUG_NORMAL);
            return;
        }

        // The SSO token is only ever accepted on a POST issued by this
        // plugin's own login page. This deliberately rules out driving a
        // login from a token sitting in a GET URL (a bookmarked, shared,
        // logged or externally-crafted link), so the token never travels in
        // the address bar or server access logs.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $tokenparam = $this->config->token_param ?: 'token';
        $token = optional_param($tokenparam, '', PARAM_RAW);
        if ($token === '') {
            return;
        }

        $claims = $this->verify_token($token, $projectid);
        if (!$claims) {
            return;
        }

        if (empty($claims->email)) {
            debugging('auth_jwt_sso: token has no email claim.', DEBUG_NORMAL);
            return;
        }

        $email = strtolower($claims->email);
        try {
            $user = \core_user::get_user_by_email($email);
            if ($user && $user->auth !== 'jwt_sso') {
                // This email belongs to an existing account created through a
                // different auth method - never auto-login/take it over via
                // SSO, since nothing here proves the caller owns that email.
                debugging('auth_jwt_sso: ' . $email . ' belongs to an existing non-jwt_sso account; refusing SSO login.', DEBUG_NORMAL);
                return;
            }
            if (!$user) {
                $user = $this->provision_user($email, $claims);
            }
        } catch (\Exception $e) {
            debugging('auth_jwt_sso: failed to find/provision user for ' . $email . ': ' . $e->getMessage(), DEBUG_NORMAL);
            return;
        }
        if (!$user || $user->deleted || $user->suspended) {
            return;
        }

        $user = get_complete_user_data('id', $user->id);
        if (!$user) {
            return;
        }

        complete_user_login($user);

        $redirecturl = !empty($SESSION->wantsurl) ? $SESSION->wantsurl : $CFG->wwwroot;
        redirect($redirecturl);
    }

    /**
     * Verifies a Firebase ID token's signature (against Firebase's published
     * JWKS), issuer and audience. Returns the decoded claims, or null if the
     * token is missing, malformed, expired, or not meant for this project.
     */
    private function verify_token(string $token, string $projectid): ?object {
        try {
            $keyset = JWK::parseKeySet($this->get_firebase_jwks());
            JWT::$leeway = 5;
            $decoded = JWT::decode($token, $keyset);
        } catch (\Exception $e) {
            debugging('auth_jwt_sso: token verification failed: ' . $e->getMessage(), DEBUG_NORMAL);
            return null;
        }

        $expectedissuer = 'https://securetoken.google.com/' . $projectid;
        if (($decoded->iss ?? null) !== $expectedissuer || ($decoded->aud ?? null) !== $projectid) {
            debugging('auth_jwt_sso: token issuer/audience does not match configured Firebase project.', DEBUG_NORMAL);
            return null;
        }

        // Firebase requires a non-empty subject (the user's uid). A token
        // without it is not a valid ID token.
        if (!is_string($decoded->sub ?? null) || trim($decoded->sub) === '') {
            debugging('auth_jwt_sso: token has no valid sub (uid) claim.', DEBUG_NORMAL);
            return null;
        }

        return $decoded;
    }

    /**
     * Fetches Firebase's signing keys, cached per db/caches.php's TTL so we
     * don't hit Google on every login attempt.
     */
    private function get_firebase_jwks(): array {
        $cache = \cache::make('auth_jwt_sso', 'jwks');
        $jwks = $cache->get('keyset');
        if ($jwks !== false) {
            return $jwks;
        }

        $curl = new \curl();
        $response = $curl->get(self::JWKS_URL);
        if ($curl->get_errno() || empty($response)) {
            throw new \moodle_exception('jwksfetchfailed', 'auth_jwt_sso');
        }

        $jwks = json_decode($response, true);
        if (!is_array($jwks) || empty($jwks['keys'])) {
            throw new \moodle_exception('jwksinvalid', 'auth_jwt_sso');
        }

        $cache->set('keyset', $jwks);
        return $jwks;
    }

    /**
     * Creates a new Moodle account for a Firebase user seen for the first
     * time. Any Firebase account is allowed in - there is no entitlement
     * check here by design (all Firebase users may enrol).
     */
    private function provision_user(string $email, object $claims): ?\stdClass {
        global $CFG;

        $name = trim((string) ($claims->name ?? ''));
        $parts = $name !== '' ? preg_split('/\s+/', $name, 2) : [];
        $localpart = explode('@', $email)[0];

        $newuser = (object) [
            'auth' => 'jwt_sso',
            'username' => $this->generate_username($email),
            'email' => $email,
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'firstname' => $parts[0] ?? $localpart,
            'lastname' => $parts[1] ?? '.',
            'password' => AUTH_PASSWORD_NOT_CACHED,
        ];

        // generate_username() reads-then-writes, so two concurrent first
        // sign-ins for distinct emails can pick the same username and one
        // insert will hit the unique constraint. Retry with a freshly
        // computed username a few times before giving up.
        $attempts = 0;
        while (true) {
            try {
                $newuser->id = user_create_user($newuser, false, true);
                break;
            } catch (\dml_write_exception $e) {
                if (++$attempts >= 5) {
                    throw $e;
                }
                $newuser->username = $this->generate_username($email);
            }
        }

        return \core_user::get_user($newuser->id);
    }

    /**
     * Derives a Moodle username from the email's local part, disambiguating
     * with a numeric suffix on collision.
     */
    private function generate_username(string $email): string {
        global $DB, $CFG;

        $base = clean_param(strtolower(explode('@', $email)[0]), PARAM_USERNAME);
        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $suffix = 1;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = $base . $suffix++;
        }

        return $username;
    }

    /**
     * Not used - this auth method only supports Firebase SSO, no passwords.
     */
    public function user_login($username, $password) {
        return false;
    }

    /**
     * Offers a "Log in with Firebase" option on Moodle's native login page,
     * pointing at this plugin's own login.php (self-contained Firebase Web
     * SDK login form) rather than any external site.
     */
    public function loginpage_idp_list($wantsurl) {
        if (trim($this->config->firebase_project_id ?? '') === '' || trim($this->config->firebase_api_key ?? '') === '') {
            return [];
        }

        $label = trim($this->config->login_button_label ?? '');

        return [[
            'url' => new \moodle_url('/auth/jwt_sso/login.php', ['wantsurl' => $wantsurl]),
            'name' => $label !== '' ? $label : get_string('loginwithfirebase', 'auth_jwt_sso'),
        ]];
    }
}
