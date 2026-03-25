<?php

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . '/web/wp/');

define('DB_NAME', 'wppack_test');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'WpPack Tests');
define('WP_PHP_BINARY', 'php');
define('WPLANG', '');

define('WP_DEBUG', true);
define('WP_ENVIRONMENT_TYPE', 'local');

define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills');

// WP 6.8+: Pre-populate $wp_theme_directories so wp_is_block_theme()
// does not trigger a _doing_it_wrong notice during wp-settings.php load.
// This is needed because install.php (run as a separate process) loads
// wp-settings.php before calling register_theme_directory().
$GLOBALS['wp_theme_directories'] = [__DIR__ . '/../vendor/wp-phpunit/wp-phpunit/data/themedir1'];
