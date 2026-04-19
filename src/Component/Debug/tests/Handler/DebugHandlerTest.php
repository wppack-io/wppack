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

namespace WPPack\Component\Debug\Tests\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DataCollector\LoggerDataCollector;
use WPPack\Component\Debug\Handler\DebugHandler;
use WPPack\Component\Logger\LoggerFactory;
use WPPack\Component\Logger\Test\TestHandler;

final class DebugHandlerTest extends TestCase
{
    private LoggerDataCollector $collector;
    private DebugHandler $handler;

    protected function setUp(): void
    {
        $this->collector = new LoggerDataCollector(new LoggerFactory([new TestHandler()]));
        $this->handler = new DebugHandler($this->collector);
    }

    #[Test]
    public function isHandlingReturnsTrueForAllLevels(): void
    {
        self::assertTrue($this->handler->isHandling('debug'));
        self::assertTrue($this->handler->isHandling('info'));
        self::assertTrue($this->handler->isHandling('notice'));
        self::assertTrue($this->handler->isHandling('warning'));
        self::assertTrue($this->handler->isHandling('error'));
        self::assertTrue($this->handler->isHandling('critical'));
        self::assertTrue($this->handler->isHandling('alert'));
        self::assertTrue($this->handler->isHandling('emergency'));
    }

    #[Test]
    public function handleForwardsToCollector(): void
    {
        $this->handler->handle('info', 'Test message', []);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertSame('info', $data['logs'][0]['level']);
        self::assertSame('Test message', $data['logs'][0]['message']);
        self::assertSame('app', $data['logs'][0]['channel']);
    }

    #[Test]
    public function handleExtractsChannelFromContext(): void
    {
        $this->handler->handle('warning', 'Channel test', ['_channel' => 'security']);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('security', $data['logs'][0]['channel']);
    }

    #[Test]
    public function handleStripsInternalKeysFromContext(): void
    {
        $this->handler->handle('info', 'Context test', [
            '_channel' => 'custom',
            'user_id' => 42,
            'action' => 'login',
        ]);

        $this->collector->collect();
        $data = $this->collector->getData();

        $context = $data['logs'][0]['context'];
        self::assertArrayNotHasKey('_channel', $context);
        self::assertSame(42, $context['user_id']);
        self::assertSame('login', $context['action']);
    }
}
