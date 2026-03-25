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

namespace WpPack\Component\Logger\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WpPack\Component\Logger\ChannelResolver\WordPressChannelResolver;
use WpPack\Component\Logger\ErrorHandler;
use WpPack\Component\Logger\Handler\ErrorLogHandler;
use WpPack\Component\Logger\LoggerFactory;

final class LoggerServiceProviderTest extends TestCase
{
    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new LoggerServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    public function registersErrorLogHandler(): void
    {
        $builder = new ContainerBuilder();
        $provider = new LoggerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(ErrorLogHandler::class));
    }

    #[Test]
    public function registersLoggerFactory(): void
    {
        $builder = new ContainerBuilder();
        $provider = new LoggerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(LoggerFactory::class));
    }

    #[Test]
    public function registersLoggerInterface(): void
    {
        $builder = new ContainerBuilder();
        $provider = new LoggerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(LoggerInterface::class));
    }

    #[Test]
    public function loggerInterfaceUsesFactoryMethod(): void
    {
        $builder = new ContainerBuilder();
        $provider = new LoggerServiceProvider();

        $provider->register($builder);

        $definition = $builder->findDefinition(LoggerInterface::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('create', $factory[1]);
    }

    #[Test]
    public function defaultChannelIsApp(): void
    {
        $builder = new ContainerBuilder();
        $provider = new LoggerServiceProvider();

        $provider->register($builder);

        $definition = $builder->findDefinition(LoggerInterface::class);
        $arguments = $definition->getArguments();
        self::assertSame('app', $arguments[0]);
    }

    #[Test]
    public function customChannelIsApplied(): void
    {
        $builder = new ContainerBuilder();
        $provider = new LoggerServiceProvider(defaultChannel: 'custom');

        $provider->register($builder);

        $definition = $builder->findDefinition(LoggerInterface::class);
        $arguments = $definition->getArguments();
        self::assertSame('custom', $arguments[0]);
    }

    #[Test]
    public function customLevelIsApplied(): void
    {
        $builder = new ContainerBuilder();
        $provider = new LoggerServiceProvider(level: 'warning');

        $provider->register($builder);

        $definition = $builder->findDefinition(ErrorLogHandler::class);
        $arguments = $definition->getArguments();
        self::assertSame('warning', $arguments[0]);
    }

    #[Test]
    public function registersWordPressChannelResolver(): void
    {
        $builder = new ContainerBuilder();
        $provider = new LoggerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(WordPressChannelResolver::class));
    }

    #[Test]
    public function registersErrorHandler(): void
    {
        $builder = new ContainerBuilder();
        $provider = new LoggerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(ErrorHandler::class));
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->addServiceProvider(new LoggerServiceProvider());

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition(ErrorLogHandler::class));
        self::assertTrue($builder->hasDefinition(LoggerFactory::class));
        self::assertTrue($builder->hasDefinition(LoggerInterface::class));
    }

    #[Test]
    public function compiledContainerResolvesLoggerInterface(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());

        $container = $builder->compile();

        self::assertTrue($container->has(LoggerInterface::class));
        self::assertInstanceOf(LoggerInterface::class, $container->get(LoggerInterface::class));
    }
}
