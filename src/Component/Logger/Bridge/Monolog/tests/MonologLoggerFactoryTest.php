<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Bridge\Monolog\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;

final class MonologLoggerFactoryTest extends TestCase
{
    #[Test]
    public function createReturnsLoggerInterface(): void
    {
        $factory = new MonologLoggerFactory();
        $logger = $factory->create('app');

        self::assertInstanceOf(LoggerInterface::class, $logger);
        self::assertInstanceOf(MonologLogger::class, $logger);
    }

    #[Test]
    public function createReturnsSameInstanceForSameName(): void
    {
        $factory = new MonologLoggerFactory();

        $logger1 = $factory->create('app');
        $logger2 = $factory->create('app');

        self::assertSame($logger1, $logger2);
    }

    #[Test]
    public function createReturnsDifferentInstancesForDifferentNames(): void
    {
        $factory = new MonologLoggerFactory();

        $app = $factory->create('app');
        $security = $factory->create('security');

        self::assertNotSame($app, $security);
    }

    #[Test]
    public function createAppliesDefaultHandlers(): void
    {
        $testHandler = new TestHandler();
        $factory = new MonologLoggerFactory(defaultHandlers: [$testHandler]);

        $logger = $factory->create('app');
        $logger->info('test message');

        self::assertTrue($testHandler->hasInfoRecords());
    }

    #[Test]
    public function createAppliesDefaultProcessors(): void
    {
        $testHandler = new TestHandler();
        $processor = new PsrLogMessageProcessor();
        $factory = new MonologLoggerFactory(
            defaultHandlers: [$testHandler],
            defaultProcessors: [$processor],
        );

        $logger = $factory->create('app');
        $logger->info('Hello {name}', ['name' => 'World']);

        self::assertTrue($testHandler->hasInfo('Hello World'));
    }

    #[Test]
    public function loggerHasCorrectChannelName(): void
    {
        $testHandler = new TestHandler();
        $factory = new MonologLoggerFactory(defaultHandlers: [$testHandler]);

        $logger = $factory->create('payment');
        $logger->warning('test');

        $records = $testHandler->getRecords();
        self::assertSame('payment', $records[0]->channel);
    }
}
