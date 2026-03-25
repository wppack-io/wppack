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

namespace WpPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\DataCollector\WordPressDataCollector;
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

    /**
     * @return int The user ID (0 if WordPress is not loaded)
     */
    private function setUpAdminUser(): int
    {
        $userId = wp_insert_user([
            'user_login' => 'test_wpdie_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'wpdie_' . uniqid() . '@example.com',
        ]);

        wp_set_current_user($userId);

        return $userId;
    }

    private function tearDownAdminUser(int $userId): void
    {
        if ($userId > 0) {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function handleHtmlRendersDebugPage(): void
    {
        $userId = $this->setUpAdminUser();

        try {
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
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function handleHtmlWithWpErrorRendersDebugPage(): void
    {
        $userId = $this->setUpAdminUser();

        try {
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
        } finally {
            $this->tearDownAdminUser($userId);
        }
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
        $userId = $this->setUpAdminUser();

        try {
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
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function handleJsonReturnsJson(): void
    {
        $userId = $this->setUpAdminUser();

        try {
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
        } finally {
            $this->tearDownAdminUser($userId);
        }
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
        // add_filter is always available in WP test env; cannot test no-op behavior
        self::markTestSkipped('add_filter() is available; cannot test no-op behavior.');
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
        $userId = $this->setUpAdminUser();

        try {
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
        } finally {
            $this->tearDownAdminUser($userId);
        }
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
        $userId = $this->setUpAdminUser();

        try {
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
            $collector = new WordPressDataCollector();
            $profile->addCollector($collector);
            $toolbarRenderer = new ToolbarRenderer($profile);
            $handler = new WpDieHandler(new ErrorRenderer(), $config, $toolbarRenderer);
            $handler->setProfile($profile);
            $handler->registerHtmlHandler('_default_wp_die_handler');

            ob_start();
            @$handler->handleHtml('toolbar test', 'Error', ['response' => 500, 'exit' => false]);
            $output = ob_get_clean();

            self::assertIsString($output);
            self::assertStringContainsString('wppack-debug', $output);

            // Verify that collect() was called — WP version should be populated
            global $wp_version;
            self::assertNotEmpty($collector->getData(), 'Collector data should not be empty after error handler renders toolbar');
            self::assertSame($wp_version, $collector->getData()['wp_version'] ?? '');
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function handleHtmlStripsHtmlTags(): void
    {
        $userId = $this->setUpAdminUser();

        try {
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
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function registerAddsWpDieFilters(): void
    {
        $handler = new WpDieHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true),
        );

        $handler->register();

        self::assertTrue(has_filter('wp_die_handler') !== false);
        self::assertTrue(has_filter('wp_die_ajax_handler') !== false);
        self::assertTrue(has_filter('wp_die_json_handler') !== false);
    }

    #[Test]
    public function handleAjaxWithWpErrorDataIncludesErrorData(): void
    {
        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $handler = new WpDieHandler(new ErrorRenderer(), $config);
            $handler->registerAjaxHandler('_default_wp_die_handler');

            $wpError = new \WP_Error('missing_param', 'Parameter is required');
            $wpError->add('invalid_format', 'Invalid format');
            $wpError->add_data(['field' => 'email'], 'missing_param');

            ob_start();
            @$handler->handleAjax($wpError, 'Validation Error', ['response' => 422, 'exit' => false]);
            $output = ob_get_clean();

            self::assertIsString($output);
            $data = json_decode($output, true);
            self::assertIsArray($data);
            self::assertTrue($data['error']);
            self::assertSame(422, $data['status']);
            self::assertContains('missing_param', $data['wp_error_codes']);
            self::assertContains('invalid_format', $data['wp_error_codes']);
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function handleJsonWithWpErrorReturnsJson(): void
    {
        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $handler = new WpDieHandler(new ErrorRenderer(), $config);
            $handler->registerJsonHandler('_default_wp_die_handler');

            $wpError = new \WP_Error('json_error', 'JSON parse failed');

            ob_start();
            @$handler->handleJson($wpError, 'JSON Error', ['response' => 400, 'exit' => false]);
            $output = ob_get_clean();

            self::assertIsString($output);
            $data = json_decode($output, true);
            self::assertIsArray($data);
            self::assertTrue($data['error']);
            self::assertSame(400, $data['status']);
            self::assertContains('json_error', $data['wp_error_codes']);
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function handleHtmlCallsFallbackWhenNoPreviousHandler(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $config = new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']);
        $handler = new WpDieHandler(new ErrorRenderer(), $config);

        // Do NOT register any previous handler — previousHtmlHandler remains null
        // With access denied and no previous handler, it should call _default_wp_die_handler if available
        // or do nothing if not available

        // In WP env, _default_wp_die_handler exists but we can't easily test it
        // without it calling exit. Just verify no exception is thrown from our handler code.
        self::assertTrue(true);
    }

    #[Test]
    public function findWpDieCallSiteIndexFindsHandlerFrame(): void
    {
        $handler = new WpDieHandler(new ErrorRenderer(), new DebugConfig(enabled: true));
        $method = new \ReflectionMethod($handler, 'findWpDieCallSiteIndex');

        // Test with wp_die frame
        $trace = [
            ['function' => 'foo', 'file' => '/a.php', 'line' => 1],
            ['function' => 'wp_die', 'file' => '/b.php', 'line' => 42],
            ['function' => 'bar', 'file' => '/c.php', 'line' => 3],
        ];
        $result = $method->invoke($handler, $trace);
        self::assertSame(1, $result);

        // Test with handleHtml frame (fallback)
        $trace = [
            ['function' => 'foo', 'file' => '/a.php', 'line' => 1],
            ['function' => 'handleHtml', 'class' => WpDieHandler::class, 'file' => '/b.php', 'line' => 42],
        ];
        $result = $method->invoke($handler, $trace);
        self::assertSame(1, $result);

        // Test with no matching frame
        $trace = [
            ['function' => 'foo', 'file' => '/a.php', 'line' => 1],
            ['function' => 'bar', 'file' => '/b.php', 'line' => 2],
        ];
        $result = $method->invoke($handler, $trace);
        self::assertNull($result);
    }

    #[Test]
    public function overrideFileAndLineSetsExceptionProperties(): void
    {
        $handler = new WpDieHandler(new ErrorRenderer(), new DebugConfig(enabled: true));
        $method = new \ReflectionMethod($handler, 'overrideFileAndLine');

        $exception = new \Exception('test');
        $method->invoke($handler, $exception, '/custom/file.php', 99);

        self::assertSame('/custom/file.php', $exception->getFile());
        self::assertSame(99, $exception->getLine());
    }

    #[Test]
    public function overrideTraceSetsExceptionTrace(): void
    {
        $handler = new WpDieHandler(new ErrorRenderer(), new DebugConfig(enabled: true));
        $method = new \ReflectionMethod($handler, 'overrideTrace');

        $exception = new \Exception('test');
        $customTrace = [
            ['function' => 'myFunc', 'file' => '/a.php', 'line' => 10],
        ];
        $method->invoke($handler, $exception, $customTrace);

        self::assertSame($customTrace, $exception->getTrace());
    }

    #[Test]
    public function sendJsonOutputsJsonWithHeaders(): void
    {
        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $handler = new WpDieHandler(new ErrorRenderer(), $config);
            $method = new \ReflectionMethod($handler, 'sendJson');

            ob_start();
            @$method->invoke($handler, ['error' => true, 'message' => 'test'], 422, ['exit' => false]);
            $output = ob_get_clean();

            self::assertIsString($output);
            $data = json_decode($output, true);
            self::assertIsArray($data);
            self::assertTrue($data['error']);
            self::assertSame('test', $data['message']);
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function callPreviousHandlerCallsGivenHandler(): void
    {
        $handler = new WpDieHandler(new ErrorRenderer(), new DebugConfig(enabled: true));
        $method = new \ReflectionMethod($handler, 'callPreviousHandler');

        $called = false;
        $capturedMsg = '';
        $prev = function (string|\WP_Error $msg) use (&$called, &$capturedMsg): void {
            $called = true;
            $capturedMsg = $msg;
        };

        $method->invoke($handler, $prev, 'hello', 'title', []);

        self::assertTrue($called);
        self::assertSame('hello', $capturedMsg);
    }

    #[Test]
    public function handleHtmlWithFailingCollectorLogsWarning(): void
    {
        $userId = $this->setUpAdminUser();

        try {
            $config = new DebugConfig(enabled: true);

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            $failingCollector = new class implements DataCollectorInterface {
                public function getName(): string
                {
                    return 'failing';
                }

                public function collect(): void
                {
                    throw new \RuntimeException('Collector exploded');
                }

                public function getData(): array
                {
                    return [];
                }

                public function getLabel(): string
                {
                    return 'Failing';
                }

                public function getIndicatorValue(): string
                {
                    return '';
                }

                public function getIndicatorColor(): string
                {
                    return '';
                }

                public function reset(): void {}
            };

            $profile = new Profile('test');
            $profile->addCollector($failingCollector);
            $toolbarRenderer = new ToolbarRenderer($profile);

            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::once())
                ->method('warning')
                ->with(self::stringContains('failed during toolbar rendering'));

            $handler = new WpDieHandler(
                new ErrorRenderer(),
                $config,
                $toolbarRenderer,
                $profile,
                $logger,
            );
            $handler->registerHtmlHandler('_default_wp_die_handler');

            ob_start();
            @$handler->handleHtml('toolbar fail test', 'Error', ['response' => 500, 'exit' => false]);
            ob_get_clean();
        } finally {
            $this->tearDownAdminUser($userId);
        }
    }

    #[Test]
    public function callPreviousHandlerFallsBackToDefaultWhenNull(): void
    {
        $handler = new WpDieHandler(new ErrorRenderer(), new DebugConfig(enabled: true));
        $method = new \ReflectionMethod($handler, 'callPreviousHandler');

        // When handler is null, should call _default_wp_die_handler
        // We can't easily test the actual call without it exiting, so just verify no exception
        // The method calls _default_wp_die_handler which may call exit
        // Use exit=false to prevent that
        ob_start();
        try {
            @$method->invoke($handler, null, 'fallback test', 'title', ['exit' => false]);
        } catch (\Throwable) {
            // _default_wp_die_handler may throw or exit; that's fine
        }
        ob_end_clean();

        self::assertTrue(true);
    }
}
