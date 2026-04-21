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
 * Plugin Name:       WPPack Multisite URL Fixer
 * Description:       Fix asset and content URLs for WordPress Multisite
 * Version:           1.0.0
 * Requires PHP:      8.2
 * Requires at least: 6.7
 * Author:            WPPack
 * License:           MIT
 *
 * Fixes asset and content URLs in WordPress Multisite installations
 * running on Bedrock structure (WordPress in /wp subdirectory).
 *
 * Subdirectory mode: /sitename/wp-admin/ → /wp/wp-admin/
 * Subdomain mode:    no URL fixing needed (paths are already correct)
 */

use WPPack\MuPlugin\MultisiteUrlFixer\Subscriber\UrlFixerSubscriber;

if (!is_multisite()) {
    return;
}

$wpPath = \defined('ABSPATH') && str_ends_with(rtrim(ABSPATH, '/'), '/wp') ? '/wp' : '';

if ($wpPath === '') {
    return;
}

// Register filters directly — must be active before init (Kernel boot).
(new UrlFixerSubscriber($wpPath))->register();
