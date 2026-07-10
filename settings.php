<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('auth_jwt_sso', get_string('pluginname', 'auth_jwt_sso'));

    // Firebase project ID (used to validate the token's issuer/audience).
    $settings->add(new admin_setting_configtext(
        'auth_jwt_sso/firebase_project_id',
        get_string('firebaseprojectid', 'auth_jwt_sso'),
        get_string('firebaseprojectid_desc', 'auth_jwt_sso'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'auth_jwt_sso/token_param',
        get_string('tokenparam', 'auth_jwt_sso'),
        get_string('tokenparam_desc', 'auth_jwt_sso'),
        'token', // Default value
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_heading(
        'auth_jwt_sso/loginpageheading',
        get_string('loginpageheading', 'auth_jwt_sso'),
        get_string('loginpageheading_desc', 'auth_jwt_sso')
    ));

    $settings->add(new admin_setting_configtext(
        'auth_jwt_sso/login_button_label',
        get_string('loginbuttonlabel', 'auth_jwt_sso'),
        get_string('loginbuttonlabel_desc', 'auth_jwt_sso'),
        '',
        PARAM_TEXT
    ));

    // Firebase Web SDK config, needed client-side to render our own login
    // page (auth/jwt_sso/login.php). These are the same values a Firebase
    // web app config block normally contains - not secret by Firebase's own
    // design, but kept here (Moodle's config DB) rather than in the plugin's
    // source so this public repo never names a real project.
    $settings->add(new admin_setting_configtext(
        'auth_jwt_sso/firebase_api_key',
        get_string('firebaseapikey', 'auth_jwt_sso'),
        get_string('firebaseapikey_desc', 'auth_jwt_sso'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'auth_jwt_sso/firebase_auth_domain',
        get_string('firebaseauthdomain', 'auth_jwt_sso'),
        get_string('firebaseauthdomain_desc', 'auth_jwt_sso'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'auth_jwt_sso/firebase_app_id',
        get_string('firebaseappid', 'auth_jwt_sso'),
        get_string('firebaseappid_desc', 'auth_jwt_sso'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'auth_jwt_sso/recaptcha_enterprise_key',
        get_string('recaptchaenterprisekey', 'auth_jwt_sso'),
        get_string('recaptchaenterprisekey_desc', 'auth_jwt_sso'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'auth_jwt_sso/appcheck_debug_token',
        get_string('appcheckdebugtoken', 'auth_jwt_sso'),
        get_string('appcheckdebugtoken_desc', 'auth_jwt_sso'),
        '',
        PARAM_TEXT
    ));

    // Add the settings to the admin page
    $ADMIN->add('authsettings', $settings);
}
