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

namespace WpPack\Plugin\MonitoringPlugin\DependencyInjection;

use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Monitoring\Bridge\CloudWatch\CloudWatchMetricProvider;
use WpPack\Component\Monitoring\Bridge\CloudWatch\CloudWatchMetricProviderFactory;
use WpPack\Component\Monitoring\DependencyInjection\MonitoringServiceProvider;
use WpPack\Component\Monitoring\MonitoringCollector;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Plugin\MonitoringPlugin\Admin\MonitoringDashboardPage;

final class MonitoringPluginServiceProvider implements ServiceProviderInterface
{
    public function registerAdmin(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(AdminPageRegistry::class)) {
            $builder->register(AdminPageRegistry::class);
        }

        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        $builder->register(MonitoringDashboardPage::class);
    }

    public function register(ContainerBuilder $builder): void
    {
        (new MonitoringServiceProvider())->register($builder);

        if (class_exists(CloudWatchMetricProviderFactory::class)) {
            $builder->register(CloudWatchMetricProviderFactory::class)
                ->setFactory([CloudWatchMetricProviderFactory::class, 'fromEnvironment']);

            $builder->register(CloudWatchMetricProvider::class)
                ->setFactory([new Reference(CloudWatchMetricProviderFactory::class), 'create']);

            $collectorDef = $builder->findDefinition(MonitoringCollector::class);
            $collectorDef->setArgument(1, [
                'cloudwatch' => new Reference(CloudWatchMetricProvider::class),
            ]);
        }
    }
}
