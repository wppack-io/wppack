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

// Multisite (subdirectory)
define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('DOMAIN_CURRENT_SITE', 'localhost:8080');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

// Redis (Valkey) object cache
define('CACHE_DSN', 'redis://127.0.0.1:6379');

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_ENVIRONMENT_TYPE', 'local');
define('SAVEQUERIES', true);

// SAML SSO (Keycloak)
define('SAML_IDP_ENTITY_ID', 'http://localhost:8081/realms/master');
define('SAML_IDP_SSO_URL', 'http://localhost:8081/realms/master/protocol/saml');
define('SAML_IDP_SLO_URL', 'http://localhost:8081/realms/master/protocol/saml');
//define('SAML_IDP_X509_CERT', '');
define('SAML_AUTO_PROVISION', true);
define('SAML_STRICT', false);
define('SAML_ALLOW_REPEAT_ATTRIBUTE_NAME', true);
define('SAML_SP_ENTITY_ID', 'http://localhost:8080');
define('SAML_SP_ACS_URL', 'http://localhost:8080/saml/acs');
define('SAML_SP_SLO_URL', 'http://localhost:8080/saml/slo');
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
