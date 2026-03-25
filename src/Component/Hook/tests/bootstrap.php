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

// Monorepo root autoload (preferred)
$rootAutoload = __DIR__ . '/../../../../vendor/autoload.php';
$componentAutoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($rootAutoload)) {
    require_once $rootAutoload;
} elseif (file_exists($componentAutoload)) {
    require_once $componentAutoload;
}
