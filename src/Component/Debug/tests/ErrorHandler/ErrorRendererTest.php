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
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\FlattenException;

final class ErrorRendererTest extends TestCase
{
    private ErrorRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ErrorRenderer();
    }

    #[Test]
    public function renderOutputIsValidHtmlContainingExceptionClassName(): void
    {
        $exception = new \RuntimeException('Test error');
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        self::assertStringContainsString('RuntimeException', $html);
    }

    #[Test]
    public function renderOutputContainsEscapedExceptionMessage(): void
    {
        $exception = new \RuntimeException('Error with <script>alert("xss")</script>');
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        // The message should be escaped, not contain raw HTML
        self::assertStringContainsString('Error with', $html);
        self::assertStringNotContainsString('<script>alert("xss")</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function renderOutputContainsFilePath(): void
    {
        $exception = new \RuntimeException('file path test');
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        // The file path should appear in the output (possibly escaped)
        self::assertStringContainsString(htmlspecialchars(__FILE__, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
    }

    #[Test]
    public function renderOutputContainsStackTraceText(): void
    {
        $exception = new \RuntimeException('stack trace test');
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        self::assertStringContainsString('Stack Trace', $html);
    }

    #[Test]
    public function renderOutputContainsInlineCss(): void
    {
        $exception = new \RuntimeException('css test');
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        self::assertStringContainsString('<style>', $html);
        self::assertStringContainsString('</style>', $html);
    }

    #[Test]
    public function renderOutputContainsInlineJs(): void
    {
        $exception = new \RuntimeException('js test');
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        self::assertStringContainsString('<script>', $html);
        self::assertStringContainsString('</script>', $html);
    }

    #[Test]
    public function renderOutputHasProperHtmlStructure(): void
    {
        $exception = new \RuntimeException('structure test');
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        self::assertStringContainsString('<html', $html);
        self::assertStringContainsString('<head>', $html);
        self::assertStringContainsString('<body>', $html);
        self::assertStringContainsString('</html>', $html);
        self::assertStringContainsString('</head>', $html);
        self::assertStringContainsString('</body>', $html);
    }

    #[Test]
    public function renderFor404StatusContains404(): void
    {
        $exception = new class ('Not Found') extends \RuntimeException {
            public function getStatusCode(): int
            {
                return 404;
            }
        };
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        self::assertStringContainsString('404', $html);
    }

    #[Test]
    public function renderShowsChainedExceptions(): void
    {
        $inner = new \LogicException('inner cause');
        $outer = new \RuntimeException('outer error', 0, $inner);
        $flat = FlattenException::createFromThrowable($outer);

        $html = $this->renderer->render($flat);

        self::assertStringContainsString('RuntimeException', $html);
        self::assertStringContainsString('outer error', $html);
        self::assertStringContainsString('Previous Exceptions', $html);
        self::assertStringContainsString('LogicException', $html);
        self::assertStringContainsString('inner cause', $html);
    }

    #[Test]
    public function renderAppendsToolbarHtml(): void
    {
        $exception = new \RuntimeException('toolbar test');
        $flat = FlattenException::createFromThrowable($exception);
        $toolbarHtml = '<div id="wppack-debug">toolbar content</div>';

        $html = $this->renderer->render($flat, $toolbarHtml);

        self::assertStringContainsString('wppack-debug', $html);
        self::assertStringContainsString('toolbar content', $html);
    }

    #[Test]
    public function renderShowsExceptionCode(): void
    {
        $exception = new \RuntimeException('coded error', 42);
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        self::assertStringContainsString('code 42', $html);
    }

    #[Test]
    public function renderWithZeroCodeOmitsCodeLabel(): void
    {
        $exception = new \RuntimeException('no code error', 0);
        $flat = FlattenException::createFromThrowable($exception);

        $html = $this->renderer->render($flat);

        self::assertStringNotContainsString('<span class="exception-code">', $html);
        self::assertStringContainsString('no code error', $html);
    }

    #[Test]
    public function shortClassNameWithNamespace(): void
    {
        self::assertSame('RuntimeException', $this->renderer->shortClassName('RuntimeException'));
        self::assertSame('FlattenException', $this->renderer->shortClassName('WpPack\\Component\\Debug\\ErrorHandler\\FlattenException'));
    }

    #[Test]
    public function shortClassNameWithoutNamespace(): void
    {
        self::assertSame('MyClass', $this->renderer->shortClassName('MyClass'));
    }

    #[Test]
    public function shortenPathWithAbspath(): void
    {
        // ABSPATH is defined by WordPress in the test environment
        $path = ABSPATH . 'wp-content/plugins/my-plugin/file.php';

        $result = $this->renderer->shortenPath($path);

        self::assertSame('wp-content/plugins/my-plugin/file.php', $result);
    }

    #[Test]
    public function shortenPathWithVendorPath(): void
    {
        $path = '/home/user/project/vendor/some-package/src/SomeClass.php';

        $result = $this->renderer->shortenPath($path);

        self::assertSame('.../vendor/some-package/src/SomeClass.php', $result);
    }

    #[Test]
    public function shortenPathWithUnknownPath(): void
    {
        $path = '/opt/other/location/file.php';

        // This path does not start with ABSPATH and has no /vendor/
        $result = $this->renderer->shortenPath($path);

        self::assertSame($path, $result);
    }

    #[Test]
    public function escapeEscapesHtmlSpecialChars(): void
    {
        $result = $this->renderer->escape('<div class="test">value & more</div>');

        self::assertSame('&lt;div class=&quot;test&quot;&gt;value &amp; more&lt;/div&gt;', $result);
    }

    #[Test]
    public function getPhpRendererReturnsLazyInstanceWhenNoInjection(): void
    {
        $renderer = new ErrorRenderer();

        $phpRenderer = $renderer->getPhpRenderer();

        self::assertInstanceOf(\WpPack\Component\Templating\PhpRenderer::class, $phpRenderer);
        // Same instance returned on second call
        self::assertSame($phpRenderer, $renderer->getPhpRenderer());
    }

    #[Test]
    public function getPhpRendererReturnsInjectedRenderer(): void
    {
        $phpRenderer = new \WpPack\Component\Templating\PhpRenderer([
            dirname(__DIR__, 2) . '/templates',
        ]);
        $renderer = new ErrorRenderer($phpRenderer);

        self::assertSame($phpRenderer, $renderer->getPhpRenderer());
    }
}
