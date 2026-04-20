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

namespace WPPack\Plugin\MonitoringPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Monitoring\MonitoringStore;
use WPPack\Plugin\MonitoringPlugin\Admin\MonitoringDashboardPage;
use WPPack\Plugin\MonitoringPlugin\DependencyInjection\MonitoringPluginServiceProvider;
use WPPack\Plugin\MonitoringPlugin\Rest\SyncTemplatesController;
use WPPack\Plugin\MonitoringPlugin\Template\MetricTemplateRegistry;

#[CoversClass(MonitoringPluginServiceProvider::class)]
final class MonitoringPluginServiceProviderTest extends TestCase
{
    #[Test]
    public function registerAdminRegistersDashboardAndTemplates(): void
    {
        $builder = new ContainerBuilder();

        (new MonitoringPluginServiceProvider())->registerAdmin($builder);

        self::assertTrue($builder->hasDefinition(AdminPageRegistry::class));
        self::assertTrue($builder->hasDefinition(MonitoringDashboardPage::class));
        self::assertTrue($builder->hasDefinition(MetricTemplateRegistry::class));
        self::assertTrue($builder->hasDefinition(SyncTemplatesController::class));
    }

    #[Test]
    public function registerComposesMonitoringAndCloudWatchProviders(): void
    {
        $builder = new ContainerBuilder();

        (new MonitoringPluginServiceProvider())->register($builder);

        // MonitoringServiceProvider exposes the core registry/store/collector
        self::assertTrue($builder->hasDefinition(MonitoringRegistry::class));
        self::assertTrue($builder->hasDefinition(MonitoringStore::class));
        self::assertTrue($builder->hasDefinition(MonitoringCollector::class));

        // CloudWatchServiceProvider tags at least one discovery as monitoring.provider
        $tagged = $builder->findTaggedServiceIds('monitoring.provider');
        self::assertNotEmpty($tagged);
    }

    #[Test]
    public function preExistingAdminRegistryIsReused(): void
    {
        $builder = new ContainerBuilder();
        $existing = $builder->register(AdminPageRegistry::class);

        (new MonitoringPluginServiceProvider())->registerAdmin($builder);

        self::assertSame($existing, $builder->findDefinition(AdminPageRegistry::class));
    }
}
