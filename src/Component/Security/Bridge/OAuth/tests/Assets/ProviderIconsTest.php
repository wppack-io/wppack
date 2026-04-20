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

namespace WPPack\Component\Security\Bridge\OAuth\Tests\Assets;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\OAuth\Assets\ProviderIcons;

#[CoversClass(ProviderIcons::class)]
final class ProviderIconsTest extends TestCase
{
    #[Test]
    public function svgReturnsSvgMarkupForKnownProviders(): void
    {
        $svg = ProviderIcons::svg('google');

        self::assertIsString($svg);
        self::assertStringStartsWith('<svg', $svg);
        self::assertStringContainsString('</svg>', $svg);
    }

    #[Test]
    public function svgReturnsNullForUnknownProvider(): void
    {
        self::assertNull(ProviderIcons::svg('nonexistent-provider'));
    }

    #[Test]
    public function hasReflectsRegistryMembership(): void
    {
        self::assertTrue(ProviderIcons::has('amazon'));
        self::assertTrue(ProviderIcons::has('apple'));
        self::assertFalse(ProviderIcons::has(''));
        self::assertFalse(ProviderIcons::has('not-a-real-provider'));
    }

    #[Test]
    public function providersReturnsNonEmptyListOfRegisteredKeys(): void
    {
        $providers = ProviderIcons::providers();

        self::assertNotEmpty($providers);
        self::assertContains('google', $providers);
        self::assertContains('microsoft', $providers);
        self::assertContains('yahoo-japan', $providers);
        self::assertContains('line', $providers);
    }

    #[Test]
    public function providersListMatchesHasLookup(): void
    {
        foreach (ProviderIcons::providers() as $provider) {
            self::assertTrue(ProviderIcons::has($provider), "has() disagrees with providers() for {$provider}");
            self::assertIsString(ProviderIcons::svg($provider));
        }
    }

    #[Test]
    public function stylesReturnsVariantArrayForKnownProvider(): void
    {
        $styles = ProviderIcons::styles('amazon');

        self::assertIsArray($styles);
        self::assertArrayHasKey('light', $styles);
        self::assertArrayHasKey('dark', $styles);

        foreach ($styles as $variant) {
            self::assertArrayHasKey('label', $variant);
            self::assertArrayHasKey('bg', $variant);
            self::assertArrayHasKey('text', $variant);
            self::assertArrayHasKey('border', $variant);
            self::assertArrayHasKey('icon', $variant);
        }
    }

    #[Test]
    public function stylesReturnsNullForUnknownProvider(): void
    {
        self::assertNull(ProviderIcons::styles('unknown'));
    }

    #[Test]
    public function styleReturnsNamedVariant(): void
    {
        $variant = ProviderIcons::style('amazon', 'dark');

        self::assertIsArray($variant);
        self::assertSame('#232F3E', $variant['bg']);
        self::assertSame('#FFFFFF', $variant['text']);
    }

    #[Test]
    public function styleReturnsNullForUnknownVariant(): void
    {
        self::assertNull(ProviderIcons::style('amazon', 'polka-dot'));
        self::assertNull(ProviderIcons::style('unknown', 'light'));
    }

    #[Test]
    public function defaultStyleReturnsFirstDeclaredVariant(): void
    {
        // amazon declares 'light' before 'dark' in the STYLES map.
        self::assertSame('light', ProviderIcons::defaultStyle('amazon'));
        self::assertSame('white', ProviderIcons::defaultStyle('apple'));
    }

    #[Test]
    public function defaultStyleReturnsNullForUnknownProvider(): void
    {
        self::assertNull(ProviderIcons::defaultStyle('unknown'));
    }

    #[Test]
    public function brandColorReturnsFirstVariantFields(): void
    {
        $color = ProviderIcons::brandColor('amazon');

        self::assertIsArray($color);
        self::assertArrayHasKey('bg', $color);
        self::assertArrayHasKey('text', $color);
        self::assertArrayHasKey('border', $color);
        self::assertArrayHasKey('icon', $color);
        self::assertSame('#FFFFFF', $color['bg'], 'first variant of amazon is "light"');
    }

    #[Test]
    public function brandColorReturnsNullForUnknownProvider(): void
    {
        self::assertNull(ProviderIcons::brandColor('unknown'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function knownProviderProvider(): iterable
    {
        yield 'amazon' => ['amazon'];
        yield 'apple' => ['apple'];
        yield 'google' => ['google'];
        yield 'microsoft' => ['microsoft'];
        yield 'discord' => ['discord'];
        yield 'slack' => ['slack'];
        yield 'line' => ['line'];
        yield 'facebook' => ['facebook'];
        yield 'yahoo' => ['yahoo'];
        yield 'yahoo-japan' => ['yahoo-japan'];
        yield 'github' => ['github'];
        yield 'auth0' => ['auth0'];
        yield 'okta' => ['okta'];
        yield 'onelogin' => ['onelogin'];
        yield 'cognito' => ['cognito'];
        yield 'keycloak' => ['keycloak'];
        yield 'entra-id' => ['entra-id'];
        yield 'd-account' => ['d-account'];
        yield 'openid' => ['openid'];
        yield 'x' => ['x'];
    }

    #[Test]
    #[DataProvider('knownProviderProvider')]
    public function knownProvidersHaveValidSvg(string $provider): void
    {
        $svg = ProviderIcons::svg($provider);

        self::assertIsString($svg);
        self::assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg);
    }
}
