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

namespace WpPack\Component\Logger\Bridge\Monolog\Tests\DependencyInjection;

use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Logger\Bridge\Monolog\DependencyInjection\MonologLoggerServiceProvider;
use WpPack\Component\Logger\Bridge\Monolog\MonologHandler;
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use WpPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WpPack\Component\Logger\Logger;
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
    public function throwsWhenLoggerServiceProviderNotRegistered(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MonologLoggerServiceProvider();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('requires LoggerServiceProvider to be registered first');

        $provider->register($builder);
    }

    #[Test]
    public function registersMonologHandler(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());
        $builder->addServiceProvider(new MonologLoggerServiceProvider());

        self::assertTrue($builder->hasDefinition(MonologHandler::class));
    }

    #[Test]
    public function registersMonologLoggerFactory(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());
        $builder->addServiceProvider(new MonologLoggerServiceProvider());

        self::assertTrue($builder->hasDefinition(MonologLoggerFactory::class));
    }

    #[Test]
    public function replacesLoggerFactoryHandlers(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());
        $builder->addServiceProvider(new MonologLoggerServiceProvider());

        $definition = $builder->findDefinition(LoggerFactory::class);
        $arguments = $definition->getArguments();

        self::assertCount(1, $arguments[0]);
        self::assertSame(MonologHandler::class, (string) $arguments[0][0]);
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());

        $result = $builder->addServiceProvider(new MonologLoggerServiceProvider());

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition(MonologHandler::class));
    }

    #[Test]
    public function handlersArePassedToMonologLoggerFactory(): void
    {
        $handler = new StreamHandler('php://memory');
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());
        $provider = new MonologLoggerServiceProvider(handlers: [$handler]);

        $provider->register($builder);

        $definition = $builder->findDefinition(MonologLoggerFactory::class);
        $arguments = $definition->getArguments();
        self::assertSame([$handler], $arguments[0]);
    }

    #[Test]
    public function processorsArePassedToMonologLoggerFactory(): void
    {
        $processor = new PsrLogMessageProcessor();
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());
        $provider = new MonologLoggerServiceProvider(processors: [$processor]);

        $provider->register($builder);

        $definition = $builder->findDefinition(MonologLoggerFactory::class);
        $arguments = $definition->getArguments();
        self::assertSame([$processor], $arguments[1]);
    }

    #[Test]
    public function compiledContainerResolvesWpPackLogger(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());
        $builder->addServiceProvider(new MonologLoggerServiceProvider(
            handlers: [new StreamHandler('php://memory')],
        ));

        $container = $builder->compile();

        $logger = $container->get(LoggerInterface::class);
        self::assertInstanceOf(LoggerInterface::class, $logger);
        self::assertInstanceOf(Logger::class, $logger);
    }

    #[Test]
    public function monologHandlerReceivesLogs(): void
    {
        $testHandler = new TestHandler();
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new LoggerServiceProvider());
        $builder->addServiceProvider(new MonologLoggerServiceProvider(
            handlers: [$testHandler],
        ));

        $container = $builder->compile();

        $logger = $container->get(LoggerInterface::class);
        $logger->info('integration test');

        self::assertTrue($testHandler->hasInfoRecords());
        self::assertTrue($testHandler->hasInfo('integration test'));
    }
}
