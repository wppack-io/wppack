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
 * Plugin Name:       WpPack Theme Directory
 * Description:       Register default theme directory
 * Version:           1.0.0
 * Requires PHP:      8.2
 * Requires at least: 6.9
 * Author:            WpPack
 * License:           MIT
 */

if (!defined('WP_DEFAULT_THEME')) {
    register_theme_directory(ABSPATH . 'wp-content/themes');
}
