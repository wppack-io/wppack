<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$wpConfigPath = __DIR__ . '/wp-config.php';

if (file_exists($wpConfigPath)) {
    // WordPress integration tests: bootstrap WordPress via wp-phpunit
    putenv('WP_PHPUNIT__TESTS_CONFIG=' . $wpConfigPath);

    $_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';

    require_once $_tests_dir . '/includes/functions.php';
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
} else {
    // Unit tests only: load PHPMailer from roots/wordpress-no-content
    $wpIncludesDir = __DIR__ . '/../vendor/roots/wordpress-no-content/wp-includes';

    require_once $wpIncludesDir . '/PHPMailer/PHPMailer.php';
    require_once $wpIncludesDir . '/PHPMailer/Exception.php';
    require_once $wpIncludesDir . '/PHPMailer/SMTP.php';
}
