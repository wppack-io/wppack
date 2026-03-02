<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Config\ConfigResolver;
use WpPack\Component\Config\DependencyInjection\RegisterConfigClassesPass;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;

final class RegisterConfigClassesPassTest extends TestCase
{
    #[Test]
    public function convertsTaggedServicesToFactoryPattern(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('App\\Config\\MyConfig', 'App\\Config\\MyConfig')
            ->setPublic(true)
            ->addTag('config.class');

        $pass = new RegisterConfigClassesPass();
        $pass->process($builder);

        self::assertTrue($builder->hasDefinition(ConfigResolver::class));

        $definition = $builder->findDefinition('App\\Config\\MyConfig');
        $factory = $definition->getFactory();

        self::assertNotNull($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame(ConfigResolver::class, $factory[0]->getId());
        self::assertSame('resolve', $factory[1]);

        $arguments = $definition->getArguments();
        self::assertSame('App\\Config\\MyConfig', $arguments[0]);
    }

    #[Test]
    public function registersConfigResolverOnlyOnce(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ConfigResolver::class, ConfigResolver::class);
        $builder->register('App\\Config\\First', 'App\\Config\\First')
            ->setPublic(true)
            ->addTag('config.class');
        $builder->register('App\\Config\\Second', 'App\\Config\\Second')
            ->setPublic(true)
            ->addTag('config.class');

        $pass = new RegisterConfigClassesPass();
        $pass->process($builder);

        self::assertTrue($builder->hasDefinition(ConfigResolver::class));
    }

    #[Test]
    public function doesNothingWhenNoTaggedServices(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('App\\Service\\Foo', 'App\\Service\\Foo');

        $pass = new RegisterConfigClassesPass();
        $pass->process($builder);

        self::assertFalse($builder->hasDefinition(ConfigResolver::class));
    }

    #[Test]
    public function processesMultipleConfigClasses(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('App\\Config\\First', 'App\\Config\\First')
            ->setPublic(true)
            ->addTag('config.class');
        $builder->register('App\\Config\\Second', 'App\\Config\\Second')
            ->setPublic(true)
            ->addTag('config.class');

        $pass = new RegisterConfigClassesPass();
        $pass->process($builder);

        $first = $builder->findDefinition('App\\Config\\First');
        $second = $builder->findDefinition('App\\Config\\Second');

        self::assertNotNull($first->getFactory());
        self::assertNotNull($second->getFactory());
        self::assertSame('App\\Config\\First', $first->getArguments()[0]);
        self::assertSame('App\\Config\\Second', $second->getArguments()[0]);
    }
}
