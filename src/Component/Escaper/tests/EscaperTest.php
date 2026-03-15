<?php

declare(strict_types=1);

namespace WpPack\Component\Escaper\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Escaper\Escaper;

final class EscaperTest extends TestCase
{
    private Escaper $escaper;

    protected function setUp(): void
    {
        if (!function_exists('esc_html')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->escaper = new Escaper();
    }

    #[Test]
    public function htmlEscapesSpecialCharacters(): void
    {
        $result = $this->escaper->html('<script>alert("XSS")</script>');

        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('&lt;', $result);
    }

    #[Test]
    public function htmlPreservesPlainText(): void
    {
        self::assertSame('Hello World', $this->escaper->html('Hello World'));
    }

    #[Test]
    public function attrEscapesSpecialCharacters(): void
    {
        $result = $this->escaper->attr('" onclick="alert(1)');

        self::assertStringNotContainsString('"', $result);
    }

    #[Test]
    public function attrPreservesPlainText(): void
    {
        self::assertSame('my-class', $this->escaper->attr('my-class'));
    }

    #[Test]
    public function urlEscapesForHtmlOutput(): void
    {
        $result = $this->escaper->url('http://example.com/?a=1&b=2');

        // WordPress esc_url() uses &#038; for ampersand encoding
        self::assertStringNotContainsString('&b=2', $result);
        self::assertStringContainsString('b=2', $result);
    }

    #[Test]
    public function urlRejectsJavascriptScheme(): void
    {
        $result = $this->escaper->url('javascript:alert(1)');

        self::assertStringNotContainsString('javascript:', $result);
    }

    #[Test]
    public function jsEscapesQuotes(): void
    {
        $result = $this->escaper->js("He said \"hello\" and 'goodbye'");

        self::assertStringNotContainsString('"', $result);
    }

    #[Test]
    public function jsPreservesPlainText(): void
    {
        self::assertSame('Hello World', $this->escaper->js('Hello World'));
    }

    #[Test]
    public function escapeDefaultsToHtml(): void
    {
        $result = $this->escaper->escape('<script>alert("XSS")</script>');

        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('&lt;', $result);
    }

    #[Test]
    public function escapeWithHtmlStrategy(): void
    {
        $result = $this->escaper->escape('<b>bold</b>', 'html');

        self::assertStringContainsString('&lt;b&gt;', $result);
    }

    #[Test]
    public function escapeWithAttrStrategy(): void
    {
        $result = $this->escaper->escape('" onclick="alert(1)', 'attr');

        self::assertStringNotContainsString('"', $result);
    }

    #[Test]
    public function escapeWithUrlStrategy(): void
    {
        $result = $this->escaper->escape('javascript:alert(1)', 'url');

        self::assertStringNotContainsString('javascript:', $result);
    }

    #[Test]
    public function escapeWithJsStrategy(): void
    {
        $result = $this->escaper->escape("He said \"hello\"", 'js');

        self::assertStringNotContainsString('"', $result);
    }

    #[Test]
    public function escapeThrowsOnUnknownStrategy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown escaping strategy "css"');

        $this->escaper->escape('value', 'css');
    }
}
