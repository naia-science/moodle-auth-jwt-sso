# Moodle Firebase SSO Authentication Plugin

## Description
Moodle authentication plugin for Single Sign-On using Firebase Authentication.
A Firebase ID token passed on the URL is verified directly against Firebase's
published signing keys (no shared secret, no intermediary token). Users are
logged in automatically, and a Moodle account is auto-provisioned on first
sign-in if none exists yet for that email.

Forked from [karimanouri/moodle-auth_jwt_sso](https://github.com/karimanouri/moodle-auth_jwt_sso),
which verified a symmetric (HS256) JWT minted by a separate backend. This fork
instead verifies Firebase ID tokens directly (RS256, via Firebase's JWKS), so
there is no shared secret to manage or backend token-minting step involved.

## Features
- SSO from a Firebase ID token, verified against Firebase's JWKS (no shared secret).
- Issuer/audience checked against a configured Firebase project ID.
- Auto-provisions a Moodle account (by email) on first sign-in — any Firebase
  user may enrol, there is no entitlement/allowlist check.
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

## Usage
Redirect a signed-in Firebase user to a Moodle URL with their current ID
token (from `firebase.auth().currentUser.getIdToken()`) attached:
```
https://yourmoodle.com/course/view.php?id=2&token=<firebase_id_token>
```
If the token is valid (signature, issuer, audience, verified email), the user
is logged in — creating their Moodle account first if this is their first
visit — and redirected to the requested page. If the token is missing or
invalid, Moodle proceeds with its normal login process.

## Security Considerations
- Always use HTTPS — the token is a bearer credential while in flight and
  while it sits in the URL (browser history, referrer headers, access logs).
- Firebase ID tokens are short-lived (~1 hour) and the plugin honors their
  `exp`/`iat` claims; there's no separate expiry to configure.
- Only tokens with a *verified* email are accepted — unverified emails
  cannot log in or trigger account provisioning.
- No entitlement check is performed: any user with a valid Firebase account
  for the configured project gets a Moodle account. This is intentional for
  this deployment; add a check in `provision_user()`/`pre_loginpage_hook()`
  if that ever needs to change.

## Credits
- Uses [firebase/php-jwt](https://github.com/firebase/php-jwt) (vendored under
  `lib/php-jwt/`) for JWT/JWKS handling.
- Originally developed by [karimanouri](https://github.com/karimanouri).
