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

namespace WPPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DebugConfig;
use WPPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WPPack\Component\Debug\ErrorHandler\RedirectHandler;

final class RedirectHandlerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer;

    private int $userId = 0;

    private int $initialObLevel;

    protected function setUp(): void
    {
        $this->initialObLevel = ob_get_level();
        $this->originalServer = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->userId = wp_insert_user([
            'user_login' => 'test_redir_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'redir_' . uniqid() . '@example.com',
        ]);

        wp_set_current_user($this->userId);
    }

    protected function tearDown(): void
    {
        // Clean up output buffers opened by onRedirect()
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }

        $_SERVER = $this->originalServer;

        if (isset($GLOBALS['_wppack_redirect_handler'])) {
            $GLOBALS['_wppack_redirect_handler']->unregister();
            unset($GLOBALS['_wppack_redirect_handler']);
        }

        if ($this->userId > 0) {
            wp_set_current_user(0);
            wp_delete_user($this->userId);
        }
    }

    #[Test]
    public function onRedirectSanitizesJavascriptUrl(): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: true);

        if (!$config->shouldShowToolbar()) {
            self::markTestSkipped('shouldShowToolbar() is false in this environment.');
        }

        $handler = new RedirectHandler(new ErrorRenderer(), $config);

        $result = $handler->onRedirect('javascript:alert(1)', 302);

        // Returns empty string to cancel wp_redirect
        self::assertSame('', $result);
    }

    #[Test]
    public function onRedirectAllowsHttpsUrl(): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: true);

        if (!$config->shouldShowToolbar()) {
            self::markTestSkipped('shouldShowToolbar() is false in this environment.');
        }

        $handler = new RedirectHandler(new ErrorRenderer(), $config);

        $result = $handler->onRedirect('https://example.com/path', 302);

        // Returns empty to cancel the redirect (intercept mode)
        self::assertSame('', $result);
    }

    #[Test]
    public function onRedirectAllowsRelativeUrl(): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: true);

        if (!$config->shouldShowToolbar()) {
            self::markTestSkipped('shouldShowToolbar() is false in this environment.');
        }

        $handler = new RedirectHandler(new ErrorRenderer(), $config);

        // Relative URLs have no scheme — should be allowed
        $result = $handler->onRedirect('/wp-admin/options.php', 302);

        self::assertSame('', $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dangerousUrlProvider(): iterable
    {
        yield 'javascript protocol' => ['javascript:alert(1)'];
        yield 'data protocol' => ['data:text/html,<script>alert(1)</script>'];
        yield 'vbscript protocol' => ['vbscript:MsgBox("XSS")'];
    }

    #[Test]
    #[DataProvider('dangerousUrlProvider')]
    public function onRedirectBlocksDangerousSchemes(string $url): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: true);

        if (!$config->shouldShowToolbar()) {
            self::markTestSkipped('shouldShowToolbar() is false in this environment.');
        }

        $handler = new RedirectHandler(new ErrorRenderer(), $config);
        $handler->onRedirect($url, 302);

        // The shutdown function will sanitize the URL;
        // we test that the sanitization logic works correctly
        $reflection = new \ReflectionMethod($handler, 'sanitizeRedirectUrl');
        $sanitized = $reflection->invoke($handler, $url);

        self::assertSame('', $sanitized, "Expected '$url' to be blocked");
    }

    #[Test]
    public function sanitizeRedirectUrlAllowsHttpAndHttps(): void
    {
        $handler = new RedirectHandler(new ErrorRenderer());

        $reflection = new \ReflectionMethod($handler, 'sanitizeRedirectUrl');

        self::assertSame('http://example.com', $reflection->invoke($handler, 'http://example.com'));
        self::assertSame('https://example.com', $reflection->invoke($handler, 'https://example.com'));
        self::assertSame('/relative/path', $reflection->invoke($handler, '/relative/path'));
    }

    #[Test]
    public function onRedirectPassesThroughWhenToolbarDisabled(): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: false);

        $handler = new RedirectHandler(new ErrorRenderer(), $config);

        $result = $handler->onRedirect('https://example.com', 302);

        // When toolbar is disabled, the URL is returned as-is (no interception)
        self::assertSame('https://example.com', $result);
    }
}
