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
 * Bootstrap for demo pages.
 *
 * Loads Composer autoloader and WordPress core.
 * Requires MySQL to be running (docker compose up -d --wait).
 */

$rootDir = dirname(__DIR__, 4);

require_once $rootDir . '/vendor/autoload.php';

// Define WordPress constants before loading wp-settings.php
define('ABSPATH', $rootDir . '/web/wp/');

define('DB_NAME', 'wppack_test');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

define('WP_DEBUG', true);
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_DEFAULT_THEME', 'default');

// Suppress WP trying to send headers/redirect during bootstrap
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';

require_once ABSPATH . 'wp-settings.php';
