<?php

declare(strict_types=1);

/**
 * Plugin Name: WpPack Amazon Mailer
 * Description: SES-based email delivery with bounce and complaint handling.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * License: MIT
 */

use WpPack\Component\Kernel\Kernel;
use WpPack\Plugin\AmazonMailerPlugin\AmazonMailerPlugin;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

Kernel::registerPlugin(new AmazonMailerPlugin());
