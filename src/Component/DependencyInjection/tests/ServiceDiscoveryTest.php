<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
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
    public function discoversAllClasses(): void
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
    public function skipsExcludedClasses(): void
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
    public function skipsExcludedByPattern(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
            ['LazyService.php', 'Tagged*'],
        );

        self::assertTrue($builder->hasDefinition(SimpleService::class));
        self::assertFalse($builder->hasDefinition(LazyService::class));
        self::assertFalse($builder->hasDefinition(TaggedService::class));
    }

    #[Test]
    public function absolutePathExcludeDoesNotMatch(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
            [__DIR__ . '/Fixtures/LazyService.php'],
        );

        // Absolute paths don't match relative paths in fnmatch(), so nothing is excluded
        self::assertTrue($builder->hasDefinition(LazyService::class));
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
        self::assertTrue($builder->getSymfonyBuilder()->hasAlias(SampleInterface::class));
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

    #[Test]
    public function respectsDefaults(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder, autowire: false, public: false);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        $definition = $builder->findDefinition(SimpleService::class);
        self::assertFalse($definition->isAutowired());
        self::assertFalse($definition->isPublic());
    }

    #[Test]
    public function resolvesEnvAttribute(): void
    {
        $_ENV['TEST_ENV_VALUE'] = 'hello';

        try {
            $builder = new ContainerBuilder();
            $discovery = new ServiceDiscovery($builder);

            $discovery->discover(
                __DIR__ . '/Fixtures/Config',
                'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\Config',
            );

            $definition = $builder->findDefinition(Fixtures\Config\EnvConfig::class);
            $args = $definition->getArguments();
            self::assertSame('hello', $args['$value']);
        } finally {
            unset($_ENV['TEST_ENV_VALUE']);
        }
    }

    #[Test]
    public function resolvesEnvAttributeWithDefault(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures/Config',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\Config',
        );

        $definition = $builder->findDefinition(Fixtures\Config\EnvConfig::class);
        $args = $definition->getArguments();
        // When env is not set and default is available, no argument is set (default used)
        self::assertArrayNotHasKey('$value', $args);
    }

    #[Test]
    public function resolvesConstantAttribute(): void
    {
        if (!defined('TEST_CONSTANT_VALUE')) {
            define('TEST_CONSTANT_VALUE', 42);
        }

        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures/Config',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\Config',
        );

        $definition = $builder->findDefinition(Fixtures\Config\ConstantConfig::class);
        $args = $definition->getArguments();
        self::assertSame(42, $args['$value']);
    }

    #[Test]
    public function resolvesOptionAttribute(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        update_option('test_option_name', 'option_value');

        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures/Config',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\Config',
        );

        $definition = $builder->findDefinition(Fixtures\Config\OptionConfig::class);
        $args = $definition->getArguments();
        self::assertSame('option_value', $args['$value']);

        delete_option('test_option_name');
    }

    #[Test]
    public function resolvesOptionDotNotation(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        update_option('test_settings', ['nested' => ['key' => 'deep_value']]);

        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures/Config',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\Config',
        );

        $definition = $builder->findDefinition(Fixtures\Config\DotOptionConfig::class);
        $args = $definition->getArguments();
        self::assertSame('deep_value', $args['$value']);

        delete_option('test_settings');
    }

    #[Test]
    public function resolvesEnvWithTypeCast(): void
    {
        $_ENV['TEST_ENV_INT'] = '42';

        try {
            $builder = new ContainerBuilder();
            $discovery = new ServiceDiscovery($builder);

            $discovery->discover(
                __DIR__ . '/Fixtures/Config',
                'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\Config',
            );

            $definition = $builder->findDefinition(Fixtures\Config\TypeCastConfig::class);
            $args = $definition->getArguments();
            self::assertSame(42, $args['$intValue']);
            self::assertTrue($args['$boolValue']);
        } finally {
            unset($_ENV['TEST_ENV_INT']);
        }
    }

    #[Test]
    public function resolvesEnvBoolFalseVariants(): void
    {
        $_ENV['TEST_ENV_BOOL'] = 'false';

        try {
            $builder = new ContainerBuilder();
            $discovery = new ServiceDiscovery($builder);

            $discovery->discover(
                __DIR__ . '/Fixtures/Config',
                'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\Config',
            );

            $definition = $builder->findDefinition(Fixtures\Config\BoolConfig::class);
            $args = $definition->getArguments();
            self::assertFalse($args['$value']);
        } finally {
            unset($_ENV['TEST_ENV_BOOL']);
        }
    }

    #[Test]
    public function resolvesServiceAutowireAttribute(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/AutowireFixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\AutowireFixtures',
        );

        $definition = $builder->findDefinition(AutowireFixtures\ServiceAutowireService::class);
        $args = $definition->getArguments();
        self::assertInstanceOf(Reference::class, $args['$service']);
        self::assertSame('some.service', $args['$service']->getId());
    }

    #[Test]
    public function resolvesParamAutowireAttribute(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/AutowireFixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\AutowireFixtures',
        );

        $definition = $builder->findDefinition(AutowireFixtures\ParamAutowireService::class);
        $args = $definition->getArguments();
        self::assertSame('%some.param%', $args['$value']);
    }

    #[Test]
    public function envNotSetWithoutDefaultUsesEnvPlaceholder(): void
    {
        // Ensure env var is not set
        unset($_ENV['UNDEFINED_ENV_VAR']);
        putenv('UNDEFINED_ENV_VAR');

        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/AutowireFixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\AutowireFixtures',
        );

        $definition = $builder->findDefinition(AutowireFixtures\EnvNoDefaultService::class);
        $args = $definition->getArguments();
        self::assertSame('%env(UNDEFINED_ENV_VAR)%', $args['$value']);
    }

    #[Test]
    public function constantNotDefinedWithoutDefaultThrows(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $method = new \ReflectionMethod($discovery, 'resolveConstant');

        // Use a parameter without a default value
        $paramReflection = (new \ReflectionClass(AutowireFixtures\EnvNoDefaultService::class))
            ->getConstructor()
            ->getParameters()[0];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NONEXISTENT_CONSTANT');

        $method->invoke($discovery, 'NONEXISTENT_CONSTANT', $paramReflection);
    }

    #[Test]
    public function envWithFloatTypeCastsToFloat(): void
    {
        $_ENV['TEST_FLOAT'] = '3.14';

        try {
            $builder = new ContainerBuilder();
            $discovery = new ServiceDiscovery($builder);

            $discovery->discover(
                __DIR__ . '/AutowireFixtures',
                'WpPack\\Component\\DependencyInjection\\Tests\\AutowireFixtures',
            );

            $definition = $builder->findDefinition(AutowireFixtures\FloatEnvService::class);
            $args = $definition->getArguments();
            self::assertSame(3.14, $args['$value']);
        } finally {
            unset($_ENV['TEST_FLOAT']);
        }
    }

    #[Test]
    public function envWithArrayTypeCastsToArray(): void
    {
        $_ENV['TEST_ARRAY'] = 'test_value';

        try {
            $builder = new ContainerBuilder();
            $discovery = new ServiceDiscovery($builder);

            $discovery->discover(
                __DIR__ . '/AutowireFixtures',
                'WpPack\\Component\\DependencyInjection\\Tests\\AutowireFixtures',
            );

            $definition = $builder->findDefinition(AutowireFixtures\ArrayEnvService::class);
            $args = $definition->getArguments();
            self::assertIsArray($args['$value']);
        } finally {
            unset($_ENV['TEST_ARRAY']);
        }
    }

    #[Test]
    public function castBoolWithNonStringValue(): void
    {
        // Use int env value to test (bool) cast path for non-string
        $_ENV['TEST_ENV_BOOL'] = '1';

        try {
            $builder = new ContainerBuilder();
            $discovery = new ServiceDiscovery($builder);

            $discovery->discover(
                __DIR__ . '/Fixtures/Config',
                'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\Config',
            );

            $definition = $builder->findDefinition(Fixtures\Config\BoolConfig::class);
            $args = $definition->getArguments();
            self::assertTrue($args['$value']);
        } finally {
            unset($_ENV['TEST_ENV_BOOL']);
        }
    }

    #[Test]
    public function optionNotSetWithoutDefaultThrows(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $method = new \ReflectionMethod($discovery, 'resolveOption');

        // Use a parameter without a default value
        $paramReflection = (new \ReflectionClass(AutowireFixtures\EnvNoDefaultService::class))
            ->getConstructor()
            ->getParameters()[0];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('option is not set');

        $method->invoke($discovery, 'nonexistent_option_name', $paramReflection);
    }

    #[Test]
    public function discoverSkipsNonExistentClass(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures/NonAutoloaded',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\NonAutoloaded',
        );

        // The file exists but the class namespace doesn't match autoloading,
        // so class_exists() returns false and the class is skipped.
        self::assertFalse(
            $builder->hasDefinition(
                'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\NonAutoloaded\\MissingClass',
            ),
        );
    }

    #[Test]
    public function optionNestedKeyNotFoundWithoutDefaultThrows(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        update_option('test_nested_opt', ['existing' => 'value']);

        try {
            $builder = new ContainerBuilder();
            $discovery = new ServiceDiscovery($builder);

            $method = new \ReflectionMethod($discovery, 'resolveOption');

            // Use a parameter without a default value
            $paramReflection = (new \ReflectionClass(AutowireFixtures\EnvNoDefaultService::class))
                ->getConstructor()
                ->getParameters()[0];

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('nested key');

            $method->invoke($discovery, 'test_nested_opt.missing_key', $paramReflection);
        } finally {
            delete_option('test_nested_opt');
        }
    }
}
