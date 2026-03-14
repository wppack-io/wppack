<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

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

    /**
     * @return int The user ID (0 if WordPress is not loaded)
     */
    private function setUpAdminUser(): int
    {
        if (!function_exists('wp_insert_user')) {
            return 0;
        }

        $userId = wp_insert_user([
            'user_login' => 'test_exc_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'exc_' . uniqid() . '@example.com',
        ]);

        wp_set_current_user($userId);

        return $userId;
    }

    private function tearDownAdminUser(int $userId): void
    {
        if ($userId > 0 && function_exists('wp_set_current_user')) {
            wp_set_current_user(0);
            wp_delete_user($userId);
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
        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $handler = new ExceptionHandler(new ErrorRenderer(), $config);

            $exception = new \RuntimeException('enabled debug test');

            ob_start();
            @$handler->handleException($exception);
            $output = ob_get_clean();

            self::assertIsString($output);
            self::assertStringContainsString('RuntimeException', $output);
            self::assertStringContainsString('enabled debug test', $output);
            self::assertStringContainsString('<html', $output);
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function onRoutingExceptionDelegatesToHandleException(): void
    {
        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $handler = new ExceptionHandler(new ErrorRenderer(), $config);

            $exception = new \RuntimeException('routing exception test');

            ob_start();
            @$handler->onRoutingException($exception);
            $output = ob_get_clean();

            self::assertIsString($output);
            self::assertStringContainsString('RuntimeException', $output);
            self::assertStringContainsString('routing exception test', $output);
        } finally {
            $this->tearDownAdminUser($userId);
        }
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

        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $handler = new ExceptionHandler(new ErrorRenderer(), $config);

            ob_start();
            @$handler->handleException(new \RuntimeException('ip allowed test'));
            $output = ob_get_clean();

            self::assertIsString($output);
            self::assertStringContainsString('ip allowed test', $output);
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function handleExceptionWithToolbarRendersToolbar(): void
    {
        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $profile = new Profile('test-token');
            $toolbarRenderer = new ToolbarRenderer($profile);

            $handler = new ExceptionHandler(new ErrorRenderer(), $config, $toolbarRenderer, $profile);

            ob_start();
            @$handler->handleException(new \RuntimeException('toolbar test'));
            $output = ob_get_clean();

            self::assertIsString($output);
            self::assertStringContainsString('toolbar test', $output);
            self::assertStringContainsString('wppack-debug', $output);
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function handleExceptionWithoutToolbarRendererReturnsNoToolbar(): void
    {
        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $handler = new ExceptionHandler(new ErrorRenderer(), $config);

            ob_start();
            @$handler->handleException(new \RuntimeException('no toolbar'));
            $output = ob_get_clean();

            self::assertIsString($output);
            self::assertStringContainsString('no toolbar', $output);
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function setProfileUpdatesProfile(): void
    {
        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $profile = new Profile('test');
            $toolbarRenderer = new ToolbarRenderer($profile);
            $handler = new ExceptionHandler(new ErrorRenderer(), $config, $toolbarRenderer);
            $handler->setProfile($profile);

            ob_start();
            @$handler->handleException(new \RuntimeException('profile test'));
            $output = ob_get_clean();

            self::assertIsString($output);
            self::assertStringContainsString('wppack-debug', $output);
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function handleExceptionWithWordPressAdminAccess(): void
    {
        $userId = $this->setUpAdminUser();

        if ($userId === 0) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        try {
            $config = new DebugConfig(enabled: true, roleWhitelist: ['administrator']);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $handler = new ExceptionHandler(new ErrorRenderer(), $config);

            ob_start();
            @$handler->handleException(new \RuntimeException('admin access test'));
            $output = ob_get_clean();

            self::assertIsString($output);
            self::assertStringContainsString('admin access test', $output);
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function handleExceptionDeniedForSubscriber(): void
    {
        if (!function_exists('wp_insert_user')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $userId = wp_insert_user([
            'user_login' => 'test_exc_sub_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
            'user_email' => 'exc_sub@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $config = new DebugConfig(enabled: true, roleWhitelist: ['administrator']);
            $handler = new ExceptionHandler(new ErrorRenderer(), $config);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('subscriber denied');

            $handler->handleException(new \RuntimeException('subscriber denied'));
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }
}
