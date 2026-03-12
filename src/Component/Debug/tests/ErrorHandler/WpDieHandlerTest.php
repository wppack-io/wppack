<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\WpDieHandler;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

final class WpDieHandlerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    #[Test]
    public function handleHtmlRendersDebugPage(): void
    {
        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        $handler = new WpDieHandler(new ErrorRenderer(), $config);

        // Register with a dummy previous handler to initialize state
        $handler->registerHtmlHandler('_default_wp_die_handler');

        ob_start();
        @$handler->handleHtml('Database connection failed', 'DB Error', ['response' => 500, 'exit' => false]);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('wp_die()', $output);
        self::assertStringContainsString('Database connection failed', $output);
        self::assertStringContainsString('<html', $output);
        self::assertStringContainsString('HTTP 500', $output);
    }

    #[Test]
    public function handleHtmlWithWpErrorRendersDebugPage(): void
    {
        if (!class_exists(\WP_Error::class)) {
            self::markTestSkipped('WP_Error class is not available.');
        }

        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        $handler = new WpDieHandler(new ErrorRenderer(), $config);
        $handler->registerHtmlHandler('_default_wp_die_handler');

        $wpError = new \WP_Error('db_error', 'Could not connect to database');

        ob_start();
        @$handler->handleHtml($wpError, 'Database Error', ['response' => 500, 'exit' => false]);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('WP_Error (db_error)', $output);
        self::assertStringContainsString('Could not connect to database', $output);
    }

    #[Test]
    public function handleHtmlDelegatesToPreviousWhenAccessDenied(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $config = new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']);
        $handler = new WpDieHandler(new ErrorRenderer(), $config);

        $previousCalled = false;
        $capturedMessage = null;
        $capturedTitle = null;

        $previousHandler = function (string|\WP_Error $message, string $title, array $args) use (&$previousCalled, &$capturedMessage, &$capturedTitle): void {
            $previousCalled = true;
            $capturedMessage = $message;
            $capturedTitle = $title;
        };

        $handler->registerHtmlHandler($previousHandler);

        $handler->handleHtml('Access denied', 'Forbidden', ['response' => 403, 'exit' => false]);

        self::assertTrue($previousCalled, 'Expected previous handler to be called');
        self::assertSame('Access denied', $capturedMessage);
        self::assertSame('Forbidden', $capturedTitle);
    }

    #[Test]
    public function handleAjaxReturnsJson(): void
    {
        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        $handler = new WpDieHandler(new ErrorRenderer(), $config);
        $handler->registerAjaxHandler('_default_wp_die_handler');

        ob_start();
        @$handler->handleAjax('AJAX error occurred', 'Error', ['response' => 400, 'exit' => false]);
        $output = ob_get_clean();

        self::assertIsString($output);

        $data = json_decode($output, true);
        self::assertIsArray($data);
        self::assertTrue($data['error']);
        self::assertSame('AJAX error occurred', $data['message']);
        self::assertSame(400, $data['status']);
        self::assertArrayHasKey('wp_error_codes', $data);
        self::assertArrayHasKey('file', $data);
        self::assertArrayHasKey('line', $data);
    }

    #[Test]
    public function handleJsonReturnsJson(): void
    {
        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        $handler = new WpDieHandler(new ErrorRenderer(), $config);
        $handler->registerJsonHandler('_default_wp_die_handler');

        ob_start();
        @$handler->handleJson('JSON error occurred', 'Error', ['response' => 422, 'exit' => false]);
        $output = ob_get_clean();

        self::assertIsString($output);

        $data = json_decode($output, true);
        self::assertIsArray($data);
        self::assertTrue($data['error']);
        self::assertSame('JSON error occurred', $data['message']);
        self::assertSame(422, $data['status']);
    }

    #[Test]
    public function registerHtmlHandlerReturnsSelfCallable(): void
    {
        $handler = new WpDieHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true),
        );

        $result = $handler->registerHtmlHandler('_default_wp_die_handler');

        self::assertIsCallable($result);
    }

    #[Test]
    public function registerIsNoOpWithoutWordPress(): void
    {
        // If add_filter is not available, register() should not throw
        if (function_exists('add_filter')) {
            self::markTestSkipped('add_filter() is available; cannot test no-op behavior.');
        }

        $handler = new WpDieHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true),
        );

        $handler->register();

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function handleAjaxDelegatesToPreviousWhenAccessDenied(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $config = new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']);
        $handler = new WpDieHandler(new ErrorRenderer(), $config);

        $previousCalled = false;

        $handler->registerAjaxHandler(function () use (&$previousCalled): void {
            $previousCalled = true;
        });

        $handler->handleAjax('denied', '', ['exit' => false]);

        self::assertTrue($previousCalled);
    }

    #[Test]
    public function handleJsonDelegatesToPreviousWhenAccessDenied(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $config = new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']);
        $handler = new WpDieHandler(new ErrorRenderer(), $config);

        $previousCalled = false;

        $handler->registerJsonHandler(function () use (&$previousCalled): void {
            $previousCalled = true;
        });

        $handler->handleJson('denied', '', ['exit' => false]);

        self::assertTrue($previousCalled);
    }

    #[Test]
    public function handleHtmlWithDefaultStatusCode(): void
    {
        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        $handler = new WpDieHandler(new ErrorRenderer(), $config);
        $handler->registerHtmlHandler('_default_wp_die_handler');

        ob_start();
        @$handler->handleHtml('Error without status', 'Error', ['exit' => false]);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('HTTP 500', $output);
    }

    #[Test]
    public function registerAjaxHandlerReturnsSelfCallable(): void
    {
        $handler = new WpDieHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true),
        );

        $result = $handler->registerAjaxHandler('_default_wp_die_handler');

        self::assertIsCallable($result);
    }

    #[Test]
    public function registerJsonHandlerReturnsSelfCallable(): void
    {
        $handler = new WpDieHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true),
        );

        $result = $handler->registerJsonHandler('_default_wp_die_handler');

        self::assertIsCallable($result);
    }

    #[Test]
    public function handleAjaxWithWpErrorReturnsErrorCodes(): void
    {
        if (!class_exists(\WP_Error::class)) {
            self::markTestSkipped('WP_Error class is not available.');
        }

        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        $handler = new WpDieHandler(new ErrorRenderer(), $config);
        $handler->registerAjaxHandler('_default_wp_die_handler');

        $wpError = new \WP_Error('invalid_nonce', 'The nonce is invalid');

        ob_start();
        @$handler->handleAjax($wpError, 'Nonce Error', ['response' => 403, 'exit' => false]);
        $output = ob_get_clean();

        self::assertIsString($output);

        $data = json_decode($output, true);
        self::assertIsArray($data);
        self::assertTrue($data['error']);
        self::assertSame(403, $data['status']);
        self::assertContains('invalid_nonce', $data['wp_error_codes']);
    }

    #[Test]
    public function setProfileUpdatesProfile(): void
    {
        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        $toolbarRenderer = new ToolbarRenderer();
        $handler = new WpDieHandler(new ErrorRenderer(), $config, $toolbarRenderer);
        $handler->setProfile(new Profile('test'));
        $handler->registerHtmlHandler('_default_wp_die_handler');

        ob_start();
        @$handler->handleHtml('toolbar test', 'Error', ['response' => 500, 'exit' => false]);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('wppack-debug', $output);
    }

    #[Test]
    public function handleHtmlStripsHtmlTags(): void
    {
        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        $handler = new WpDieHandler(new ErrorRenderer(), $config);
        $handler->registerHtmlHandler('_default_wp_die_handler');

        ob_start();
        @$handler->handleHtml('<strong>Bold</strong> message with <a href="#">link</a>', 'Error', ['response' => 500, 'exit' => false]);
        $output = ob_get_clean();

        self::assertIsString($output);
        // strip_tags removes HTML, so the rendered output should contain the plain text
        self::assertStringContainsString('Bold message with link', $output);
    }
}
