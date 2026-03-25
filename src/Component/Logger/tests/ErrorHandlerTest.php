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

namespace WpPack\Component\Logger\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Logger\ChannelResolver\DefaultChannelResolver;
use WpPack\Component\Logger\ErrorHandler;
use WpPack\Component\Logger\Handler\HandlerInterface;
use WpPack\Component\Logger\LoggerFactory;

final class ErrorHandlerTest extends TestCase
{
    private ErrorHandler $errorHandler;

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $loggedEntries = [];

    private function createErrorHandler(bool $captureAllErrors = true): ErrorHandler
    {
        $handler = new class ($this->loggedEntries) implements HandlerInterface {
            /** @param list<array{level: string, message: string, context: array<string, mixed>}> $entries */
            public function __construct(private array &$entries) {}

            public function isHandling(string $level): bool
            {
                return true;
            }

            public function handle(string $level, string $message, array $context): void
            {
                $this->entries[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };

        $factory = new LoggerFactory([$handler]);
        $resolver = new DefaultChannelResolver();

        return new ErrorHandler($factory, $resolver, $captureAllErrors);
    }

    protected function setUp(): void
    {
        $this->errorHandler = $this->createErrorHandler();

        // Push a no-op error handler so our ErrorHandler does not chain
        // to PHPUnit's handler (which would report intentional trigger_error()
        // calls as test warnings/notices/deprecations).
        set_error_handler(static fn(): bool => true);
    }

    protected function tearDown(): void
    {
        $this->errorHandler->restore();
        restore_error_handler(); // Remove the no-op, restoring PHPUnit's handler
        $this->loggedEntries = [];
    }

    #[Test]
    public function registerInstallsErrorHandler(): void
    {
        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL);
        try {
            trigger_error('Test warning', E_USER_WARNING);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertNotEmpty($this->loggedEntries);
        $entry = $this->loggedEntries[array_key_last($this->loggedEntries)];
        self::assertSame('warning', $entry['level']);
        self::assertSame('Test warning', $entry['message']);
    }

    #[Test]
    public function capturesDeprecationAsNoticeWithType(): void
    {
        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL);
        try {
            trigger_error('Deprecated feature', E_USER_DEPRECATED);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertCount(1, $this->loggedEntries);
        $entry = $this->loggedEntries[0];
        self::assertSame('notice', $entry['level']);
        self::assertSame('Deprecated feature', $entry['message']);
        self::assertSame('deprecation', $entry['context']['_type']);
        self::assertSame('E_USER_DEPRECATED', $entry['context']['_error_type']);
        self::assertSame('php', $entry['context']['_channel']);
    }

    #[Test]
    public function capturesNotice(): void
    {
        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL);
        try {
            trigger_error('A notice', E_USER_NOTICE);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertCount(1, $this->loggedEntries);
        self::assertSame('notice', $this->loggedEntries[0]['level']);
        self::assertArrayNotHasKey('_type', $this->loggedEntries[0]['context']);
    }

    #[Test]
    public function capturesWarning(): void
    {
        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL);
        try {
            trigger_error('A warning', E_USER_WARNING);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertCount(1, $this->loggedEntries);
        self::assertSame('warning', $this->loggedEntries[0]['level']);
        self::assertSame('E_USER_WARNING', $this->loggedEntries[0]['context']['_error_type']);
    }

    #[Test]
    public function captureAllErrorsCapturesSuppressedErrors(): void
    {
        $this->errorHandler->register();

        $previousLevel = error_reporting(0);
        try {
            trigger_error('Suppressed', E_USER_WARNING);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertNotEmpty($this->loggedEntries);
        self::assertSame('warning', $this->loggedEntries[0]['level']);
        self::assertSame('Suppressed', $this->loggedEntries[0]['message']);
    }

    #[Test]
    public function captureAllErrorsCapturesDeprecationEvenWhenMasked(): void
    {
        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL & ~E_USER_DEPRECATED);
        try {
            trigger_error('Masked deprecation', E_USER_DEPRECATED);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertCount(1, $this->loggedEntries);
        self::assertSame('notice', $this->loggedEntries[0]['level']);
        self::assertSame('Masked deprecation', $this->loggedEntries[0]['message']);
        self::assertSame('deprecation', $this->loggedEntries[0]['context']['_type']);
    }

    #[Test]
    public function legacyModeRespectsSuppression(): void
    {
        $this->errorHandler->restore();
        $this->errorHandler = $this->createErrorHandler(captureAllErrors: false);
        $this->errorHandler->register();

        $previousLevel = error_reporting(0);
        try {
            trigger_error('Suppressed', E_USER_WARNING);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertEmpty($this->loggedEntries);
    }

    #[Test]
    public function legacyModeRespectsErrorReportingMask(): void
    {
        $this->errorHandler->restore();
        $this->errorHandler = $this->createErrorHandler(captureAllErrors: false);
        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL & ~E_USER_DEPRECATED);
        try {
            trigger_error('Masked deprecation', E_USER_DEPRECATED);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertEmpty($this->loggedEntries);
    }

    #[Test]
    public function returnsTrueToStopPhpHandler(): void
    {
        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL);
        try {
            $result = @trigger_error('Test', E_USER_NOTICE);
        } finally {
            error_reporting($previousLevel);
        }

        // trigger_error returns true when the error handler returns true
        self::assertTrue($result);
    }

    #[Test]
    public function restoreRemovesHandler(): void
    {
        $this->errorHandler->register();
        $this->errorHandler->restore();

        // After restore, our handler should not capture
        // We can verify by checking that a second register+trigger works
        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL);
        try {
            trigger_error('After re-register', E_USER_NOTICE);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertCount(1, $this->loggedEntries);
    }

    #[Test]
    public function registerIsIdempotent(): void
    {
        $this->errorHandler->register();
        $this->errorHandler->register(); // Should not install twice

        $previousLevel = error_reporting(E_ALL);
        try {
            trigger_error('Only once', E_USER_NOTICE);
        } finally {
            error_reporting($previousLevel);
        }

        // Should only be captured once, not duplicated
        self::assertCount(1, $this->loggedEntries);
    }

    #[Test]
    public function restoreIsIdempotent(): void
    {
        $this->errorHandler->restore(); // Should not fail when not registered
        $this->errorHandler->register();
        $this->errorHandler->restore();
        $this->errorHandler->restore(); // Should not fail on double restore

        self::assertEmpty($this->loggedEntries);
    }

    #[Test]
    public function contextIncludesFileAndLine(): void
    {
        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL);
        try {
            trigger_error('With file info', E_USER_WARNING);
        } finally {
            error_reporting($previousLevel);
        }

        self::assertCount(1, $this->loggedEntries);
        $context = $this->loggedEntries[0]['context'];
        self::assertArrayHasKey('_file', $context);
        self::assertArrayHasKey('_line', $context);
        self::assertNotEmpty($context['_file']);
        self::assertGreaterThan(0, $context['_line']);
    }

    #[Test]
    public function callsPreviousErrorHandler(): void
    {
        $previousCalled = false;
        $previousHandler = set_error_handler(function () use (&$previousCalled): bool {
            $previousCalled = true;

            return false;
        });

        try {
            $this->errorHandler->register();

            $previousLevel = error_reporting(E_ALL);
            try {
                trigger_error('Chain test', E_USER_NOTICE);
            } finally {
                error_reporting($previousLevel);
            }

            self::assertTrue($previousCalled, 'Previous error handler should have been called');
        } finally {
            $this->errorHandler->restore();
            restore_error_handler(); // Remove the test handler we set
        }
    }

    #[Test]
    public function preventsReentrantCalls(): void
    {
        // Use reflection to verify the handling flag mechanism
        $ref = new \ReflectionProperty($this->errorHandler, 'handling');

        self::assertFalse($ref->getValue($this->errorHandler));

        $this->errorHandler->register();

        $previousLevel = error_reporting(E_ALL);
        try {
            trigger_error('Normal call', E_USER_NOTICE);
        } finally {
            error_reporting($previousLevel);
        }

        // After the call completes, handling should be false again
        self::assertFalse($ref->getValue($this->errorHandler));
        self::assertCount(1, $this->loggedEntries);
    }

    #[Test]
    public function reentrantCallReturnsFalse(): void
    {
        // Simulate a re-entrant error by manually setting the handling flag
        $ref = new \ReflectionProperty($this->errorHandler, 'handling');

        $this->errorHandler->register();

        // Set handling to true to simulate re-entrancy
        $ref->setValue($this->errorHandler, true);

        // Suppress notice output: when ErrorHandler detects re-entrancy it returns false,
        // which causes PHP to emit the notice unless error_reporting is 0.
        $previousLevel = error_reporting(0);
        try {
            trigger_error('Reentrant', E_USER_NOTICE);
        } finally {
            error_reporting($previousLevel);
            $ref->setValue($this->errorHandler, false);
        }

        // The re-entrant call should have been skipped
        self::assertEmpty($this->loggedEntries);
    }

    #[Test]
    public function handlerRecoveriesFromExceptionInLogger(): void
    {
        // Create a handler that throws an exception
        $handler = new class implements HandlerInterface {
            public function isHandling(string $level): bool
            {
                return true;
            }

            public function handle(string $level, string $message, array $context): void
            {
                throw new \RuntimeException('Logger failed');
            }
        };

        $factory = new LoggerFactory([$handler]);
        $resolver = new DefaultChannelResolver();
        $errorHandler = new ErrorHandler($factory, $resolver);

        // Push a no-op so ErrorHandler chains to it instead of PHPUnit's handler
        set_error_handler(static fn(): bool => true);

        try {
            $errorHandler->register();

            $previousLevel = error_reporting(E_ALL);
            try {
                // The trigger_error call triggers handleError, which throws because
                // the handler throws. The handling flag should be reset in the finally block.
                trigger_error('Trigger', E_USER_NOTICE);
            } catch (\RuntimeException) {
                // Expected
            } finally {
                error_reporting($previousLevel);
            }

            // Verify handling flag is reset (no re-entrancy protection leak)
            $ref = new \ReflectionProperty($errorHandler, 'handling');
            self::assertFalse($ref->getValue($errorHandler));
        } finally {
            $errorHandler->restore();
            restore_error_handler();
        }
    }

    #[Test]
    public function capturesUnknownErrorTypeAsWarning(): void
    {
        $this->errorHandler->register();

        // Simulate calling the error handler with an unknown error type
        $handleError = new \ReflectionMethod($this->errorHandler, 'handleError');

        $handleError->invoke($this->errorHandler, 0, 'Unknown error type', __FILE__, __LINE__);

        self::assertCount(1, $this->loggedEntries);
        self::assertSame('warning', $this->loggedEntries[0]['level']);
        self::assertSame('E_UNKNOWN', $this->loggedEntries[0]['context']['_error_type']);
    }
}
