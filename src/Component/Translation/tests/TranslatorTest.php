<?php

declare(strict_types=1);

namespace WpPack\Component\Translation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Translation\Attribute\PluginTextDomain;
use WpPack\Component\Translation\Attribute\ThemeTextDomain;
use WpPack\Component\Translation\Translator;

final class TranslatorTest extends TestCase
{
    #[Test]
    public function resolveDomainFromPluginTextDomainAttribute(): void
    {
        $translator = new PluginTranslatorStub();

        self::assertSame('my-plugin', $translator->domain);
    }

    #[Test]
    public function resolveDomainFromThemeTextDomainAttribute(): void
    {
        $translator = new ThemeTranslatorStub();

        self::assertSame('my-theme', $translator->domain);
    }

    #[Test]
    public function acceptsDomainViaConstructor(): void
    {
        $translator = new Translator('custom-domain');

        self::assertSame('custom-domain', $translator->domain);
    }

    #[Test]
    public function constructorDomainTakesPrecedenceOverAttribute(): void
    {
        $translator = new PluginTranslatorStub('override-domain');

        self::assertSame('override-domain', $translator->domain);
    }

    #[Test]
    public function throwsLogicExceptionWithoutDomainOrAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must either pass a domain to the constructor or have a #[PluginTextDomain] or #[ThemeTextDomain] attribute');

        new NoDomainTranslatorStub();
    }

    #[Test]
    public function translateCallsWordPressFunction(): void
    {
        if (!function_exists('__')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $translator = new Translator('test-domain');

        self::assertSame('Hello', $translator->translate('Hello'));
    }

    #[Test]
    public function echoCallsWordPressFunction(): void
    {
        if (!function_exists('_e')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $translator = new Translator('test-domain');

        ob_start();
        $translator->echo('Hello');
        $output = ob_get_clean();

        self::assertSame('Hello', $output);
    }

    #[Test]
    public function pluralCallsWordPressFunction(): void
    {
        if (!function_exists('_n')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $translator = new Translator('test-domain');

        self::assertSame('%d item', $translator->plural('%d item', '%d items', 1));
        self::assertSame('%d items', $translator->plural('%d item', '%d items', 5));
    }

    #[Test]
    public function translateWithContextCallsWordPressFunction(): void
    {
        if (!function_exists('_x')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $translator = new Translator('test-domain');

        self::assertSame('Post', $translator->translateWithContext('Post', 'verb'));
    }

    #[Test]
    public function pluralWithContextCallsWordPressFunction(): void
    {
        if (!function_exists('_nx')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $translator = new Translator('test-domain');

        self::assertSame('%d item', $translator->pluralWithContext('%d item', '%d items', 1, 'cart'));
        self::assertSame('%d items', $translator->pluralWithContext('%d item', '%d items', 5, 'cart'));
    }

    #[Test]
    public function escHtmlCallsWordPressFunction(): void
    {
        if (!function_exists('esc_html__')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $translator = new Translator('test-domain');

        self::assertSame('Safe text', $translator->escHtml('Safe text'));
    }

    #[Test]
    public function escAttrCallsWordPressFunction(): void
    {
        if (!function_exists('esc_attr__')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $translator = new Translator('test-domain');

        self::assertSame('Title text', $translator->escAttr('Title text'));
    }
}

#[PluginTextDomain(domain: 'my-plugin', path: 'my-plugin/languages')]
class PluginTranslatorStub extends Translator {}

#[ThemeTextDomain(domain: 'my-theme')]
class ThemeTranslatorStub extends Translator {}

class NoDomainTranslatorStub extends Translator {}
