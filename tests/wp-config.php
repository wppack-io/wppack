<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . '/web/wp/');

// DATABASE_DSN drives the db.php drop-in if set (matrix-driven CI testing).
// When unset, the db.php drop-in falls back to auto-building a MySQL DSN
// from the DB_* constants below ("wpdb" variant path).
$wppackTestDatabaseDsn = $_SERVER['DATABASE_DSN'] ?? $_ENV['DATABASE_DSN'] ?? '';
if ($wppackTestDatabaseDsn !== '') {
    define('DATABASE_DSN', $wppackTestDatabaseDsn);
}
unset($wppackTestDatabaseDsn);

define('DB_NAME', 'wppack_test');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1:' . ($_SERVER['WPPACK_TEST_DB_PORT'] ?? '3307'));
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
