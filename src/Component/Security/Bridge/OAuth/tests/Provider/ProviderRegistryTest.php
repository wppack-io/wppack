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

namespace WPPack\Component\Security\Bridge\OAuth\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\OAuth\Provider\GoogleProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderDefinition;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderRegistry;

#[CoversClass(ProviderRegistry::class)]
final class ProviderRegistryTest extends TestCase
{
    #[Test]
    public function definitionsReturnsAllProviders(): void
    {
        $definitions = ProviderRegistry::definitions();
        self::assertNotEmpty($definitions);
        self::assertGreaterThanOrEqual(18, \count($definitions));
        foreach ($definitions as $type => $def) {
            self::assertInstanceOf(ProviderDefinition::class, $def);
            self::assertSame($type, $def->type);
        }
    }

    #[Test]
    public function definitionReturnsCorrectProvider(): void
    {
        $def = ProviderRegistry::definition('google');
        self::assertInstanceOf(ProviderDefinition::class, $def);
        self::assertSame('google', $def->type);
        self::assertSame('Google', $def->label);
        self::assertTrue($def->oidc);
    }

    #[Test]
    public function definitionReturnsNullForUnknown(): void
    {
        self::assertNull(ProviderRegistry::definition('nonexistent'));
    }

    #[Test]
    public function providerClassReturnsCorrectClass(): void
    {
        self::assertSame(GoogleProvider::class, ProviderRegistry::providerClass('google'));
    }

    #[Test]
    public function providerClassReturnsNullForUnknown(): void
    {
        self::assertNull(ProviderRegistry::providerClass('nonexistent'));
    }

    #[Test]
    public function typesReturnsAllTypeStrings(): void
    {
        $types = ProviderRegistry::types();
        self::assertContains('google', $types);
        self::assertContains('github', $types);
        self::assertContains('entra-id', $types);
        self::assertContains('oidc', $types);
        self::assertContains('amazon', $types);
        self::assertContains('microsoft', $types);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function allProviderTypes(): \Generator
    {
        foreach (ProviderRegistry::types() as $type) {
            yield $type => [$type];
        }
    }

    #[Test]
    #[DataProvider('allProviderTypes')]
    public function eachProviderHasValidDefinition(string $type): void
    {
        $def = ProviderRegistry::definition($type);
        self::assertNotNull($def);
        self::assertSame($type, $def->type);
        self::assertNotEmpty($def->label);
        self::assertNotEmpty($def->dropdownLabel);
        self::assertIsBool($def->oidc);
        self::assertIsArray($def->requiredFields);
        self::assertIsArray($def->defaultScopes);
    }
}
