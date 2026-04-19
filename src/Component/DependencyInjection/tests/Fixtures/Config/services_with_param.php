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

return static function (ContainerConfigurator $services): void {
    $services->param('app.name', 'TestApp');
};
