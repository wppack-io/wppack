<?php

declare(strict_types=1);

/**
 * WordPress Multisite — subdirectory installation.
 */

use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Handler;
use WpPack\Component\HttpFoundation\Request;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$config = new Configuration([
    'web_root' => __DIR__,
    'multisite' => true, // Uses default pattern: #^/[_0-9a-zA-Z-]+(/wp-.*)#
]);

$request = Request::createFromGlobals();
(new Handler($config))->handle($request);
