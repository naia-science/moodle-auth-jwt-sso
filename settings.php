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

    // Add the settings to the admin page
    $ADMIN->add('authsettings', $settings);
}
