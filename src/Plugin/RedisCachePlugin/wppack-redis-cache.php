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
 * Plugin Name: WpPack Redis Cache
 * Description: Redis-based object cache with ElastiCache IAM auth support.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * License: MIT
 */

use WpPack\Component\Kernel\Kernel;
use WpPack\Plugin\RedisCachePlugin\RedisCachePlugin;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Requires WPPACK_CACHE_DSN constant or environment variable
if (defined('WPPACK_CACHE_DSN') || getenv('WPPACK_CACHE_DSN') !== false) {
    Kernel::registerPlugin(new RedisCachePlugin(__FILE__));
}
