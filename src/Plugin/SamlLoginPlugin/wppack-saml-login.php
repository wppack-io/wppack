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
 * Plugin Name: WpPack SAML Login
 * Description: SAML 2.0 SSO authentication for WordPress.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Requires at least: 6.9
 * Author: WpPack
 * License: MIT
 */

use WpPack\Component\Kernel\Kernel;
use WpPack\Plugin\SamlLoginPlugin\SamlLoginPlugin;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Requires SAML_IDP_ENTITY_ID constant or environment variable
if (defined('SAML_IDP_ENTITY_ID') || getenv('SAML_IDP_ENTITY_ID') !== false) {
    Kernel::registerPlugin(new SamlLoginPlugin(__FILE__));
}
