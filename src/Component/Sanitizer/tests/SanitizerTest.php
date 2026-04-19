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

namespace WPPack\Component\Sanitizer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Sanitizer\Sanitizer;

final class SanitizerTest extends TestCase
{
    private Sanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new Sanitizer();
    }

    #[Test]
    public function textRemovesHtmlTags(): void
    {
        $result = $this->sanitizer->text('<script>alert("XSS")</script>Hello');

        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('Hello', $result);
    }

    #[Test]
    public function textTrimsWhitespace(): void
    {
        self::assertSame('Hello', $this->sanitizer->text('  Hello  '));
    }

    #[Test]
    public function textareaPreservesNewlines(): void
    {
        $result = $this->sanitizer->textarea("line1\nline2");

        self::assertStringContainsString("\n", $result);
    }

    #[Test]
    public function textareaRemovesHtmlTags(): void
    {
        $result = $this->sanitizer->textarea('<b>bold</b>');

        self::assertStringNotContainsString('<b>', $result);
    }

    #[Test]
    public function ksesPostKeepsSafeTags(): void
    {
        $result = $this->sanitizer->ksesPost('<p>Hello</p><script>alert("XSS")</script>');

        self::assertStringContainsString('<p>Hello</p>', $result);
        self::assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function ksesWithStripContext(): void
    {
        $result = $this->sanitizer->kses('<p>Hello</p>', 'strip');

        self::assertSame('Hello', $result);
    }

    #[Test]
    public function ksesWithAllowedTagsArray(): void
    {
        $result = $this->sanitizer->kses(
            '<p class="intro">Hello</p><div>World</div>',
            ['p' => ['class' => true]],
        );

        self::assertStringContainsString('<p class="intro">Hello</p>', $result);
        self::assertStringNotContainsString('<div>', $result);
    }

    #[Test]
    public function stripTagsRemovesAllTags(): void
    {
        $result = $this->sanitizer->stripTags('<p>Hello</p> <b>World</b>');

        self::assertStringNotContainsString('<p>', $result);
        self::assertStringNotContainsString('<b>', $result);
        self::assertStringContainsString('Hello', $result);
    }

    #[Test]
    public function emailStripsInvalidCharacters(): void
    {
        $result = $this->sanitizer->email('john@example.com');

        self::assertSame('john@example.com', $result);
    }

    #[Test]
    public function emailReturnsEmptyForInvalidEmail(): void
    {
        $result = $this->sanitizer->email('not-an-email');

        self::assertSame('', $result);
    }

    #[Test]
    public function urlRemovesInvalidCharacters(): void
    {
        $result = $this->sanitizer->url('http://example.com/path');

        self::assertSame('http://example.com/path', $result);
    }

    #[Test]
    public function urlRejectsJavascriptScheme(): void
    {
        $result = $this->sanitizer->url('javascript:alert(1)');

        self::assertStringNotContainsString('javascript:', $result);
    }

    #[Test]
    public function filenameReplacesSpacesWithDashes(): void
    {
        $result = $this->sanitizer->filename('my file.pdf');

        self::assertSame('my-file.pdf', $result);
    }

    #[Test]
    public function filenameRemovesSpecialCharacters(): void
    {
        $result = $this->sanitizer->filename('my file (1).pdf');

        self::assertSame('my-file-1.pdf', $result);
    }

    #[Test]
    public function keyRestrictsToAllowedCharacters(): void
    {
        $result = $this->sanitizer->key('My_Option-KEY!');

        self::assertSame('my_option-key', $result);
    }

    #[Test]
    public function titleConvertsToSlug(): void
    {
        $result = $this->sanitizer->title('Hello World!');

        self::assertSame('hello-world', $result);
    }

    #[Test]
    public function slugConvertsToSlugWithDashes(): void
    {
        $result = $this->sanitizer->slug('Hello World!');

        self::assertSame('hello-world', $result);
    }

    #[Test]
    public function htmlClassRestrictsToAllowedCharacters(): void
    {
        $result = $this->sanitizer->htmlClass('my-class_name');

        self::assertSame('my-class_name', $result);
    }

    #[Test]
    public function htmlClassStripsInvalidCharacters(): void
    {
        $result = $this->sanitizer->htmlClass('my class!@#');

        self::assertStringNotContainsString(' ', $result);
        self::assertStringNotContainsString('!', $result);
    }

    #[Test]
    public function userStripsInvalidCharacters(): void
    {
        $result = $this->sanitizer->user('john_doe');

        self::assertSame('john_doe', $result);
    }

    #[Test]
    public function mimeTypeValidatesFormat(): void
    {
        self::assertSame('image/png', $this->sanitizer->mimeType('image/png'));
    }

    #[Test]
    public function hexColorValidatesFormat(): void
    {
        self::assertSame('#ff0000', $this->sanitizer->hexColor('#ff0000'));
    }

    #[Test]
    public function hexColorReturnsEmptyForInvalid(): void
    {
        self::assertSame('', $this->sanitizer->hexColor('not-a-color'));
    }

    #[Test]
    public function hexColorAcceptsShortFormat(): void
    {
        self::assertSame('#f00', $this->sanitizer->hexColor('#f00'));
    }
}
