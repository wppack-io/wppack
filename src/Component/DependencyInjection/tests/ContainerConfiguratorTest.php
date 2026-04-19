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

namespace WPPack\Component\DependencyInjection\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;

final class ContainerConfiguratorTest extends TestCase
{
    #[Test]
    public function loadsConfigFile(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services.php');

        self::assertTrue($builder->hasDefinition(SimpleService::class));
    }

    #[Test]
    public function appliesDefaults(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_with_defaults.php');

        $definition = $builder->findDefinition(SimpleService::class);
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->isPublic());
    }

    #[Test]
    public function loadDiscoversAllClasses(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_with_load.php');

        self::assertTrue($builder->hasDefinition(SimpleService::class));
        self::assertTrue($builder->hasDefinition(Fixtures\DependentService::class));
    }

    #[Test]
    public function excludePatternsWork(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_with_exclude.php');

        self::assertTrue($builder->hasDefinition(SimpleService::class));
        self::assertFalse($builder->hasDefinition(Fixtures\LazyService::class));
    }

    #[Test]
    public function setOverridesDiscoveredService(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_with_override.php');

        $definition = $builder->findDefinition(SimpleService::class);
        self::assertTrue($definition->isLazy());
    }

    #[Test]
    public function setLazy(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_set_lazy.php');

        $definition = $builder->findDefinition(SimpleService::class);
        self::assertTrue($definition->isLazy());
    }

    #[Test]
    public function setTag(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_set_tag.php');

        $tagged = $builder->findTaggedServiceIds('app.handler');
        self::assertArrayHasKey(SimpleService::class, $tagged);
    }

    #[Test]
    public function setArg(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_set_arg.php');

        $definition = $builder->findDefinition(SimpleService::class);
        $args = $definition->getArguments();
        self::assertSame('test_value', $args['$name']);
    }

    #[Test]
    public function setFactory(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_set_factory.php');

        $definition = $builder->findDefinition(SimpleService::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(SimpleService::class, $factory[0]);
        self::assertSame('create', $factory[1]);
    }

    #[Test]
    public function aliasRegistersAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_with_alias.php');

        self::assertTrue($builder->getSymfonyBuilder()->hasAlias('app.simple'));
    }

    #[Test]
    public function paramSetsParameter(): void
    {
        $builder = new ContainerBuilder();
        $builder->loadConfig(__DIR__ . '/Fixtures/Config/services_with_param.php');

        self::assertSame('TestApp', $builder->getParameter('app.name'));
    }
}
