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

namespace WpPack\Plugin\MonitoringPlugin;

use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\Attribute\TextDomain;
use WpPack\Component\Monitoring\DependencyInjection\RegisterMetricBridgesPass;
use WpPack\Component\Monitoring\DependencyInjection\RegisterMetricProvidersPass;
use WpPack\Component\Monitoring\MonitoringCollector;
use WpPack\Component\Monitoring\Rest\MonitoringController;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Plugin\MonitoringPlugin\Admin\MonitoringDashboardPage;
use WpPack\Plugin\MonitoringPlugin\DependencyInjection\MonitoringPluginServiceProvider;

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
