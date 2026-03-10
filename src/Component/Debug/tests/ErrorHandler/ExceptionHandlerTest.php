<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;

final class ExceptionHandlerTest extends TestCase
{
    private ?\Closure $savedHandler = null;

    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;

        // Save the current exception handler so we can restore it
        $previous = set_exception_handler(null);
        restore_exception_handler();
        if ($previous !== null) {
            $this->savedHandler = $previous(...);
        }
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;

        // Restore the original exception handler
        // Clear any handler set during the test
        set_exception_handler(null);
        if ($this->savedHandler !== null) {
            set_exception_handler($this->savedHandler);
        }
    }

    #[Test]
    public function registerSetsExceptionHandler(): void
    {
        $handler = new ExceptionHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true),
        );

        $handler->register();

        // Retrieve the current exception handler
        $current = set_exception_handler(null);
        restore_exception_handler();

        self::assertNotNull($current, 'Expected an exception handler to be set');
    }

    #[Test]
    public function handleExceptionWithDisabledConfigThrowsException(): void
    {
        $handler = new ExceptionHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: false),
        );

        $exception = new \RuntimeException('disabled debug');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disabled debug');

        $handler->handleException($exception);
    }

    #[Test]
    public function handleExceptionWithEnabledConfigOutputsHtml(): void
    {
        $handler = new ExceptionHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true),
        );

        $exception = new \RuntimeException('enabled debug test');

        ob_start();
        @$handler->handleException($exception);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('RuntimeException', $output);
        self::assertStringContainsString('enabled debug test', $output);
        self::assertStringContainsString('<html', $output);
    }

    #[Test]
    public function onRoutingExceptionDelegatesToHandleException(): void
    {
        $handler = new ExceptionHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true),
        );

        $exception = new \RuntimeException('routing exception test');

        ob_start();
        @$handler->onRoutingException($exception);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('RuntimeException', $output);
        self::assertStringContainsString('routing exception test', $output);
    }

    #[Test]
    public function handleExceptionWithDisabledConfigDelegatesToPreviousHandler(): void
    {
        $previousCalled = false;
        $capturedThrowable = null;

        // Set a previous handler
        set_exception_handler(function (\Throwable $e) use (&$previousCalled, &$capturedThrowable): void {
            $previousCalled = true;
            $capturedThrowable = $e;
        });

        $handler = new ExceptionHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: false),
        );

        // Register to capture the previous handler
        $handler->register();

        $exception = new \RuntimeException('delegate test');

        $handler->handleException($exception);

        self::assertTrue($previousCalled, 'Expected previous handler to be called');
        self::assertSame($exception, $capturedThrowable);
    }

    #[Test]
    public function handleExceptionThrowsWhenIpNotAllowed(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $handler = new ExceptionHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ip not allowed');

        $handler->handleException(new \RuntimeException('ip not allowed'));
    }

    #[Test]
    public function handleExceptionRendersWhenIpIsAllowed(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $handler = new ExceptionHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']),
        );

        ob_start();
        @$handler->handleException(new \RuntimeException('ip allowed test'));
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('ip allowed test', $output);
    }
}
