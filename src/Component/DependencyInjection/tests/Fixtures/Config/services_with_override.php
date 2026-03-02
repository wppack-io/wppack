<?php

declare(strict_types=1);

use WpPack\Component\DependencyInjection\Configurator\ContainerConfigurator;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;

return static function (ContainerConfigurator $services): void {
    $services->defaults()->autowire()->public();
    $services->load(
        'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\',
        dirname(__DIR__),
    )->exclude('Config/*');
    $services->set(SimpleService::class)->lazy();
};
