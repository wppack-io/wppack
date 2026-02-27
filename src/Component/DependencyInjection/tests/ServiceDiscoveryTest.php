<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceDiscovery;
use WpPack\Component\DependencyInjection\Tests\Fixtures\AbstractService;
use WpPack\Component\DependencyInjection\Tests\Fixtures\DependentService;
use WpPack\Component\DependencyInjection\Tests\Fixtures\LazyService;
use WpPack\Component\DependencyInjection\Tests\Fixtures\PlainClass;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SampleImplementation;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SampleInterface;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;
use WpPack\Component\DependencyInjection\Tests\Fixtures\TaggedService;

final class ServiceDiscoveryTest extends TestCase
{
    #[Test]
    public function discoversAnnotatedServices(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        self::assertTrue($builder->hasDefinition(SimpleService::class));
        self::assertTrue($builder->hasDefinition(DependentService::class));
        self::assertTrue($builder->hasDefinition(TaggedService::class));
        self::assertTrue($builder->hasDefinition(LazyService::class));
    }

    #[Test]
    public function skipsPlainClasses(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        self::assertFalse($builder->hasDefinition(PlainClass::class));
    }

    #[Test]
    public function skipsAbstractClasses(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        self::assertFalse($builder->hasDefinition(AbstractService::class));
    }

    #[Test]
    public function skipsInterfaces(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        self::assertFalse($builder->hasDefinition(SampleInterface::class));
    }

    #[Test]
    public function setsLazyFlag(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        $definition = $builder->findDefinition(LazyService::class);
        self::assertTrue($definition->isLazy());
    }

    #[Test]
    public function registersTagsFromAttribute(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        $tagged = $builder->findTaggedServiceIds('app.handler');
        self::assertArrayHasKey(TaggedService::class, $tagged);
    }

    #[Test]
    public function registersAliases(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        self::assertTrue($builder->hasDefinition(SampleImplementation::class));
        // Register dependencies needed by discovered fixtures
        $builder->register('custom.service', SimpleService::class);
        $builder->setParameter('app.name', 'TestApp');
        // Alias is registered so it resolves at compile time
        $container = $builder->compile();
        self::assertTrue($container->has(SampleInterface::class));
    }

    #[Test]
    public function setsAutowiredByDefault(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        $definition = $builder->findDefinition(SimpleService::class);
        self::assertTrue($definition->isAutowired());
    }
}
