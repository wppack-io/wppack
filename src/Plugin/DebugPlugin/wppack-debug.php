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

/**
 * Plugin Name: WpPack Debug
 * Description: Debug toolbar and profiler for WordPress development.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Requires at least: 6.9
 * Author: WpPack
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
