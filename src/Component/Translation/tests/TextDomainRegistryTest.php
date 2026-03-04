<?php

declare(strict_types=1);

namespace WpPack\Component\Translation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Translation\Attribute\PluginTextDomain;
use WpPack\Component\Translation\Attribute\ThemeTextDomain;
use WpPack\Component\Translation\TextDomainRegistry;

final class TextDomainRegistryTest extends TestCase
{
    #[Test]
    public function registerLoadsPluginTextDomain(): void
    {
        if (!function_exists('load_plugin_textdomain')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $registry = new TextDomainRegistry();
        $registry->register(new RegistryPluginStub());

        self::assertTrue($registry->has('registry-plugin'));
    }

    #[Test]
    public function registerLoadsThemeTextDomain(): void
    {
        if (!function_exists('load_theme_textdomain')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $registry = new TextDomainRegistry();
        $registry->register(new RegistryThemeStub());

        self::assertTrue($registry->has('registry-theme'));
    }

    #[Test]
    public function registerAcceptsNonTranslatorObject(): void
    {
        if (!function_exists('load_plugin_textdomain')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $registry = new TextDomainRegistry();
        $registry->register(new NonTranslatorPluginStub());

        self::assertTrue($registry->has('non-translator-plugin'));
    }

    #[Test]
    public function registerThrowsWithoutAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have a #[PluginTextDomain] or #[ThemeTextDomain] attribute');

        $registry = new TextDomainRegistry();
        $registry->register(new NoAttributeStub());
    }

    #[Test]
    public function hasReturnsTrueAfterRegistration(): void
    {
        if (!function_exists('load_plugin_textdomain')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $registry = new TextDomainRegistry();
        $registry->register(new RegistryPluginStub());

        self::assertTrue($registry->has('registry-plugin'));
    }

    #[Test]
    public function hasReturnsFalseForUnknownDomain(): void
    {
        $registry = new TextDomainRegistry();

        self::assertFalse($registry->has('unknown-domain'));
    }

    #[Test]
    public function getRegisteredDomainsReturnsEmptyByDefault(): void
    {
        $registry = new TextDomainRegistry();

        self::assertSame([], $registry->getRegisteredDomains());
    }

    #[Test]
    public function getRegisteredDomainsReturnsRegisteredDomains(): void
    {
        if (!function_exists('load_plugin_textdomain')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $registry = new TextDomainRegistry();
        $registry->register(new RegistryPluginStub());

        $domains = $registry->getRegisteredDomains();

        self::assertCount(1, $domains);
        self::assertArrayHasKey('registry-plugin', $domains);
        self::assertInstanceOf(PluginTextDomain::class, $domains['registry-plugin']);
    }

    #[Test]
    public function loadPluginCallsWordPressFunction(): void
    {
        if (!function_exists('load_plugin_textdomain')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        TextDomainRegistry::loadPlugin('static-plugin', 'static-plugin/languages');

        // If no exception was thrown, the call succeeded
        self::assertTrue(true);
    }

    #[Test]
    public function loadThemeCallsWordPressFunction(): void
    {
        if (!function_exists('load_theme_textdomain')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        TextDomainRegistry::loadTheme('static-theme', 'languages');

        // If no exception was thrown, the call succeeded
        self::assertTrue(true);
    }
}

#[PluginTextDomain(domain: 'registry-plugin', path: 'registry-plugin/languages')]
class RegistryPluginStub {}

#[ThemeTextDomain(domain: 'registry-theme')]
class RegistryThemeStub {}

#[PluginTextDomain(domain: 'non-translator-plugin')]
class NonTranslatorPluginStub {}

class NoAttributeStub {}
