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

namespace WPPack\Plugin\MonitoringPlugin;

use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\Kernel\AbstractPlugin;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Monitoring\DependencyInjection\RegisterMetricBridgesPass;
use WPPack\Component\Monitoring\DependencyInjection\RegisterMetricProvidersPass;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\Rest\MonitoringController;
use WPPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\MonitoringPlugin\Admin\MonitoringDashboardPage;
use WPPack\Plugin\MonitoringPlugin\DependencyInjection\MonitoringPluginServiceProvider;

#[TextDomain(domain: 'wppack-monitoring')]
final class MonitoringPlugin extends AbstractPlugin
{
    private readonly MonitoringPluginServiceProvider $serviceProvider;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new MonitoringPluginServiceProvider();
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->serviceProvider->registerAdmin($builder);
        $this->serviceProvider->register($builder);
    }

    /**
     * @return CompilerPassInterface[]
     */
    public function getCompilerPasses(): array
    {
        return [
            new RegisterMetricProvidersPass(),
            new RegisterMetricBridgesPass(),
            new RegisterRestControllersPass(),
            new RegisterHookSubscribersPass(),
        ];
    }

    public function boot(Container $container): void
    {
        /** @var AdminPageRegistry $pageRegistry */
        $pageRegistry = $container->get(AdminPageRegistry::class);
        /** @var MonitoringDashboardPage $dashboardPage */
        $dashboardPage = $container->get(MonitoringDashboardPage::class);
        $dashboardPage->setPluginFile($this->getFile());
        /** @var MonitoringCollector $collector */
        $collector = $container->get(MonitoringCollector::class);
        $dashboardPage->setCollector($collector);
        $pageRegistry->register($dashboardPage, $this->isNetworkActivated());

        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(MonitoringController::class));
    }
}
