<?php

declare(strict_types=1);

/**
 * Router for PHP built-in server.
 *
 * Usage: php -S localhost:8080 -t web web/router.php
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve wp-content files directly
if (str_starts_with($requestUri, '/wp-content/')) {
    $filePath = __DIR__ . $requestUri;
    if (is_file($filePath)) {
        return false;
    }
}

// Serve WordPress core files directly (wp-admin, wp-login.php, etc.)
if (str_starts_with($requestUri, '/wp/')) {
    $filePath = __DIR__ . $requestUri;

    // Static file
    if (is_file($filePath)) {
        return false;
    }

    // Directory with index.php (e.g. /wp/wp-admin/)
    if (is_dir($filePath) && is_file($filePath . '/index.php')) {
        return false;
    }
}

// Route everything else through WordPress
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
require __DIR__ . '/wp/index.php';
