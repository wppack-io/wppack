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
use WPPack\Component\Monitoring\DependencyInjection\MonitoringServiceProvider;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Monitoring\MonitoringStore;
use WPPack\Component\Monitoring\Rest\MonitoringController;
use WPPack\Component\Monitoring\Rest\MonitoringSettingsController;
use WPPack\Component\Option\OptionManager;
use WPPack\Component\Transient\TransientManager;

#[CoversClass(MonitoringServiceProvider::class)]
final class MonitoringServiceProviderTest extends TestCase
{
    #[Test]
    public function registersCoreServices(): void
    {
        $builder = new ContainerBuilder();

        (new MonitoringServiceProvider())->register($builder);

        self::assertTrue($builder->hasDefinition(OptionManager::class));
        self::assertTrue($builder->hasDefinition(TransientManager::class));
        self::assertTrue($builder->hasDefinition(MonitoringRegistry::class));
        self::assertTrue($builder->hasDefinition(MonitoringStore::class));
        self::assertTrue($builder->hasDefinition(MonitoringCollector::class));
        self::assertTrue($builder->hasDefinition(MonitoringController::class));
        self::assertTrue($builder->hasDefinition(MonitoringSettingsController::class));
    }

    #[Test]
    public function doesNotOverrideExistingOptionManager(): void
    {
        $builder = new ContainerBuilder();
        $existing = $builder->register(OptionManager::class);

        (new MonitoringServiceProvider())->register($builder);

        self::assertSame($existing, $builder->findDefinition(OptionManager::class));
    }

    #[Test]
    public function tagsRestControllers(): void
    {
        $builder = new ContainerBuilder();

        (new MonitoringServiceProvider())->register($builder);

        $taggedIds = array_keys($builder->findTaggedServiceIds('rest.controller'));

        self::assertContains(MonitoringController::class, $taggedIds);
        self::assertContains(MonitoringSettingsController::class, $taggedIds);
    }

    #[Test]
    public function cacheTtlArgumentIsForwardedToCollector(): void
    {
        $builder = new ContainerBuilder();

        (new MonitoringServiceProvider(cacheTtl: 7200))->register($builder);

        $definition = $builder->findDefinition(MonitoringCollector::class);
        $args = $definition->getArguments();

        // Args: registry, providers[], transients, cacheTtl
        self::assertSame(7200, $args[3]);
    }
}
