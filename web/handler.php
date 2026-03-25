<?php

declare(strict_types=1);

/**
 * Front controller for PHP built-in server.
 *
 * Usage: php -S localhost:8080 -t web web/handler.php
 */

use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Handler;
use WpPack\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = new Configuration([
    'web_root' => __DIR__,
    'wordpress_index' => '/wp/index.php',
]);

$request = Request::createFromGlobals();
$handler = new Handler($config);

try {
    $filePath = $handler->resolve($request);
    if ($filePath !== null) {
        require $filePath;
    }
} catch (\Exception $e) {
    $handler->handleException($e);
}
