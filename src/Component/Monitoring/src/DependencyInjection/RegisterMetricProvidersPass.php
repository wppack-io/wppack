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

namespace WpPack\Component\Monitoring\DependencyInjection;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Monitoring\MonitoringRegistry;

final class RegisterMetricProvidersPass implements CompilerPassInterface
{
    public const TAG = 'monitoring.provider';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(MonitoringRegistry::class)) {
            return;
        }

        $registryDefinition = $builder->findDefinition(MonitoringRegistry::class);

        foreach ($builder->findTaggedServiceIds(self::TAG) as $serviceId => $tags) {
            $registryDefinition->addMethodCall('addFromSource', [new Reference($serviceId)]);
        }
    }
}
