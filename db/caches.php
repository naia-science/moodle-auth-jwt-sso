<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Caches the Firebase JWKS (signing keys) so we don't fetch it on every login.
    'jwks' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 3600,
    ],
];
