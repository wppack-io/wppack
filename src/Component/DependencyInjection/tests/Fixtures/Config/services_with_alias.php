<?php

declare(strict_types=1);

use WpPack\Component\DependencyInjection\Configurator\ContainerConfigurator;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;

return static function (ContainerConfigurator $services): void {
    $services->set(SimpleService::class);
    $services->alias('app.simple', SimpleService::class);
};
