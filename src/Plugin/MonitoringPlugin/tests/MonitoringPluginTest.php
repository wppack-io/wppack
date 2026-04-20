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
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Monitoring\DependencyInjection\RegisterMetricBridgesPass;
use WPPack\Component\Monitoring\DependencyInjection\RegisterMetricProvidersPass;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
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
}
