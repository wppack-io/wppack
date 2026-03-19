<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// WordPress integration tests: bootstrap WordPress via wp-phpunit
putenv('WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-config.php');

$_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';

require_once $_tests_dir . '/includes/functions.php';

// WP 6.8+: suppress _doing_it_wrong notice for wp_is_block_theme() called before
// theme directory registration during WP bootstrap (WordPress core issue).
tests_add_filter('doing_it_wrong_trigger_error', static function (bool $trigger, string $function): bool {
    if ($function === 'wp_is_block_theme') {
        return false;
    }

    return $trigger;
}, 10, 2);

require_once $_tests_dir . '/includes/bootstrap.php';

// Load admin/core includes required by component tests
$extraIncludes = [
    // Admin includes (DashboardWidget, etc.)
    ABSPATH . 'wp-admin/includes/dashboard.php',
    ABSPATH . 'wp-admin/includes/template.php',
    ABSPATH . 'wp-admin/includes/screen.php',
    // DependencyInjection: WP_Filesystem_Base
    ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php',
    // DependencyInjection: WP_Admin_Bar
    ABSPATH . WPINC . '/class-wp-admin-bar.php',
];
foreach ($extraIncludes as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}
