<?php

declare(strict_types=1);

/**
 * Plugin Name: WpPack S3 Storage Plugin
 * Description: S3-based media storage with browser-direct upload support.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * License: MIT
 */

use WpPack\Component\Kernel\Kernel;
use WpPack\Plugin\S3StoragePlugin\S3StoragePlugin;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Requires S3_BUCKET constant or environment variable
if (defined('S3_BUCKET') || getenv('S3_BUCKET') !== false) {
    Kernel::registerPlugin(new S3StoragePlugin(__FILE__));
}
