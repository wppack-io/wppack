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

namespace WpPack\Component\OEmbed\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\OEmbed\OEmbedProviderDefinition;

final class OEmbedProviderDefinitionTest extends TestCase
{
    #[Test]
    public function wildcardFormatResolvesProperties(): void
    {
        $definition = new OEmbedProviderDefinition(
            'https://example.com/*',
            'https://example.com/oembed',
        );

        self::assertSame('https://example.com/*', $definition->format);
        self::assertSame('https://example.com/oembed', $definition->endpoint);
        self::assertFalse($definition->regex);
    }

    #[Test]
    public function regexFormatResolvesProperties(): void
    {
        $definition = new OEmbedProviderDefinition(
            '#https?://custom\.site/.*#i',
            'https://custom.site/oembed',
            regex: true,
        );

        self::assertSame('#https?://custom\.site/.*#i', $definition->format);
        self::assertSame('https://custom.site/oembed', $definition->endpoint);
        self::assertTrue($definition->regex);
    }

    #[Test]
    public function regexDefaultsToFalse(): void
    {
        $definition = new OEmbedProviderDefinition(
            'https://example.com/*',
            'https://example.com/oembed',
        );

        self::assertFalse($definition->regex);
    }
}
