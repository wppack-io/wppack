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
 * WordPress Multisite — subdirectory installation.
 */

use WPPack\Component\Handler\Configuration;
use WPPack\Component\Handler\Handler;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$config = new Configuration([
    'web_root' => __DIR__,
    'multisite' => true, // Uses default pattern: #^/[_0-9a-zA-Z-]+(/wp-.*)#
]);

$result = (new Handler($config))->run();
if ($result !== null) {
    require $result;
}
