<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load WordPress bundled PHPMailer from roots/wordpress-no-content
$wpIncludesDir = __DIR__ . '/../vendor/roots/wordpress-no-content/wp-includes';

require_once $wpIncludesDir . '/PHPMailer/PHPMailer.php';
require_once $wpIncludesDir . '/PHPMailer/Exception.php';
require_once $wpIncludesDir . '/PHPMailer/SMTP.php';
