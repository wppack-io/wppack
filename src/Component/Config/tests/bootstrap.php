<?php

declare(strict_types=1);

// Monorepo root autoload (preferred)
$rootAutoload = __DIR__ . '/../../../../vendor/autoload.php';
$componentAutoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($rootAutoload)) {
    require_once $rootAutoload;
} elseif (file_exists($componentAutoload)) {
    require_once $componentAutoload;
}
