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

namespace WPPack\Plugin\MonitoringPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Monitoring\DependencyInjection\RegisterMetricBridgesPass;
use WPPack\Component\Monitoring\DependencyInjection\RegisterMetricProvidersPass;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Monitoring\Rest\MonitoringController;
use WPPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Transient\TransientManager;
use WPPack\Plugin\MonitoringPlugin\Admin\MonitoringDashboardPage;
use WPPack\Plugin\MonitoringPlugin\MonitoringPlugin;

#[CoversClass(MonitoringPlugin::class)]
final class MonitoringPluginTest extends TestCase
{
    #[Test]
    public function pluginDeclaresTextDomainAttribute(): void
    {
        $ref = new \ReflectionClass(MonitoringPlugin::class);
        $attr = $ref->getAttributes(TextDomain::class)[0] ?? null;

        self::assertNotNull($attr);
        self::assertSame('wppack-monitoring', $attr->newInstance()->domain);
    }

    #[Test]
    public function getCompilerPassesReturnsExpectedPasses(): void
    {
        $passes = (new MonitoringPlugin(__FILE__))->getCompilerPasses();

        self::assertCount(4, $passes);
        foreach ($passes as $pass) {
            self::assertInstanceOf(CompilerPassInterface::class, $pass);
        }

        $classes = array_map(static fn(CompilerPassInterface $p): string => $p::class, $passes);
        self::assertContains(RegisterMetricProvidersPass::class, $classes);
        self::assertContains(RegisterMetricBridgesPass::class, $classes);
        self::assertContains(RegisterRestControllersPass::class, $classes);
        self::assertContains(RegisterHookSubscribersPass::class, $classes);
    }

    #[Test]
    public function registerPopulatesBuilderWithPluginServices(): void
    {
        $plugin = new MonitoringPlugin(__FILE__);
        $builder = new ContainerBuilder();

        $plugin->register($builder);

        self::assertTrue($builder->hasDefinition(MonitoringRegistry::class));
    }

    #[Test]
    public function getFileReturnsPluginFilePath(): void
    {
        $path = '/fake/wppack-monitoring.php';
        self::assertSame($path, (new MonitoringPlugin($path))->getFile());
    }

    #[Test]
    public function bootWiresDashboardAndRestController(): void
    {
        $registry = new MonitoringRegistry();
        $transients = new TransientManager();
        $collector = new MonitoringCollector($registry, [], $transients);
        $controller = new MonitoringController($collector, $registry);

        $dashboardPage = new MonitoringDashboardPage();

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(AdminPageRegistry::class, new AdminPageRegistry());
        $symfonyContainer->set(MonitoringDashboardPage::class, $dashboardPage);
        $symfonyContainer->set(MonitoringCollector::class, $collector);
        $symfonyContainer->set(RestRegistry::class, new RestRegistry(new Request()));
        $symfonyContainer->set(MonitoringController::class, $controller);

        $container = new Container($symfonyContainer);

        $plugin = new MonitoringPlugin(__FILE__);
        $plugin->boot($container);

        self::assertNotFalse(has_action('admin_menu') ?: has_action('network_admin_menu'));
        self::assertNotFalse(has_action('rest_api_init'));

        remove_all_actions('admin_menu');
        remove_all_actions('network_admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
    }
}
