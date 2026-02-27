<?php

declare(strict_types=1);

// Monorepo root autoload (preferred)
$rootAutoload = __DIR__ . '/../../../../vendor/autoload.php';
$componentAutoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($rootAutoload)) {
    require_once $rootAutoload;

    // Load WordPress bundled PHPMailer from roots/wordpress-no-content
    $wpIncludesDir = __DIR__ . '/../../../../vendor/roots/wordpress-no-content/wp-includes';
    require_once $wpIncludesDir . '/PHPMailer/PHPMailer.php';
    require_once $wpIncludesDir . '/PHPMailer/Exception.php';
    require_once $wpIncludesDir . '/PHPMailer/SMTP.php';
} elseif (file_exists($componentAutoload)) {
    require_once $componentAutoload;
}
