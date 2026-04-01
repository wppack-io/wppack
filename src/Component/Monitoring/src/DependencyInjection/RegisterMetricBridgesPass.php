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
use WpPack\Component\Monitoring\MonitoringCollector;

final class RegisterMetricBridgesPass implements CompilerPassInterface
{
    public const TAG = 'monitoring.bridge';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(MonitoringCollector::class)) {
            return;
        }

        $collectorDef = $builder->findDefinition(MonitoringCollector::class);
        $bridges = [];

        foreach ($builder->findTaggedServiceIds(self::TAG) as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $name = $attributes['name'] ?? $serviceId;
                $bridges[$name] = new Reference($serviceId);
            }
        }

        if ($bridges !== []) {
            $collectorDef->setArgument(1, $bridges);
        }
    }
}
