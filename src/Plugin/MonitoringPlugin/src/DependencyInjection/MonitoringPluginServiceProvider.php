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

namespace WPPack\Plugin\MonitoringPlugin\DependencyInjection;

use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Monitoring\Bridge\CloudWatch\DependencyInjection\CloudWatchServiceProvider;
use WPPack\Component\Monitoring\DependencyInjection\MonitoringServiceProvider;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\MonitoringPlugin\Admin\MonitoringDashboardPage;
use WPPack\Plugin\MonitoringPlugin\Rest\SyncTemplatesController;
use WPPack\Plugin\MonitoringPlugin\Template\MetricTemplateRegistry;

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
        $builder->register(MetricTemplateRegistry::class);
        $builder->register(SyncTemplatesController::class)
            ->addArgument(new Reference(\WPPack\Component\Monitoring\MonitoringStore::class))
            ->addArgument(new Reference(MetricTemplateRegistry::class));
    }

    public function register(ContainerBuilder $builder): void
    {
        (new MonitoringServiceProvider())->register($builder);
        (new CloudWatchServiceProvider())->register($builder);
    }
}
