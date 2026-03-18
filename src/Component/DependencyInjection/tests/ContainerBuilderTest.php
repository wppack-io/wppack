<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Definition;
use WpPack\Component\DependencyInjection\Exception\ParameterNotFoundException;
use WpPack\Component\DependencyInjection\Exception\ServiceNotFoundException;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;

final class ContainerBuilderTest extends TestCase
{
    #[Test]
    public function registersServiceDefinition(): void
    {
        $builder = new ContainerBuilder();
        $definition = $builder->register('my.service');

        self::assertInstanceOf(Definition::class, $definition);
        self::assertSame('my.service', $definition->getId());
    }

    #[Test]
    public function registersServiceWithClass(): void
    {
        $builder = new ContainerBuilder();
        $definition = $builder->register('my.service', \stdClass::class);

        self::assertSame(\stdClass::class, $definition->getClass());
    }

    #[Test]
    public function findsDefinition(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service');

        $found = $builder->findDefinition('my.service');
        self::assertSame('my.service', $found->getId());
    }

    #[Test]
    public function throwsOnMissingDefinition(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(ServiceNotFoundException::class);
        $builder->findDefinition('missing.service');
    }

    #[Test]
    public function checksIfDefinitionExists(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service');

        self::assertTrue($builder->hasDefinition('my.service'));
        self::assertFalse($builder->hasDefinition('missing.service'));
    }

    #[Test]
    public function returnsAllDefinitions(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('service.a');
        $builder->register('service.b');

        $definitions = $builder->getDefinitions();
        self::assertCount(2, $definitions);
        self::assertArrayHasKey('service.a', $definitions);
        self::assertArrayHasKey('service.b', $definitions);
    }

    #[Test]
    public function findsTaggedServiceIds(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('handler.a')->addTag('app.handler', ['priority' => 10]);
        $builder->register('handler.b')->addTag('app.handler');

        $tagged = $builder->findTaggedServiceIds('app.handler');
        self::assertCount(2, $tagged);
        self::assertArrayHasKey('handler.a', $tagged);
        self::assertArrayHasKey('handler.b', $tagged);
    }

    #[Test]
    public function returnsEmptyArrayForUnknownTag(): void
    {
        $builder = new ContainerBuilder();

        self::assertSame([], $builder->findTaggedServiceIds('unknown.tag'));
    }

    #[Test]
    public function addsCompilerPass(): void
    {
        $builder = new ContainerBuilder();
        $pass = new class implements CompilerPassInterface {
            public bool $processed = false;

            public function process(ContainerBuilder $builder): void
            {
                $this->processed = true;
            }
        };

        $result = $builder->addCompilerPass($pass);

        self::assertSame($builder, $result);
        self::assertContains($pass, $builder->getCompilerPasses());
    }

    #[Test]
    public function compilerPassIsExecutedDuringCompile(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service', \stdClass::class);

        $pass = new class implements CompilerPassInterface {
            public bool $processed = false;

            public function process(ContainerBuilder $builder): void
            {
                $this->processed = true;
            }
        };

        $builder->addCompilerPass($pass);
        $builder->compile();

        self::assertTrue($pass->processed);
    }

    #[Test]
    public function compilesAndReturnsContainer(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service', \stdClass::class)->setPublic(true);

        $container = $builder->compile();

        self::assertTrue($container->has('my.service'));
        self::assertInstanceOf(\stdClass::class, $container->get('my.service'));
    }

    #[Test]
    public function setsAndGetsParameters(): void
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('app.name', 'WpPack');

        self::assertSame('WpPack', $builder->getParameter('app.name'));
        self::assertTrue($builder->hasParameter('app.name'));
        self::assertFalse($builder->hasParameter('missing'));
    }

    #[Test]
    public function throwsOnMissingParameter(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(ParameterNotFoundException::class);
        $builder->getParameter('missing.param');
    }

    #[Test]
    public function setsAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('concrete.service', \stdClass::class);
        $builder->setAlias('alias.service', 'concrete.service');

        $container = $builder->compile();

        self::assertTrue($container->has('alias.service'));
        self::assertInstanceOf(\stdClass::class, $container->get('alias.service'));
    }

    #[Test]
    public function addsServiceProvider(): void
    {
        $builder = new ContainerBuilder();

        $provider = new class implements ServiceProviderInterface {
            public function register(ContainerBuilder $builder): void
            {
                $builder->register('provided.service', \stdClass::class);
            }
        };

        $result = $builder->addServiceProvider($provider);

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition('provided.service'));
    }

    #[Test]
    public function registeredServicesArePublicByDefault(): void
    {
        $builder = new ContainerBuilder();
        $definition = $builder->register('my.service', \stdClass::class);

        self::assertTrue($definition->isPublic());
    }

    #[Test]
    public function compilerPassCanModifyServices(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service', \stdClass::class);

        $pass = new class implements CompilerPassInterface {
            public function process(ContainerBuilder $builder): void
            {
                if ($builder->hasDefinition('my.service')) {
                    $builder->findDefinition('my.service')->setArgument(0, 'injected');
                }
            }
        };

        $builder->addCompilerPass($pass);

        $def = $builder->findDefinition('my.service');
        $builder->compile();

        self::assertSame('injected', $def->getArguments()[0]);
    }

    #[Test]
    public function compilerPassWithFactoryAndReference(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('factory.service', \stdClass::class);
        $builder->register('my.service', \stdClass::class);

        $pass = new class implements CompilerPassInterface {
            public function process(ContainerBuilder $builder): void
            {
                $builder->findDefinition('my.service')
                    ->setFactory([new Reference('factory.service'), 'create'])
                    ->setArgument(0, new Reference('factory.service'));
            }
        };

        $builder->addCompilerPass($pass);

        // Verify the definitions can be read back with WpPack types
        $pass->process($builder);
        $def = $builder->findDefinition('my.service');

        $factory = $def->getFactory();
        self::assertNotNull($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('factory.service', $factory[0]->getId());

        $args = $def->getArguments();
        self::assertInstanceOf(Reference::class, $args[0]);
        self::assertSame('factory.service', $args[0]->getId());
    }

    #[Test]
    public function parameterNotFoundExceptionContainsParameterName(): void
    {
        $builder = new ContainerBuilder();

        try {
            $builder->getParameter('my.missing.param');
            self::fail('Expected ParameterNotFoundException');
        } catch (ParameterNotFoundException $e) {
            self::assertStringContainsString('my.missing.param', $e->getMessage());
        }
    }

    #[Test]
    public function registerSameIdTwiceOverwritesDefinition(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service', \stdClass::class);
        $builder->register('my.service', \ArrayObject::class);

        $definition = $builder->findDefinition('my.service');
        self::assertSame(\ArrayObject::class, $definition->getClass());
    }

    #[Test]
    public function compilesEmptyContainer(): void
    {
        $builder = new ContainerBuilder();

        $container = $builder->compile();

        self::assertFalse($container->has('any.service'));
    }

    #[Test]
    public function loadConfigRegistersServicesFromFile(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->loadConfig(__DIR__ . '/Fixtures/Config/test_services.php');

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition(SimpleService::class));
        self::assertSame('TestApp', $builder->getParameter('app.name'));
    }

    #[Test]
    public function loadConfigCallsConfiguratorProcess(): void
    {
        $builder = new ContainerBuilder();

        $builder->loadConfig(__DIR__ . '/Fixtures/Config/test_services.php');

        $definition = $builder->findDefinition(SimpleService::class);
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->isPublic());
    }

    #[Test]
    public function findDefinitionWrapsSymfonyDefinitionWhenNotInCache(): void
    {
        $builder = new ContainerBuilder();

        // Register directly via Symfony's builder to bypass our definitions cache
        $builder->getSymfonyBuilder()->register('symfony.only', \stdClass::class)->setPublic(true);

        // This should wrap the Symfony definition
        $definition = $builder->findDefinition('symfony.only');
        self::assertInstanceOf(Definition::class, $definition);
        self::assertSame(\stdClass::class, $definition->getClass());
    }

    #[Test]
    public function getDefinitionsIncludesSymfonyRegisteredServices(): void
    {
        $builder = new ContainerBuilder();

        // Register via WpPack
        $builder->register('wppack.service', \stdClass::class);

        // Register directly via Symfony's builder
        $builder->getSymfonyBuilder()->register('symfony.service', \ArrayObject::class)->setPublic(true);

        $definitions = $builder->getDefinitions();

        self::assertArrayHasKey('wppack.service', $definitions);
        self::assertArrayHasKey('symfony.service', $definitions);
    }

    #[Test]
    public function setParameterReturnsSelf(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->setParameter('key', 'value');

        self::assertSame($builder, $result);
    }

    #[Test]
    public function setAliasReturnsSelf(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('service.a', \stdClass::class);

        $result = $builder->setAlias('alias.a', 'service.a');

        self::assertSame($builder, $result);
    }

    #[Test]
    public function getSymfonyBuilderReturnsInstance(): void
    {
        $builder = new ContainerBuilder();

        $symfonyBuilder = $builder->getSymfonyBuilder();

        self::assertInstanceOf(\Symfony\Component\DependencyInjection\ContainerBuilder::class, $symfonyBuilder);
    }
}
