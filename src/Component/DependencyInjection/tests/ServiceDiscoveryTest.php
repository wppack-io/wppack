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
}
