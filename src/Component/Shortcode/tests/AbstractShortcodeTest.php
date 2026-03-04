<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Shortcode\AbstractShortcode;
use WpPack\Component\Shortcode\Attribute\AsShortcode;

final class AbstractShortcodeTest extends TestCase
{
    #[Test]
    public function resolvesNameFromAttribute(): void
    {
        $shortcode = new ConcreteTestShortcode();

        self::assertSame('test_shortcode', $shortcode->name);
    }

    #[Test]
    public function resolvesDescriptionFromAttribute(): void
    {
        $shortcode = new ConcreteTestShortcode();

        self::assertSame('A test shortcode', $shortcode->description);
    }

    #[Test]
    public function descriptionDefaultsToEmptyString(): void
    {
        $shortcode = new MinimalTestShortcode();

        self::assertSame('', $shortcode->description);
    }

    #[Test]
    public function renderReceivesAttsAndContent(): void
    {
        $shortcode = new ConcreteTestShortcode();

        $result = $shortcode->render(['url' => 'https://example.com'], 'Click me');

        self::assertSame('<a href="https://example.com">Click me</a>', $result);
    }

    #[Test]
    public function renderReceivesEmptyAttsAndContent(): void
    {
        $shortcode = new ConcreteTestShortcode();

        $result = $shortcode->render([], '');

        self::assertSame('<a href=""></a>', $result);
    }

    #[Test]
    public function throwsLogicExceptionWithoutAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have the #[AsShortcode] attribute');

        new NoAttributeTestShortcode();
    }
}

#[AsShortcode(name: 'test_shortcode', description: 'A test shortcode')]
class ConcreteTestShortcode extends AbstractShortcode
{
    public function render(array $atts, string $content): string
    {
        $url = $atts['url'] ?? '';

        return sprintf('<a href="%s">%s</a>', $url, $content);
    }
}

#[AsShortcode(name: 'minimal')]
class MinimalTestShortcode extends AbstractShortcode
{
    public function render(array $atts, string $content): string
    {
        return $content;
    }
}

class NoAttributeTestShortcode extends AbstractShortcode
{
    public function render(array $atts, string $content): string
    {
        return '';
    }
}
