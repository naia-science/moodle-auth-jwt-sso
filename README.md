# Moodle JWT SSO Authentication Plugin

## Description
This is a Moodle authentication plugin that allows Single Sign-On (SSO) using JSON Web Tokens (JWT). Users who have a valid JWT token in the URL are automatically logged into Moodle without needing to enter a username or password.

## Features
- Seamless SSO authentication using JWT.
- Uses a secret key for token verification.
- Configurable JWT token parameter name in the URL.
- Redirects users to the originally requested page after authentication.
- Secure token validation with Firebase PHP-JWT library.

## Installation

### 1. Clone the Repository
Navigate to Moodle's `auth` directory and clone the plugin:
```sh
cd /path/to/moodle/auth
mkdir jwt_sso
cd jwt_sso
```

### 2. Copy Plugin Files
Ensure that `auth.php` and `settings.php` are placed inside the `auth/jwt_sso/` directory.

### 3. Enable the Plugin
1. Log in to Moodle as an administrator.
2. Go to **Site Administration > Plugins > Authentication > Manage Authentication**.
3. Enable `JWT SSO` authentication.
4. Configure the plugin settings:
   - **JWT Secret Key:** The secret key used to verify the JWT.
   - **Token Parameter Name:** The URL parameter that contains the JWT token (default: `token`).

## Usage
To authenticate a user via JWT, send them to a Moodle URL with the token:
```
https://yourmoodle.com/course/view.php?id=2&token=your_jwt_here
```
If the token is valid, the user is logged in and redirected to the requested page. If the token is missing or invalid, Moodle proceeds with the normal login process.

## Security Considerations
- Always use HTTPS to transmit tokens securely.
- Rotate your JWT secret key periodically.
- Ensure your external application properly signs JWTs to prevent unauthorized access.

## Credits
- Uses [Firebase PHP-JWT](https://github.com/firebase/php-jwt) for JWT handling.
- Developed for seamless Moodle authentication using external applications.

## Contact
For any questions or feedback, please contact `emankariminouri@gmail.com`.

