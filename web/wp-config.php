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
define('SAML_IDP_X509_CERT', 'MIICmzCCAYMCBgGdKJqtUTANBgkqhkiG9w0BAQsFADARMQ8wDQYDVQQDDAZtYXN0ZXIwHhcNMjYwMzI2MDUyMzM4WhcNMzYwMzI2MDUyNTE4WjARMQ8wDQYDVQQDDAZtYXN0ZXIwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDELb9ajsxv/B/C3/HyA/d7pcKZ6DSNxRudbNRpzGeTIjtgcdkCOpCCOZ2m6muPY+xzOWpFhf8mwHrFAVDNfk0gJz8DJ73oQt9yxiFdLasg+blDmIX18+JEjBFpGtb/cjGAdsawji0BxcciaL/ANAMWQy7DUDb3du0KkAGjfIRJoA+sS9W2V1wRXIZtjSBEF04Ogo/zzD9/YO9m/YregKisnD/K1gPWrUIJnUK19jm+3YkWBPA2bqvWDGOScQBP0WafUgTWtfxDCT6mqjgNlLe1K0mcetEGmrI1D1ja36P87eqjFkK99pz4oyMgh9sta7qLkbQFktYVTD8R5DO3uhnvAgMBAAEwDQYJKoZIhvcNAQELBQADggEBAI7dn47nW9V8tRWDthnzppyaKx/nDbzlj3hOGZMcaOftZo/idNF4ReR5uxr7z6BaMwmOgIQLQAxsSelLreoodffeMtcamBOIvzNUkzpxZzcDp+XKCJnSGh7SS9Uq07rPPxM8Jr/LUe9TFskahsvwA8IOdcswqd9eXzSGLUjk0pmOw8VFqCUOH7luLVm8rRPqaUutEXevXYyDQAcmBIeNNLIZHP7vJPnw7LjkokF/2PMsxFmovT2uApQoVvGWSg2B0uKkQBa0IllJlNe7LwKMu1ZkGVrH0WuncykrGQ0qrNlvwcrMNPtaPxWwBab8zEUthFZsk3cgEqe0K0e5Inj5yDY=');
define('SAML_AUTO_PROVISION', true);
define('SAML_STRICT', false);

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
