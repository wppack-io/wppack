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

use WPPack\Component\DependencyInjection\Configurator\ContainerConfigurator;
use WPPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;

return static function (ContainerConfigurator $services): void {
    $services->defaults()->autowire()->public();
    $services->set(SimpleService::class);
    $services->param('app.name', 'TestApp');
};
