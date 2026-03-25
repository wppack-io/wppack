<?php

declare(strict_types=1);

/**
 * Plugin Name: WpPack Debug
 * Description: Debug toolbar and profiler for WordPress development.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * License: MIT
 */

use WpPack\Component\Kernel\Kernel;
use WpPack\Plugin\DebugPlugin\DebugPlugin;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Only register when WP_DEBUG is enabled
if (defined('WP_DEBUG') && WP_DEBUG) {
    Kernel::registerPlugin(new DebugPlugin(__FILE__));
}
