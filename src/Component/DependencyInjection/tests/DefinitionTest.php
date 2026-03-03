<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Definition as SymfonyDefinition;
use WpPack\Component\DependencyInjection\Definition;
use WpPack\Component\DependencyInjection\Reference;

final class DefinitionTest extends TestCase
{
    #[Test]
    public function returnsId(): void
    {
        $definition = new Definition('my.service');

        self::assertSame('my.service', $definition->getId());
    }

    #[Test]
    public function setsAndGetsArguments(): void
    {
        $definition = new Definition('my.service');
        $definition->setArgument(0, 'value1');
        $definition->setArgument(1, 'value2');

        $arguments = $definition->getArguments();
        self::assertSame('value1', $arguments[0]);
        self::assertSame('value2', $arguments[1]);
    }

    #[Test]
    public function addsArguments(): void
    {
        $definition = new Definition('my.service');
        $definition->addArgument('first');
        $definition->addArgument('second');

        $arguments = $definition->getArguments();
        self::assertSame('first', $arguments[0]);
        self::assertSame('second', $arguments[1]);
    }

    #[Test]
    public function convertsReferenceArgumentsToWpPack(): void
    {
        $definition = new Definition('my.service');
        $definition->setArgument(0, new Reference('other.service'));

        $arguments = $definition->getArguments();
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame('other.service', $arguments[0]->getId());
    }

    #[Test]
    public function convertsReferenceArrayArguments(): void
    {
        $ref1 = new Reference('service.a');
        $ref2 = new Reference('service.b');
        $definition = new Definition('my.service');
        $definition->setArgument(0, [$ref1, $ref2]);

        $arguments = $definition->getArguments();
        self::assertIsArray($arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[0][0]);
        self::assertInstanceOf(Reference::class, $arguments[0][1]);
        self::assertSame('service.a', $arguments[0][0]->getId());
        self::assertSame('service.b', $arguments[0][1]->getId());
    }

    #[Test]
    public function setsAndGetsFactory(): void
    {
        $definition = new Definition('my.service');
        $definition->setFactory([new Reference('factory.service'), 'create']);

        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('factory.service', $factory[0]->getId());
        self::assertSame('create', $factory[1]);
    }

    #[Test]
    public function setsFactoryWithStringClass(): void
    {
        $definition = new Definition('my.service');
        $definition->setFactory(['MyFactory', 'create']);

        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame('MyFactory', $factory[0]);
        self::assertSame('create', $factory[1]);
    }

    #[Test]
    public function returnsNullFactoryByDefault(): void
    {
        $definition = new Definition('my.service');

        self::assertNull($definition->getFactory());
    }

    #[Test]
    public function addsAndGetsMethodCalls(): void
    {
        $definition = new Definition('my.service');
        $definition->addMethodCall('setLogger', [new Reference('logger')]);

        $calls = $definition->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('setLogger', $calls[0]['method']);
        self::assertInstanceOf(Reference::class, $calls[0]['arguments'][0]);
        self::assertSame('logger', $calls[0]['arguments'][0]->getId());
    }

    #[Test]
    public function addsAndGetsTags(): void
    {
        $definition = new Definition('my.service');
        $definition->addTag('app.handler');
        $definition->addTag('kernel.event_listener');

        $tags = $definition->getTags();
        self::assertContains('app.handler', $tags);
        self::assertContains('kernel.event_listener', $tags);
    }

    #[Test]
    public function setsAutowired(): void
    {
        $definition = new Definition('my.service');

        self::assertFalse($definition->isAutowired());

        $result = $definition->autowire();

        self::assertSame($definition, $result);
        self::assertTrue($definition->isAutowired());
    }

    #[Test]
    public function setsPublic(): void
    {
        $definition = new Definition('my.service');
        $definition->setPublic(true);

        self::assertTrue($definition->isPublic());

        $definition->setPublic(false);
        self::assertFalse($definition->isPublic());
    }

    #[Test]
    public function setsLazy(): void
    {
        $definition = new Definition('my.service');

        self::assertFalse($definition->isLazy());

        $definition->setLazy(true);
        self::assertTrue($definition->isLazy());
    }

    #[Test]
    public function setsClass(): void
    {
        $definition = new Definition('my.service');
        $definition->setClass('App\\Service');

        self::assertSame('App\\Service', $definition->getClass());
    }

    #[Test]
    public function wrapsSymfonyDefinition(): void
    {
        $symfonyDef = new SymfonyDefinition('App\\Service');
        $symfonyDef->addArgument('hello');

        $definition = Definition::wrap('my.service', $symfonyDef);

        self::assertSame('my.service', $definition->getId());
        self::assertSame('App\\Service', $definition->getClass());
        self::assertSame('hello', $definition->getArguments()[0]);
    }

    #[Test]
    public function checksIfHasTag(): void
    {
        $definition = new Definition('my.service');
        $definition->addTag('app.handler');

        self::assertTrue($definition->hasTag('app.handler'));
        self::assertFalse($definition->hasTag('missing.tag'));
    }

    #[Test]
    public function setsAbstract(): void
    {
        $definition = new Definition('my.service');
        $result = $definition->setAbstract(true);

        self::assertSame($definition, $result);
    }

    #[Test]
    public function setsDecoratedService(): void
    {
        $definition = new Definition('my.service');
        $result = $definition->setDecoratedService('decorated.service');

        self::assertSame($definition, $result);
    }

    #[Test]
    public function setsDecoratedServiceWithRenamedIdAndPriority(): void
    {
        $definition = new Definition('my.service');
        $result = $definition->setDecoratedService('decorated.service', 'inner.service', 5);

        self::assertSame($definition, $result);
    }

    #[Test]
    public function fluentApi(): void
    {
        $definition = new Definition('my.service');

        $result = $definition
            ->setArgument(0, 'value')
            ->addArgument('another')
            ->setFactory(['Factory', 'create'])
            ->addMethodCall('init')
            ->addTag('my.tag')
            ->autowire()
            ->setPublic(true)
            ->setLazy(false);

        self::assertSame($definition, $result);
    }

    #[Test]
    public function getSymfonyDefinitionReturnsWrappedInstance(): void
    {
        $symfonyDef = new SymfonyDefinition('App\\Service');
        $definition = Definition::wrap('my.service', $symfonyDef);

        self::assertSame($symfonyDef, $definition->getSymfonyDefinition());
    }

    #[Test]
    public function getFactoryReturnsNullForNonArrayFactory(): void
    {
        $symfonyDef = new SymfonyDefinition();
        $symfonyDef->setFactory('globalFunction');
        $definition = Definition::wrap('my.service', $symfonyDef);

        self::assertNull($definition->getFactory());
    }
}
