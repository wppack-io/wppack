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
 * Basic usage — minimal front controller.
 *
 * Place this file as web/index.php and point your web server here.
 */

use WpPack\Component\Handler\Handler;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$result = (new Handler())->run();
if ($result !== null) {
    require $result;
}
