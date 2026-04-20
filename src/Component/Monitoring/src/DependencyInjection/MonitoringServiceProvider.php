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

use Psr\Log\LoggerInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Monitoring\Bridge\Cloudflare\CloudflareMetricProvider;
use WPPack\Component\Monitoring\Bridge\Cloudflare\MockCloudflareMonitoringProvider;
use WPPack\Component\Monitoring\Bridge\CloudWatch\CloudWatchMetricProvider;
use WPPack\Component\Monitoring\Bridge\CloudWatch\MockCloudWatchMonitoringProvider;
use WPPack\Component\Monitoring\MockMetricProvider;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Monitoring\MonitoringStore;
use WPPack\Component\Monitoring\Rest\MonitoringController;
use WPPack\Component\Monitoring\Rest\MonitoringSettingsController;
use WPPack\Component\Option\OptionManager;
use WPPack\Component\Transient\TransientManager;

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

        $hasLogger = $builder->hasDefinition(LoggerInterface::class);

        // Store (MonitoringProviderInterface — user-managed providers)
        $storeDefinition = $builder->register(MonitoringStore::class)
            ->addArgument(new Reference(OptionManager::class))
            ->addTag(RegisterMetricProvidersPass::TAG);
        if ($hasLogger) {
            $storeDefinition->addArgument(new Reference(LoggerInterface::class));
        }

        // Collector
        $collectorDefinition = $builder->register(MonitoringCollector::class)
            ->addArgument(new Reference(MonitoringRegistry::class))
            ->addArgument([])
            ->addArgument(new Reference(TransientManager::class))
            ->addArgument($this->cacheTtl);
        if ($hasLogger) {
            $collectorDefinition->addArgument(new Reference(LoggerInterface::class));
        }

        // Auto-discover bridges
        if (class_exists(CloudWatchMetricProvider::class)) {
            $cwDefinition = $builder->register(CloudWatchMetricProvider::class)
                ->addTag(RegisterMetricBridgesPass::TAG, ['name' => 'cloudwatch']);
            if ($hasLogger) {
                $cwDefinition->addArgument(new Reference(LoggerInterface::class));
            }
        }

        if (class_exists(CloudflareMetricProvider::class)) {
            $cfDefinition = $builder->register(CloudflareMetricProvider::class)
                ->addTag(RegisterMetricBridgesPass::TAG, ['name' => 'cloudflare']);
            if ($hasLogger) {
                $cfDefinition->addArgument(new Reference(LoggerInterface::class));
            }
        }

        // Mock bridges + sample providers for local development
        if (wp_get_environment_type() === 'local') {
            if (class_exists(MockMetricProvider::class)) {
                $builder->register(MockMetricProvider::class)
                    ->addTag(RegisterMetricBridgesPass::TAG, ['name' => 'mock-aws'])
                    ->addTag(RegisterMetricBridgesPass::TAG, ['name' => 'mock-cloudflare']);
            }

            if (class_exists(MockCloudWatchMonitoringProvider::class)) {
                $builder->register(MockCloudWatchMonitoringProvider::class)
                    ->addTag(RegisterMetricProvidersPass::TAG);
            }

            if (class_exists(MockCloudflareMonitoringProvider::class)) {
                $builder->register(MockCloudflareMonitoringProvider::class)
                    ->addTag(RegisterMetricProvidersPass::TAG);
            }
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
