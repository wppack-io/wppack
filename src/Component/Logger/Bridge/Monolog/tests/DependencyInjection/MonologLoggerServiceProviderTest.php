<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Bridge\Monolog\Tests\DependencyInjection;

use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Logger\Bridge\Monolog\DependencyInjection\MonologLoggerServiceProvider;
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use WpPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WpPack\Component\Logger\LoggerFactory;

final class MonologLoggerServiceProviderTest extends TestCase
{
    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new MonologLoggerServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    public function registersLoggerFactoryAsMonologLoggerFactory(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MonologLoggerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(LoggerFactory::class));
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->addServiceProvider(new MonologLoggerServiceProvider());

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition(LoggerFactory::class));
    }

    #[Test]
    public function overridesLoggerServiceProviderFactory(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());
        $builder->addServiceProvider(new MonologLoggerServiceProvider());

        $container = $builder->compile();

        self::assertTrue($container->has(LoggerInterface::class));
        $logger = $container->get(LoggerInterface::class);
        self::assertInstanceOf(\Monolog\Logger::class, $logger);
    }

    #[Test]
    public function handlersArePassedToFactory(): void
    {
        $handler = new StreamHandler('php://memory');
        $builder = new ContainerBuilder();
        $provider = new MonologLoggerServiceProvider(handlers: [$handler]);

        $provider->register($builder);

        $definition = $builder->findDefinition(LoggerFactory::class);
        $arguments = $definition->getArguments();
        self::assertSame([$handler], $arguments[0]);
    }

    #[Test]
    public function processorsArePassedToFactory(): void
    {
        $processor = new PsrLogMessageProcessor();
        $builder = new ContainerBuilder();
        $provider = new MonologLoggerServiceProvider(processors: [$processor]);

        $provider->register($builder);

        $definition = $builder->findDefinition(LoggerFactory::class);
        $arguments = $definition->getArguments();
        self::assertSame([$processor], $arguments[1]);
    }

    #[Test]
    public function compiledContainerResolvesMonologLogger(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());
        $builder->addServiceProvider(new MonologLoggerServiceProvider(
            handlers: [new StreamHandler('php://memory')],
        ));

        $container = $builder->compile();

        $logger = $container->get(LoggerInterface::class);
        self::assertInstanceOf(LoggerInterface::class, $logger);
        self::assertInstanceOf(\Monolog\Logger::class, $logger);
    }
}
