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
 * Plugin Name:  WpPack Multisite URL Fixer
 * Description:  Fix asset and content URLs for WordPress Multisite
 * Version:      1.0.0
 * Author:       WpPack
 * License:      MIT License
 *
 * Fixes asset and content URLs in WordPress Multisite installations
 * running on Bedrock structure (WordPress in /wp subdirectory).
 *
 * Subdirectory mode: /sitename/wp-admin/ → /wp/wp-admin/
 * Subdomain mode:    no URL fixing needed (paths are already correct)
 */

use WpPack\Component\Kernel\Kernel;
use WpPack\MuPlugin\MultisiteUrlFixer\MultisiteUrlFixerPlugin;

if (!is_multisite()) {
    return;
}

$wpPath = \defined('ABSPATH') && str_ends_with(rtrim(ABSPATH, '/'), '/wp') ? '/wp' : '';

if ($wpPath === '') {
    return;
}

Kernel::registerPlugin(new MultisiteUrlFixerPlugin(__FILE__, $wpPath));
