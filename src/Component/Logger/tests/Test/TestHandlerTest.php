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

namespace WpPack\Component\Logger\Tests\Test;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Logger\Test\TestHandler;

final class TestHandlerTest extends TestCase
{
    private TestHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
    }

    #[Test]
    public function isHandlingAlwaysReturnsTrue(): void
    {
        self::assertTrue($this->handler->isHandling('debug'));
        self::assertTrue($this->handler->isHandling('emergency'));
    }

    #[Test]
    public function handleStoresRecords(): void
    {
        $this->handler->handle('info', 'Test message', ['key' => 'value']);

        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('info', $records[0]['level']);
        self::assertSame('Test message', $records[0]['message']);
        self::assertSame(['key' => 'value'], $records[0]['context']);
    }

    #[Test]
    public function resetClearsRecords(): void
    {
        $this->handler->handle('info', 'Test', []);
        self::assertCount(1, $this->handler->getRecords());

        $this->handler->reset();
        self::assertCount(0, $this->handler->getRecords());
    }

    #[Test]
    public function hasLevelMethodsMatchExactMessage(): void
    {
        $this->handler->handle('info', 'Exact message', []);

        self::assertTrue($this->handler->hasInfo('Exact message'));
        self::assertFalse($this->handler->hasInfo('Different message'));
        self::assertFalse($this->handler->hasError('Exact message'));
    }

    #[Test]
    public function hasLevelThatContainsMatchesSubstring(): void
    {
        $this->handler->handle('warning', 'User login failed for admin', ['ip' => '127.0.0.1']);

        self::assertTrue($this->handler->hasWarningThatContains('login failed'));
        self::assertFalse($this->handler->hasWarningThatContains('login succeeded'));
    }

    #[Test]
    public function hasLevelThatContainsWithContextMatching(): void
    {
        $this->handler->handle('error', 'Payment failed', [
            'amount' => 100,
            'currency' => 'USD',
        ]);

        self::assertTrue($this->handler->hasErrorThatContains('Payment failed', ['amount' => 100]));
        self::assertFalse($this->handler->hasErrorThatContains('Payment failed', ['amount' => 200]));
        self::assertFalse($this->handler->hasErrorThatContains('Payment failed', ['missing_key' => 'value']));
    }

    #[Test]
    public function allLevelMethods(): void
    {
        $levels = [
            'emergency' => 'hasEmergency',
            'alert' => 'hasAlert',
            'critical' => 'hasCritical',
            'error' => 'hasError',
            'warning' => 'hasWarning',
            'notice' => 'hasNotice',
            'info' => 'hasInfo',
            'debug' => 'hasDebug',
        ];

        foreach ($levels as $level => $method) {
            $handler = new TestHandler();
            $handler->handle($level, 'Test', []);
            self::assertTrue($handler->$method('Test'), "Failed asserting {$method} returns true");
        }
    }

    #[Test]
    public function allLevelThatContainsMethods(): void
    {
        $levels = [
            'emergency' => 'hasEmergencyThatContains',
            'alert' => 'hasAlertThatContains',
            'critical' => 'hasCriticalThatContains',
            'error' => 'hasErrorThatContains',
            'warning' => 'hasWarningThatContains',
            'notice' => 'hasNoticeThatContains',
            'info' => 'hasInfoThatContains',
            'debug' => 'hasDebugThatContains',
        ];

        foreach ($levels as $level => $method) {
            $handler = new TestHandler();
            $handler->handle($level, 'Test message here', []);
            self::assertTrue($handler->$method('message'), "Failed asserting {$method} returns true");
        }
    }
}
