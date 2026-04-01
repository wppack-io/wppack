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

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Monitoring\Bridge\CloudWatch\CloudWatchMetricProvider;
use WpPack\Component\Monitoring\MonitoringCollector;
use WpPack\Component\Monitoring\MonitoringRegistry;
use WpPack\Component\Monitoring\MonitoringStore;
use WpPack\Component\Monitoring\Rest\MonitoringController;
use WpPack\Component\Monitoring\Rest\MonitoringSettingsController;
use WpPack\Component\Option\OptionManager;
use WpPack\Component\Transient\TransientManager;

final class MonitoringServiceProvider implements ServiceProviderInterface
{
    public function __construct(
        private readonly int $cacheTtl = 300,
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        // OptionManager
        if (!$builder->hasDefinition(OptionManager::class)) {
            $builder->register(OptionManager::class);
        }

        // TransientManager
        if (!$builder->hasDefinition(TransientManager::class)) {
            $builder->register(TransientManager::class);
        }

        // Registry
        $builder->register(MonitoringRegistry::class);

        // Store (MonitoringProviderInterface — user-managed providers)
        $builder->register(MonitoringStore::class)
            ->addArgument(new Reference(OptionManager::class))
            ->addTag(RegisterMetricProvidersPass::TAG);

        // Collector
        $builder->register(MonitoringCollector::class)
            ->addArgument(new Reference(MonitoringRegistry::class))
            ->addArgument([])
            ->addArgument(new Reference(TransientManager::class))
            ->addArgument($this->cacheTtl);

        // Auto-discover bridges
        if (class_exists(CloudWatchMetricProvider::class)) {
            $builder->register(CloudWatchMetricProvider::class)
                ->addTag(RegisterMetricBridgesPass::TAG, ['name' => 'cloudwatch']);
        }

        // REST controllers
        $builder->register(MonitoringController::class)
            ->addArgument(new Reference(MonitoringCollector::class))
            ->addArgument(new Reference(MonitoringRegistry::class))
            ->addTag('rest.controller');

        $builder->register(MonitoringSettingsController::class)
            ->addArgument(new Reference(MonitoringStore::class))
            ->addArgument(new Reference(MonitoringRegistry::class))
            ->addTag('rest.controller');
    }
}
