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

namespace WpPack\Component\DependencyInjection\Tests\Configurator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Configurator\DefaultsConfigurator;
use WpPack\Component\DependencyInjection\Configurator\ServiceConfigurator;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;

final class ServiceConfiguratorTest extends TestCase
{
    #[Test]
    public function processRegistersNewService(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $configurator = new ServiceConfigurator('app.service', 'App\\Service');

        $configurator->process($builder, $defaults);

        self::assertTrue($builder->hasDefinition('app.service'));
        self::assertSame('App\\Service', $builder->findDefinition('app.service')->getClass());
    }

    #[Test]
    public function processUpdatesExistingService(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('app.service', 'App\\OldService');
        $defaults = new DefaultsConfigurator();
        $configurator = new ServiceConfigurator('app.service', 'App\\NewService');

        $configurator->process($builder, $defaults);

        self::assertSame('App\\NewService', $builder->findDefinition('app.service')->getClass());
    }

    #[Test]
    public function processAppliesDefaults(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $defaults->autowire()->public();
        $configurator = new ServiceConfigurator('app.service');

        $configurator->process($builder, $defaults);

        $definition = $builder->findDefinition('app.service');
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->isPublic());
    }

    #[Test]
    public function processOverridesDefaults(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $defaults->autowire()->public();
        $configurator = new ServiceConfigurator('app.service');
        $configurator->autowire(false)->public(false);

        $configurator->process($builder, $defaults);

        $definition = $builder->findDefinition('app.service');
        self::assertFalse($definition->isAutowired());
        self::assertFalse($definition->isPublic());
    }

    #[Test]
    public function processAppliesLazy(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $configurator = new ServiceConfigurator('app.service');
        $configurator->lazy();

        $configurator->process($builder, $defaults);

        self::assertTrue($builder->findDefinition('app.service')->isLazy());
    }

    #[Test]
    public function processAppliesTags(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $configurator = new ServiceConfigurator('app.service');
        $configurator->tag('app.handler', ['priority' => 10]);

        $configurator->process($builder, $defaults);

        $definition = $builder->findDefinition('app.service');
        self::assertTrue($definition->hasTag('app.handler'));
    }

    #[Test]
    public function processAppliesArgs(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $configurator = new ServiceConfigurator('app.service');
        $configurator->arg('$name', 'value');

        $configurator->process($builder, $defaults);

        $arguments = $builder->findDefinition('app.service')->getArguments();
        self::assertSame('value', $arguments['$name']);
    }

    #[Test]
    public function processAppliesFactory(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $configurator = new ServiceConfigurator('app.service');
        $configurator->factory([new Reference('factory.service'), 'create']);

        $configurator->process($builder, $defaults);

        $factory = $builder->findDefinition('app.service')->getFactory();
        self::assertNotNull($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('factory.service', $factory[0]->getId());
        self::assertSame('create', $factory[1]);
    }

    #[Test]
    public function processAppliesClass(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $configurator = new ServiceConfigurator('app.service', 'App\\MyService');

        $configurator->process($builder, $defaults);

        self::assertSame('App\\MyService', $builder->findDefinition('app.service')->getClass());
    }

    #[Test]
    public function fluentApi(): void
    {
        $configurator = new ServiceConfigurator('app.service');

        $result = $configurator
            ->lazy()
            ->autowire()
            ->public()
            ->tag('my.tag')
            ->arg('$key', 'value')
            ->factory(['Factory', 'create']);

        self::assertSame($configurator, $result);
    }
}
