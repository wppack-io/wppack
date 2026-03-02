<?php

declare(strict_types=1);

use WpPack\Component\DependencyInjection\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $services): void {
    $services->defaults()->autowire()->public();
    $services->load(
        'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\',
        dirname(__DIR__),
    );
};
