<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * Plugin Name: WPPack Monitoring
 * Description: Infrastructure monitoring dashboard.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Requires at least: 6.7
 * Author: WPPack
 * License: MIT
 */

use WPPack\Component\Kernel\Kernel;
use WPPack\Plugin\MonitoringPlugin\MonitoringPlugin;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

Kernel::registerPlugin(new MonitoringPlugin(__FILE__));
