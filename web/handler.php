<?php

declare(strict_types=1);

/**
 * Front controller for PHP built-in server.
 *
 * Usage: php -S localhost:8080 -t web web/handler.php
 */

use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Handler;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = new Configuration([
    'web_root' => __DIR__,
    'wordpress_index' => '/wp/index.php',
]);

$result = (new Handler($config))->run();
if ($result !== null) {
    require $result;
}
