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
 * Plugin Name: WpPack OAuth Login
 * Description: OAuth 2.0 / OpenID Connect multi-provider login for WordPress.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Requires at least: 6.9
 * Author: WpPack
 * License: MIT
 */

use WpPack\Component\Kernel\Kernel;
use WpPack\Plugin\OAuthLoginPlugin\OAuthLoginPlugin;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Requires OAUTH_PROVIDERS constant
if (defined('OAUTH_PROVIDERS') && is_array(constant('OAUTH_PROVIDERS'))) {
    Kernel::registerPlugin(new OAuthLoginPlugin(__FILE__));
}
