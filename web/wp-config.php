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
define('SAML_IDP_X509_CERT', 'MIICmzCCAYMCBgGdLELAIDANBgkqhkiG9w0BAQsFADARMQ8wDQYDVQQDDAZtYXN0ZXIwHhcNMjYwMzI2MjIyNjA1WhcNMzYwMzI2MjIyNzQ1WjARMQ8wDQYDVQQDDAZtYXN0ZXIwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDlubDrJif5tQQwc+3U4XSWG90CUMIIIm7uB5n8UVh80Rq0vjyg6S4ERKjsL8ZJCWBGe0q0Y7xjJWi4QqBnvQ6Ww1uxRfcvQB/s+QbOgnY3p+6NKZZUARHUsQE6PTduU6se2MvMHtvDbnlKKRirfeio+5kX/Pe5JcbNomVjdQr539zN+LnSC4tXJsiueIQsnwlBbKaKjrCZQKzOi7mxgcTnSuSjkvqy0vQSdmI3lkbGd+yVL/sTH8xJImeMCCPBFfR/XeIyvOiN3dXdewiMdna0RU0eQV1AwiVhr+iTDBLKzWrwXcAe1eozbJZLrJFbJoSSnKitxBtkUot14mSTngN3AgMBAAEwDQYJKoZIhvcNAQELBQADggEBAHIHS4v3FK1EQlNL7qrXZqNKYxHePHwslCvSuIvlqxcvXyGBhqTVJ3eUxH1NXRSgZ3RczcR2y5gt5BFYVAimiIFoaV/wQNQbzynqSRTGf3Vczeg57rgULXahlVoEifUqhvSCkDSlipBGDS+Zl9bum0ModKtp8IkXP+Dmk5tPqs3Eegl03gAWUA1Ucy5a6SRgG3fXjjodQ2N+CmqMjzDWwlcuNeFpoHXtJPanZoaZ3iOgMnT0gVsXfwCE1dhMBh8zYakorXMUmQLel7wK2nQIGQ2yVqTBijZGjE3hZ8H4FP5s/V4vt1w/khyMBujMb2Z68L9vIvqHyA669ReLQJ2AygE=');
define('SAML_AUTO_PROVISION', true);
define('SAML_STRICT', false);
define('SAML_ALLOW_REPEAT_ATTRIBUTE_NAME', true);
define('SAML_SSO_ONLY', true);

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
