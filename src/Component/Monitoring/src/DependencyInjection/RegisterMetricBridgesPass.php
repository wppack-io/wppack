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

namespace WPPack\Component\Monitoring\DependencyInjection;

use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringStore;

final class RegisterMetricBridgesPass implements CompilerPassInterface
{
    public const TAG = 'monitoring.bridge';

    public function process(ContainerBuilder $builder): void
    {
        $bridges = [];

        foreach ($builder->findTaggedServiceIds(self::TAG) as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $name = $attributes['name'] ?? $serviceId;
                $bridges[$name] = new Reference($serviceId);
            }
        }

        if ($bridges === []) {
            return;
        }

        // Inject bridges into MonitoringCollector
        if ($builder->hasDefinition(MonitoringCollector::class)) {
            $builder->findDefinition(MonitoringCollector::class)->setArgument(1, $bridges);
        }

        // Inject bridges into MonitoringStore (for settings class resolution)
        if ($builder->hasDefinition(MonitoringStore::class)) {
            $builder->findDefinition(MonitoringStore::class)->setArgument(1, array_values($bridges));
        }
    }
}
