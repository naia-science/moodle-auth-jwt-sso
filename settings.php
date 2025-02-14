<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('auth_jwt_sso', get_string('pluginname', 'auth_jwt_sso'));

    // Secret Key Setting
    $settings->add(new admin_setting_configtext(
        'auth_jwt_sso/secretkey',
        get_string('secretkey', 'auth_jwt_sso'),
        get_string('secretkey_desc', 'auth_jwt_sso'),
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
