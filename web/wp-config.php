<?php

declare(strict_types=1);

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wp/');
}
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8080/wp-content');
define('WP_HOME', 'http://localhost:8080');
define('WP_SITEURL', 'http://localhost:8080/wp');

define('DB_NAME', 'wppack_dev');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
$table_prefix = 'wpdev_';

// Redis (Valkey) object cache
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('WP_ENVIRONMENT_TYPE', 'local');
define('SAVEQUERIES', true);

// SAML SSO (Keycloak)
define('SAML_IDP_ENTITY_ID', 'http://localhost:8081/realms/master');
define('SAML_IDP_SSO_URL', 'http://localhost:8081/realms/master/protocol/saml');
define('SAML_IDP_SLO_URL', 'http://localhost:8081/realms/master/protocol/saml');
define('SAML_IDP_X509_CERT', 'MIICmzCCAYMCBgGdKMBAvjANBgkqhkiG9w0BAQsFADARMQ8wDQYDVQQDDAZtYXN0ZXIwHhcNMjYwMzI2MDYwNDQxWhcNMzYwMzI2MDYwNjIxWjARMQ8wDQYDVQQDDAZtYXN0ZXIwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCvzyqJc8wXWCKwy2c2HZi9XTdUqZ3iZmB64hvJpxyfQ+9oq3cMNe3tfUFCKUUJbFa6/zTDbKzJJV0erHGJ++1jQLKZOefqRz8euNwAtXyokEQVUtJbbK+Z5K6MXBqRCMfiWNvNrRcC8AoZZnMRdRwFQFfEsufhKL6iPXGx8LcxscVdvzb6f6ba/3243m8IUteggtRFr1GQNKQtN2yoAf/zbAhjann72x0kYkT1mf4gDsC0AKvm82VM5XAOb6tDdXyQwc5JWQsL3TV0fWS4EPva26UehDpy0kJyDJ9JegYLiY5WoW4hqgR2ipoHR6QtCgnBaBn0tObVuYfOYFZomaABAgMBAAEwDQYJKoZIhvcNAQELBQADggEBABaYbr6VeuPguRZ58HGJ+4ogWMx9po5k24uJ0A/AFgm82808mHt2iGXkNVWE4aX30IshnC69nvYRcpFRJ1KhweT2f9TfL1/PmtckQxzcm8W6m+h9kxmTNebFsD6Vg7UNuXgzprGGpXNzfLZceJhS+b0l5oBKZsdot5n1LUyxRPSy1PghA5ydob+xe57XFxO7l+HK0RRJyzOO8/2cin9tfGSAiJujCk0DpxDp+MHIMgCYeNQOSj7QOB+xKoc70k0NgygiuAr48eMRS7Aoz49RbAPcUGfEljXR84uQM9krMnpz0PbWQ3h6LrwjusM8sd3DdSCUgpP1IjLq4gZ1pF2g8B0=');
define('SAML_AUTO_PROVISION', true);
define('SAML_STRICT', false);
define('SAML_ALLOW_REPEAT_ATTRIBUTE_NAME', true);
define('SAML_SSO_ONLY', false);

// Auth keys (dev only — do not use in production)
define('AUTH_KEY', 'wppack-dev-auth-key');
define('SECURE_AUTH_KEY', 'wppack-dev-secure-auth-key');
define('LOGGED_IN_KEY', 'wppack-dev-logged-in-key');
define('NONCE_KEY', 'wppack-dev-nonce-key');
define('AUTH_SALT', 'wppack-dev-auth-salt');
define('SECURE_AUTH_SALT', 'wppack-dev-secure-auth-salt');
define('LOGGED_IN_SALT', 'wppack-dev-logged-in-salt');
define('NONCE_SALT', 'wppack-dev-nonce-salt');

require_once ABSPATH . 'wp-settings.php';
