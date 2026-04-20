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

namespace WPPack\Component\Monitoring\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Monitoring\DependencyInjection\RegisterMetricBridgesPass;
use WPPack\Component\Monitoring\DependencyInjection\RegisterMetricProvidersPass;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Monitoring\MonitoringStore;

#[CoversClass(RegisterMetricBridgesPass::class)]
#[CoversClass(RegisterMetricProvidersPass::class)]
final class CompilerPassesTest extends TestCase
{
    // ── RegisterMetricBridgesPass ──────────────────────────────────────

    #[Test]
    public function bridgesPassExitsWhenNothingTagged(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(MonitoringCollector::class);

        (new RegisterMetricBridgesPass())->process($builder);

        // No arg 1 should be set
        $args = $builder->findDefinition(MonitoringCollector::class)->getArguments();
        self::assertArrayNotHasKey(1, $args);
    }

    #[Test]
    public function bridgesPassInjectsKeyedBridgeMapIntoCollector(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(MonitoringCollector::class)
            ->addArgument('placeholder-0')
            ->addArgument('placeholder-1');
        $builder->register('app.cloudwatch')->addTag('monitoring.bridge', ['name' => 'cloudwatch']);
        $builder->register('app.cloudflare')->addTag('monitoring.bridge', ['name' => 'cloudflare']);

        (new RegisterMetricBridgesPass())->process($builder);

        $args = $builder->findDefinition(MonitoringCollector::class)->getArguments();
        self::assertIsArray($args[1]);
        self::assertArrayHasKey('cloudwatch', $args[1]);
        self::assertArrayHasKey('cloudflare', $args[1]);
    }

    #[Test]
    public function bridgesPassInjectsOrderedListIntoStore(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(MonitoringStore::class)
            ->addArgument('placeholder-0')
            ->addArgument('placeholder-1');
        $builder->register('app.cloudwatch')->addTag('monitoring.bridge', ['name' => 'cloudwatch']);

        (new RegisterMetricBridgesPass())->process($builder);

        $args = $builder->findDefinition(MonitoringStore::class)->getArguments();
        self::assertIsArray($args[1]);
        self::assertCount(1, $args[1]);
    }

    #[Test]
    public function bridgesPassTagWithoutNameFallsBackToServiceId(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(MonitoringCollector::class)
            ->addArgument('placeholder-0')
            ->addArgument('placeholder-1');
        $builder->register('app.untaggedNamedBridge')->addTag('monitoring.bridge');

        (new RegisterMetricBridgesPass())->process($builder);

        $args = $builder->findDefinition(MonitoringCollector::class)->getArguments();
        self::assertArrayHasKey('app.untaggedNamedBridge', $args[1]);
    }

    // ── RegisterMetricProvidersPass ────────────────────────────────────

    #[Test]
    public function providersPassExitsWhenRegistryAbsent(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('app.source')->addTag('monitoring.provider');

        // Should not throw
        (new RegisterMetricProvidersPass())->process($builder);

        self::assertFalse($builder->hasDefinition(MonitoringRegistry::class));
    }

    #[Test]
    public function providersPassRegistersSourcesAsAddFromSourceCalls(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(MonitoringRegistry::class);
        $builder->register('app.source.a')->addTag('monitoring.provider');
        $builder->register('app.source.b')->addTag('monitoring.provider');

        (new RegisterMetricProvidersPass())->process($builder);

        $calls = $builder->findDefinition(MonitoringRegistry::class)->getMethodCalls();
        self::assertCount(2, $calls);
        foreach ($calls as $call) {
            self::assertSame('addFromSource', $call['method']);
        }
    }
}
