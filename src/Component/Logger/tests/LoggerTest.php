<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Component\Logger\Context\LoggerContext;
use WpPack\Component\Logger\Exception\InvalidArgumentException;
use WpPack\Component\Logger\Logger;
use WpPack\Component\Logger\Test\TestHandler;

final class LoggerTest extends TestCase
{
    private Logger $logger;
    private TestHandler $handler;

    protected function setUp(): void
    {
        $this->logger = new Logger('test');
        $this->handler = new TestHandler();
        $this->logger->pushHandler($this->handler);
    }

    #[Test]
    public function implementsPsr3LoggerInterface(): void
    {
        self::assertInstanceOf(LoggerInterface::class, $this->logger);
    }

    #[Test]
    public function getName(): void
    {
        self::assertSame('test', $this->logger->getName());
    }

    #[Test]
    public function emergency(): void
    {
        $this->logger->emergency('System down');
        self::assertTrue($this->handler->hasEmergency('System down'));
    }

    #[Test]
    public function alert(): void
    {
        $this->logger->alert('Action required');
        self::assertTrue($this->handler->hasAlert('Action required'));
    }

    #[Test]
    public function critical(): void
    {
        $this->logger->critical('Critical failure');
        self::assertTrue($this->handler->hasCritical('Critical failure'));
    }

    #[Test]
    public function error(): void
    {
        $this->logger->error('An error occurred');
        self::assertTrue($this->handler->hasError('An error occurred'));
    }

    #[Test]
    public function warning(): void
    {
        $this->logger->warning('Deprecation warning');
        self::assertTrue($this->handler->hasWarning('Deprecation warning'));
    }

    #[Test]
    public function notice(): void
    {
        $this->logger->notice('Notable event');
        self::assertTrue($this->handler->hasNotice('Notable event'));
    }

    #[Test]
    public function info(): void
    {
        $this->logger->info('User logged in');
        self::assertTrue($this->handler->hasInfo('User logged in'));
    }

    #[Test]
    public function debug(): void
    {
        $this->logger->debug('Debug info');
        self::assertTrue($this->handler->hasDebug('Debug info'));
    }

    #[Test]
    public function logWithGenericLevel(): void
    {
        $this->logger->log('info', 'Generic log');
        self::assertTrue($this->handler->hasInfo('Generic log'));
    }

    #[Test]
    public function invalidLevelThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level "invalid".');

        $this->logger->log('invalid', 'Should fail');
    }

    #[Test]
    public function placeholderInterpolation(): void
    {
        $this->logger->info('User {username} logged in from {ip}', [
            'username' => 'john',
            'ip' => '127.0.0.1',
        ]);

        self::assertTrue($this->handler->hasInfo('User john logged in from 127.0.0.1'));
    }

    #[Test]
    public function placeholderWithStringableObject(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };

        $this->logger->info('Value: {obj}', ['obj' => $stringable]);
        self::assertTrue($this->handler->hasInfo('Value: stringable-value'));
    }

    #[Test]
    public function placeholderWithNonStringValueIsIgnored(): void
    {
        $this->logger->info('Count: {count}', ['count' => 42]);
        self::assertTrue($this->handler->hasInfo('Count: {count}'));
    }

    #[Test]
    public function withContextAddsPersistentContext(): void
    {
        $this->logger->withContext(new LoggerContext(['request_id' => 'abc-123']));
        $this->logger->info('Test message');

        $records = $this->handler->getRecords();
        self::assertSame('abc-123', $records[0]['context']['request_id']);
    }

    #[Test]
    public function withContextMergesWithExistingContext(): void
    {
        $this->logger->withContext(new LoggerContext(['key1' => 'value1']));
        $this->logger->withContext(new LoggerContext(['key2' => 'value2']));
        $this->logger->info('Test');

        $records = $this->handler->getRecords();
        self::assertSame('value1', $records[0]['context']['key1']);
        self::assertSame('value2', $records[0]['context']['key2']);
    }

    #[Test]
    public function withContextCanOverridePreviousValues(): void
    {
        $this->logger->withContext(new LoggerContext(['key' => 'old']));
        $this->logger->withContext(new LoggerContext(['key' => 'new']));
        $this->logger->info('Test');

        $records = $this->handler->getRecords();
        self::assertSame('new', $records[0]['context']['key']);
    }

    #[Test]
    public function callContextOverridesPersistentContext(): void
    {
        $this->logger->withContext(new LoggerContext(['key' => 'persistent']));
        $this->logger->info('Test', ['key' => 'call']);

        $records = $this->handler->getRecords();
        self::assertSame('call', $records[0]['context']['key']);
    }

    #[Test]
    public function channelIsInjectedIntoContext(): void
    {
        $this->logger->info('Test');

        $records = $this->handler->getRecords();
        self::assertSame('test', $records[0]['context']['_channel']);
    }

    #[Test]
    public function noHandlersDoesNotThrow(): void
    {
        $logger = new Logger('empty');
        $logger->info('This should not throw');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function handlerIsSkippedWhenNotHandling(): void
    {
        $skippingHandler = new class implements \WpPack\Component\Logger\Handler\HandlerInterface {
            public bool $called = false;

            public function isHandling(string $level): bool
            {
                return false;
            }

            public function handle(string $level, string $message, array $context): void
            {
                $this->called = true;
            }
        };

        $logger = new Logger('test');
        $logger->pushHandler($skippingHandler);
        $logger->info('Test');

        self::assertFalse($skippingHandler->called);
    }

    #[Test]
    public function getLevelSeverity(): void
    {
        self::assertSame(0, Logger::getLevelSeverity('emergency'));
        self::assertSame(3, Logger::getLevelSeverity('error'));
        self::assertSame(7, Logger::getLevelSeverity('debug'));
    }

    #[Test]
    public function getLevelSeverityWithInvalidLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Logger::getLevelSeverity('invalid');
    }
}
