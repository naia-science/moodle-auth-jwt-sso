# Moodle Firebase SSO Authentication Plugin

## Description
Moodle authentication plugin for Single Sign-On using Firebase Authentication.
A Firebase ID token is verified directly against Firebase's published signing
keys (no shared secret, no intermediary token). Users are logged in
automatically, and a Moodle account is auto-provisioned on first sign-in if
none exists yet for that email.

Users get a "Log in with Firebase" option on Moodle's native login page,
served entirely by this plugin (`login.php`) - a self-contained Firebase
email/password form using Firebase's CDN-hosted Web SDK. No other app or
service is involved; this plugin does not depend on any other codebase.

On success the login page submits the freshly minted ID token to the target
page via an auto-submitting **POST** form, which the plugin's
`pre_loginpage_hook()` then verifies. The token is only ever accepted on a
POST - it is never read from a URL query parameter - so it does not travel in
the address bar, browser history or server access logs, and a login cannot be
driven from a bookmarked, shared or externally-crafted link.

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
- The ID token is submitted by POST and only accepted by POST, never via a URL
  query parameter, so it is never exposed in URLs or access logs.
- Configurable name for the POSTed token parameter.
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
   - **Token Parameter Name:** the name of the POST field carrying the Firebase ID token (default: `token`).
   - **Firebase API key / auth domain / app ID:** the Firebase project's web
     app config, used only by this plugin's own `login.php` (client-side).
     Not secret by Firebase's own design, but kept in Moodle's config DB
     rather than the plugin's source so this public repo never names a real
     project.
   - **reCAPTCHA Enterprise site key:** only needed if the Firebase project
     enforces App Check on Authentication. Leave blank to skip App Check.
   - **App Check debug token:** bypasses reCAPTCHA attestation for a domain
     the reCAPTCHA key isn't registered for (e.g. local/staging testing).
     Leave blank in production.
   - **Login button label:** custom text for the login option on Moodle's
     login page (e.g. "Log in with your organisation account"). Leave blank
     for the default "Log in with Firebase". This - like the values above -
     lives in Moodle's config DB, not in this repo, so branding/company names
     never end up in source control.

### 3. Localization
Ships with English and French (`lang/fr/`) string packs. Moodle picks the
translation automatically based on the site's/user's language, same as any
other plugin.

## Usage
Users log in through the "Log in with Firebase" option on Moodle's native
login page (see `auth_plugin_jwt_sso::loginpage_idp_list()`), which leads to
this plugin's own `login.php` — a self-contained Firebase email/password form.
On a successful Firebase sign-in, the page auto-submits the fresh ID token to
the originally requested page via a **POST**, where `pre_loginpage_hook()`
verifies it.

If the token is valid (signature, issuer, audience, has an email claim), the
user is logged in — creating their Moodle account first if this is their first
visit — and redirected to the originally requested page. If the token is
missing or invalid, Moodle proceeds with its normal login process.

The token is **only** accepted on a POST. Driving a login by placing a token in
a URL — a deep link from another app, or a bookmarked/shared link — is
deliberately not supported, so the token never travels in a URL.

## Security Considerations
- Always use HTTPS — the token is a bearer credential while in flight. It is
  submitted by POST and never placed in a URL, so it does not leak through
  browser history, referrer headers or access logs.
- Firebase ID tokens are short-lived (~1 hour) and the plugin honors their
  `exp`/`iat` claims; there's no separate expiry to configure.
- Email verification is *not* checked — matching the trust model the rest of
  the Firebase-backed system already uses (the main app's backend doesn't
  check `email_verified` either), so any account that can sign in to Firebase
  can SSO into Moodle and get provisioned.
- Because email isn't verified, SSO **only ever logs into or provisions
  accounts with `auth = 'jwt_sso'`**. If a Moodle account with the same email
  already exists under a different auth method (manual, self-registration,
  etc.), the SSO login is refused rather than taking that account over -
  otherwise anyone could self-register a Firebase account with someone
  else's email and hijack their existing Moodle login.
- No entitlement check is performed: any user with a valid Firebase account
  for the configured project gets a Moodle account. This is intentional for
  this deployment; add a check in `provision_user()`/`pre_loginpage_hook()`
  if that ever needs to change.

## Credits
- Uses [firebase/php-jwt](https://github.com/firebase/php-jwt) (vendored under
  `lib/php-jwt/`) for JWT/JWKS handling.
- Originally developed by [karimanouri](https://github.com/karimanouri).
