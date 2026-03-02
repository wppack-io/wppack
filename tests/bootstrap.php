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
} else {
    // Unit tests only: load PHPMailer from roots/wordpress-no-content
    $wpIncludesDir = __DIR__ . '/../vendor/roots/wordpress-no-content/wp-includes';

    require_once $wpIncludesDir . '/PHPMailer/PHPMailer.php';
    require_once $wpIncludesDir . '/PHPMailer/Exception.php';
    require_once $wpIncludesDir . '/PHPMailer/SMTP.php';
}
