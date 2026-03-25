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
use WpPack\Component\OEmbed\OEmbedProviderInterface;
use WpPack\Component\OEmbed\OEmbedProviderRegistry;

final class OEmbedProviderRegistryTest extends TestCase
{
    private OEmbedProviderRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new OEmbedProviderRegistry();
    }

    #[Test]
    public function registerCollectsDefinitionsFromProviders(): void
    {
        $provider = new class implements OEmbedProviderInterface {
            public function getProviders(): array
            {
                return [
                    new OEmbedProviderDefinition('https://example.com/*', 'https://example.com/oembed'),
                    new OEmbedProviderDefinition('#https?://custom\.site/.*#i', 'https://custom.site/oembed', regex: true),
                ];
            }
        };

        $this->registry->addProvider($provider);
        $this->registry->register();

        self::assertTrue($this->registry->hasProvider('https://example.com/*'));
        self::assertTrue($this->registry->hasProvider('#https?://custom\.site/.*#i'));
    }

    #[Test]
    public function registerMergesMultipleProviders(): void
    {
        $provider1 = new class implements OEmbedProviderInterface {
            public function getProviders(): array
            {
                return [
                    new OEmbedProviderDefinition('https://example.com/*', 'https://example.com/oembed'),
                ];
            }
        };

        $provider2 = new class implements OEmbedProviderInterface {
            public function getProviders(): array
            {
                return [
                    new OEmbedProviderDefinition('#https?://custom\.site/.*#i', 'https://custom.site/oembed', regex: true),
                ];
            }
        };

        $this->registry->addProvider($provider1);
        $this->registry->addProvider($provider2);
        $this->registry->register();

        self::assertCount(2, $this->registry->all());
        self::assertTrue($this->registry->hasProvider('https://example.com/*'));
        self::assertTrue($this->registry->hasProvider('#https?://custom\.site/.*#i'));
    }

    #[Test]
    public function addDefinitionAddsProviderDirectly(): void
    {
        $this->registry->addDefinition('https://example.com/*', 'https://example.com/oembed');

        self::assertTrue($this->registry->hasProvider('https://example.com/*'));

        $providers = $this->registry->all();
        self::assertCount(1, $providers);
        self::assertSame('https://example.com/*', $providers[0]->format);
        self::assertSame('https://example.com/oembed', $providers[0]->endpoint);
        self::assertFalse($providers[0]->regex);
    }

    #[Test]
    public function unregisterRemovesDefinition(): void
    {
        $this->registry->addDefinition('https://example.com/*', 'https://example.com/oembed');
        $this->registry->unregister('https://example.com/*');

        self::assertFalse($this->registry->hasProvider('https://example.com/*'));
        self::assertSame([], $this->registry->all());
    }

    #[Test]
    public function hasProviderReturnsFalseForUnknownFormat(): void
    {
        self::assertFalse($this->registry->hasProvider('https://nonexistent.com/*'));
    }

    #[Test]
    public function allReturnsEmptyArrayByDefault(): void
    {
        self::assertSame([], $this->registry->all());
    }

    #[Test]
    public function laterProviderOverridesEarlierProviderFormat(): void
    {
        $provider1 = new class implements OEmbedProviderInterface {
            public function getProviders(): array
            {
                return [
                    new OEmbedProviderDefinition('https://example.com/*', 'https://example.com/oembed/v1'),
                ];
            }
        };

        $provider2 = new class implements OEmbedProviderInterface {
            public function getProviders(): array
            {
                return [
                    new OEmbedProviderDefinition('https://example.com/*', 'https://example.com/oembed/v2'),
                ];
            }
        };

        $this->registry->addProvider($provider1);
        $this->registry->addProvider($provider2);
        $this->registry->register();

        $providers = $this->registry->all();
        self::assertCount(1, $providers);
        self::assertSame('https://example.com/oembed/v2', $providers[0]->endpoint);
    }

    #[Test]
    public function regexFlagIsPropagated(): void
    {
        $this->registry->addDefinition('#https?://regex\.site/.*#i', 'https://regex.site/oembed', regex: true);

        $providers = $this->registry->all();
        self::assertCount(1, $providers);
        self::assertTrue($providers[0]->regex);
    }
}
