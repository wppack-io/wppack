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
 * Plugin Name: WpPack SCIM
 * Description: SCIM 2.0 user provisioning for WordPress.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Requires at least: 6.9
 * Author: WpPack
 * License: MIT
 */

use WpPack\Component\Kernel\Kernel;
use WpPack\Plugin\ScimPlugin\ScimPlugin;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Requires SCIM_BEARER_TOKEN constant or environment variable
if (defined('SCIM_BEARER_TOKEN') || getenv('SCIM_BEARER_TOKEN') !== false) {
    Kernel::registerPlugin(new ScimPlugin(__FILE__));
}
