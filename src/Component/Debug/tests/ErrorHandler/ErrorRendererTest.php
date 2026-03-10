<?php

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
}
