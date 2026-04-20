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

namespace WPPack\Component\Logger\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Logger\Logger;
use WPPack\Component\Logger\LoggerFactory;
use WPPack\Component\Logger\Test\TestHandler;

final class LoggerFactoryTest extends TestCase
{
    #[Test]
    public function createReturnsLoggerInstance(): void
    {
        $factory = new LoggerFactory();
        $logger = $factory->create('app');

        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame('app', $logger->getName());
    }

    #[Test]
    public function createReturnsSameInstanceForSameChannel(): void
    {
        $factory = new LoggerFactory();
        $logger1 = $factory->create('app');
        $logger2 = $factory->create('app');

        self::assertSame($logger1, $logger2);
    }

    #[Test]
    public function createReturnsDifferentInstancesForDifferentChannels(): void
    {
        $factory = new LoggerFactory();
        $logger1 = $factory->create('app');
        $logger2 = $factory->create('security');

        self::assertNotSame($logger1, $logger2);
        self::assertSame('app', $logger1->getName());
        self::assertSame('security', $logger2->getName());
    }

    #[Test]
    public function defaultHandlersAreRegistered(): void
    {
        $handler = new TestHandler();
        $factory = new LoggerFactory([$handler]);
        $logger = $factory->create('test');

        $logger->info('Test message');

        self::assertTrue($handler->hasInfo('Test message'));
    }

    #[Test]
    public function multipleDefaultHandlers(): void
    {
        $handler1 = new TestHandler();
        $handler2 = new TestHandler();
        $factory = new LoggerFactory([$handler1, $handler2]);
        $logger = $factory->create('test');

        $logger->info('Test message');

        self::assertTrue($handler1->hasInfo('Test message'));
        self::assertTrue($handler2->hasInfo('Test message'));
    }

    #[Test]
    public function pushHandlerAddsToDefaultsAndRetroactivelyAttachesToExistingLoggers(): void
    {
        $initial = new TestHandler();
        $factory = new LoggerFactory([$initial]);

        // Create a logger *before* pushHandler is called so we can verify
        // the handler is attached retroactively.
        $existing = $factory->create('app');

        $added = new TestHandler();
        $factory->pushHandler($added);

        // Existing logger should route to both the initial handler and
        // the newly-pushed one.
        $existing->info('hello');
        self::assertTrue($initial->hasInfo('hello'));
        self::assertTrue($added->hasInfo('hello'));

        // Loggers created after pushHandler inherit the augmented
        // default-handlers list.
        $fresh = $factory->create('fresh');
        $fresh->warning('watch out');
        self::assertTrue($initial->hasWarning('watch out'));
        self::assertTrue($added->hasWarning('watch out'));
    }
}
