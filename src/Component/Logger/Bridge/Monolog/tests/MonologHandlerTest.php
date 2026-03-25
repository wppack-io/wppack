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

namespace WpPack\Component\Logger\Bridge\Monolog\Tests;

use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Logger\Bridge\Monolog\MonologHandler;
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use WpPack\Component\Logger\Handler\HandlerInterface;

final class MonologHandlerTest extends TestCase
{
    #[Test]
    public function implementsHandlerInterface(): void
    {
        $handler = new MonologHandler(new MonologLoggerFactory());

        self::assertInstanceOf(HandlerInterface::class, $handler);
    }

    #[Test]
    public function handleDelegatesToMonolog(): void
    {
        $testHandler = new TestHandler();
        $factory = new MonologLoggerFactory(defaultHandlers: [$testHandler]);
        $handler = new MonologHandler($factory);

        $handler->handle('info', 'test message', ['_channel' => 'app']);

        self::assertTrue($testHandler->hasInfoRecords());
        self::assertTrue($testHandler->hasInfo('test message'));
    }

    #[Test]
    public function channelIsExtractedFromContext(): void
    {
        $testHandler = new TestHandler();
        $factory = new MonologLoggerFactory(defaultHandlers: [$testHandler]);
        $handler = new MonologHandler($factory);

        $handler->handle('warning', 'payment failed', ['_channel' => 'payment']);

        $records = $testHandler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('payment', $records[0]->channel);
    }

    #[Test]
    public function defaultChannelIsApp(): void
    {
        $testHandler = new TestHandler();
        $factory = new MonologLoggerFactory(defaultHandlers: [$testHandler]);
        $handler = new MonologHandler($factory);

        $handler->handle('info', 'no channel', []);

        $records = $testHandler->getRecords();
        self::assertSame('app', $records[0]->channel);
    }

    #[Test]
    public function internalContextKeysAreFiltered(): void
    {
        $testHandler = new TestHandler();
        $factory = new MonologLoggerFactory(defaultHandlers: [$testHandler]);
        $handler = new MonologHandler($factory);

        $handler->handle('error', 'test', [
            '_channel' => 'app',
            '_file' => '/path/to/file.php',
            '_line' => 42,
            '_type' => 'deprecation',
            '_error_type' => 'E_DEPRECATED',
            'user_id' => 123,
        ]);

        $records = $testHandler->getRecords();
        $context = $records[0]->context;
        self::assertArrayNotHasKey('_channel', $context);
        self::assertArrayNotHasKey('_file', $context);
        self::assertArrayNotHasKey('_line', $context);
        self::assertArrayNotHasKey('_type', $context);
        self::assertArrayNotHasKey('_error_type', $context);
    }

    #[Test]
    public function userContextIsPreserved(): void
    {
        $testHandler = new TestHandler();
        $factory = new MonologLoggerFactory(defaultHandlers: [$testHandler]);
        $handler = new MonologHandler($factory);

        $handler->handle('info', 'test', [
            '_channel' => 'app',
            'user_id' => 123,
            'action' => 'login',
        ]);

        $records = $testHandler->getRecords();
        $context = $records[0]->context;
        self::assertSame(123, $context['user_id']);
        self::assertSame('login', $context['action']);
    }

    #[Test]
    public function isHandlingRespectsMinimumLevel(): void
    {
        $handler = new MonologHandler(new MonologLoggerFactory(), level: 'warning');

        self::assertTrue($handler->isHandling('emergency'));
        self::assertTrue($handler->isHandling('alert'));
        self::assertTrue($handler->isHandling('critical'));
        self::assertTrue($handler->isHandling('error'));
        self::assertTrue($handler->isHandling('warning'));
        self::assertFalse($handler->isHandling('notice'));
        self::assertFalse($handler->isHandling('info'));
        self::assertFalse($handler->isHandling('debug'));
    }

    #[Test]
    public function sameChannelReusesSameLogger(): void
    {
        $testHandler = new TestHandler();
        $factory = new MonologLoggerFactory(defaultHandlers: [$testHandler]);
        $handler = new MonologHandler($factory);

        $handler->handle('info', 'first', ['_channel' => 'payment']);
        $handler->handle('info', 'second', ['_channel' => 'payment']);

        $records = $testHandler->getRecords();
        self::assertCount(2, $records);
        self::assertSame('payment', $records[0]->channel);
        self::assertSame('payment', $records[1]->channel);
    }
}
