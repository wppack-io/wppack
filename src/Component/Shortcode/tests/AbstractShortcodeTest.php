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

namespace WPPack\Component\Shortcode\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\OptionsResolver\OptionsResolver;
use WPPack\Component\Shortcode\AbstractShortcode;
use WPPack\Component\Shortcode\Attribute\AsShortcode;

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

    #[Test]
    public function resolveAttributesReturnsRawAttsWithoutConfigureAttributesOverride(): void
    {
        $shortcode = new ConcreteTestShortcode();

        $result = $shortcode->resolveAttributes(['url' => 'https://example.com']);

        self::assertSame(['url' => 'https://example.com'], $result);
    }

    #[Test]
    public function resolveAttributesMergesDefaultsWhenConfigureAttributesOverridden(): void
    {
        $shortcode = new ConfiguredTestShortcode();

        $result = $shortcode->resolveAttributes(['url' => 'https://example.com']);

        self::assertSame('https://example.com', $result['url']);
        self::assertSame('primary', $result['style']);
    }

    #[Test]
    public function resolveAttributesCachesResolver(): void
    {
        $shortcode = new ConfiguredTestShortcode();

        $result1 = $shortcode->resolveAttributes(['url' => 'https://a.com']);
        $result2 = $shortcode->resolveAttributes(['url' => 'https://b.com']);

        self::assertSame('https://a.com', $result1['url']);
        self::assertSame('https://b.com', $result2['url']);
    }

    #[Test]
    public function resolveAttributesCastsViaAllowedTypes(): void
    {
        $shortcode = new TypedTestShortcode();

        $result = $shortcode->resolveAttributes(['count' => '10', 'enabled' => 'yes']);

        self::assertSame(10, $result['count']);
        self::assertTrue($result['enabled']);
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

#[AsShortcode(name: 'configured_test', description: 'Configured test shortcode')]
class ConfiguredTestShortcode extends AbstractShortcode
{
    protected function configureAttributes(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'url' => '#',
            'style' => 'primary',
        ]);
    }

    public function render(array $atts, string $content): string
    {
        return sprintf(
            '<a href="%s" class="btn-%s">%s</a>',
            $atts['url'],
            $atts['style'],
            $content,
        );
    }
}

#[AsShortcode(name: 'typed_test', description: 'Typed test shortcode')]
class TypedTestShortcode extends AbstractShortcode
{
    protected function configureAttributes(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'count' => 5,
            'enabled' => false,
        ]);
        $resolver->setAllowedTypes('count', 'int');
        $resolver->setAllowedTypes('enabled', 'bool');
    }

    public function render(array $atts, string $content): string
    {
        return sprintf('%d|%s', $atts['count'], $atts['enabled'] ? 'true' : 'false');
    }
}
