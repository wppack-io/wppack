<?php

declare(strict_types=1);

/**
 * AWS Lambda deployment — force Lambda mode with custom directories.
 */

use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Handler;
use WpPack\Component\HttpFoundation\Request;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$config = new Configuration([
    'web_root' => __DIR__,
    'lambda' => [
        'enabled' => true,
        'directories' => [
            '/tmp/uploads',
            '/tmp/cache',
            '/tmp/sessions',
            '/tmp/wflogs',
        ],
    ],
]);

$request = Request::createFromGlobals();
(new Handler($config))->handle($request);
