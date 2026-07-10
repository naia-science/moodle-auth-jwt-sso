# Moodle Firebase SSO Authentication Plugin

## Description
Moodle authentication plugin for Single Sign-On using Firebase Authentication.
A Firebase ID token passed on the URL is verified directly against Firebase's
published signing keys (no shared secret, no intermediary token). Users are
logged in automatically, and a Moodle account is auto-provisioned on first
sign-in if none exists yet for that email.

Users who land on Moodle directly (not via a link that already carries a
token) get a "Log in with Firebase" option on Moodle's native login page,
served entirely by this plugin (`login.php`) - a self-contained Firebase
email/password form using Firebase's CDN-hosted Web SDK. No other app or
service is involved; this plugin does not depend on any other codebase.

Forked from [karimanouri/moodle-auth_jwt_sso](https://github.com/karimanouri/moodle-auth_jwt_sso),
which verified a symmetric (HS256) JWT minted by a separate backend. This fork
instead verifies Firebase ID tokens directly (RS256, via Firebase's JWKS), so
there is no shared secret to manage or backend token-minting step involved.

## Features
- SSO from a Firebase ID token, verified against Firebase's JWKS (no shared secret).
- Issuer/audience checked against a configured Firebase project ID.
- Auto-provisions a Moodle account (by email) on first sign-in — any Firebase
  user may enrol, there is no entitlement/allowlist check.
- Self-hosted "Log in with Firebase" page for users arriving at Moodle
  directly, with no dependency on any other app.
- Configurable JWT token parameter name in the URL.
- Redirects users to the originally requested page after authentication.
- JWKS responses are cached (1 hour) to avoid a Google round-trip on every login.

## Installation

### 1. Clone the Repository
```sh
cd /path/to/moodle/auth
git clone https://github.com/naia-science/moodle-auth-jwt-sso jwt_sso
```

### 2. Enable the Plugin
1. Log in to Moodle as an administrator.
2. Go to **Site Administration > Plugins > Authentication > Manage Authentication**.
3. Enable `Firebase SSO Authentication`.
4. Configure the plugin settings:
   - **Firebase project ID:** the Firebase project whose ID tokens should be accepted.
   - **Token Parameter Name:** the URL parameter carrying the Firebase ID token (default: `token`).
   - **Firebase API key / auth domain / app ID:** the Firebase project's web
     app config, used only by this plugin's own `login.php` (client-side).
     Not secret by Firebase's own design, but kept in Moodle's config DB
     rather than the plugin's source so this public repo never names a real
     project.
   - **reCAPTCHA Enterprise site key:** only needed if the Firebase project
     enforces App Check on Authentication. Leave blank to skip App Check.

## Usage
Two ways a user ends up logged in:

**Already has a Firebase session elsewhere** (e.g. another app using the same
Firebase project): redirect them to a Moodle URL with a fresh ID token
(from `firebase.auth().currentUser.getIdToken()`) attached:
```
https://yourmoodle.com/course/view.php?id=2&token=<firebase_id_token>
```

**Arrives at Moodle directly**, with no token: Moodle's login page shows a
"Log in with Firebase" option (see `auth_plugin_jwt_sso::loginpage_idp_list()`)
that leads to this plugin's own `login.php` - a self-contained email/password
form. On success it redirects back with a fresh token, landing in the same
flow as above.

In both cases, if the token is valid (signature, issuer, audience, has an
email claim), the user is logged in — creating their Moodle account first if
this is their first visit — and redirected to the originally requested page.
If the token is missing or invalid, Moodle proceeds with its normal login
process.

## Security Considerations
- Always use HTTPS — the token is a bearer credential while in flight and
  while it sits in the URL (browser history, referrer headers, access logs).
- Firebase ID tokens are short-lived (~1 hour) and the plugin honors their
  `exp`/`iat` claims; there's no separate expiry to configure.
- Email verification is *not* checked — matching the trust model the rest of
  the Firebase-backed system already uses (the main app's backend doesn't
  check `email_verified` either), so any account that can sign in to Firebase
  can SSO into Moodle and get provisioned.
- No entitlement check is performed: any user with a valid Firebase account
  for the configured project gets a Moodle account. This is intentional for
  this deployment; add a check in `provision_user()`/`pre_loginpage_hook()`
  if that ever needs to change.

## Credits
- Uses [firebase/php-jwt](https://github.com/firebase/php-jwt) (vendored under
  `lib/php-jwt/`) for JWT/JWKS handling.
- Originally developed by [karimanouri](https://github.com/karimanouri).
