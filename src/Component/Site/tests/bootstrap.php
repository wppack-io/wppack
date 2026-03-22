<?php

declare(strict_types=1);

$rootBootstrap = __DIR__ . '/../../../../tests/bootstrap.php';
$componentAutoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($rootBootstrap)) {
    require_once $rootBootstrap;
} elseif (file_exists($componentAutoload)) {
    require_once $componentAutoload;
}
