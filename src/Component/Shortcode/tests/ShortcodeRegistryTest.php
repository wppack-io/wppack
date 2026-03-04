<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\OptionsResolver\OptionsResolver;
use WpPack\Component\Shortcode\AbstractShortcode;
use WpPack\Component\Shortcode\Attribute\AsShortcode;
use WpPack\Component\Shortcode\ShortcodeRegistry;

final class ShortcodeRegistryTest extends TestCase
{
    private ShortcodeRegistry $registry;

    protected function setUp(): void
    {
        if (!function_exists('add_shortcode')) {
            self::markTestSkipped('WordPress shortcode functions are not available.');
        }

        $this->registry = new ShortcodeRegistry();
    }

    #[Test]
    public function registerCallsAddShortcode(): void
    {
        $shortcode = new RegistryTestShortcode();

        $this->registry->register($shortcode);

        self::assertTrue(shortcode_exists('registry_test'));
    }

    #[Test]
    public function unregisterCallsRemoveShortcode(): void
    {
        $shortcode = new RegistryTestShortcode();
        $this->registry->register($shortcode);

        $this->registry->unregister('registry_test');

        self::assertFalse(shortcode_exists('registry_test'));
    }

    #[Test]
    public function registeredShortcodeInvokesRender(): void
    {
        $shortcode = new RegistryTestShortcode();
        $this->registry->register($shortcode);

        $result = do_shortcode('[registry_test name="world"]Hello[/registry_test]');

        self::assertSame('Hello, world!', $result);
    }

    #[Test]
    public function registeredShortcodeWithConfigureAttributesAppliesDefaults(): void
    {
        $shortcode = new ConfiguredRegistryTestShortcode();
        $this->registry->register($shortcode);

        $result = do_shortcode('[configured_registry_test]content[/configured_registry_test]');

        self::assertSame('content|primary', $result);
    }

    #[Test]
    public function registeredShortcodeWithConfigureAttributesOverridesDefaults(): void
    {
        $shortcode = new ConfiguredRegistryTestShortcode();
        $this->registry->register($shortcode);

        $result = do_shortcode('[configured_registry_test style="danger"]content[/configured_registry_test]');

        self::assertSame('content|danger', $result);
    }
}

#[AsShortcode(name: 'registry_test', description: 'Registry test shortcode')]
class RegistryTestShortcode extends AbstractShortcode
{
    public function render(array $atts, string $content): string
    {
        $name = $atts['name'] ?? 'unknown';

        return sprintf('%s, %s!', $content, $name);
    }
}

#[AsShortcode(name: 'configured_registry_test', description: 'Configured registry test')]
class ConfiguredRegistryTestShortcode extends AbstractShortcode
{
    protected function configureAttributes(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'style' => 'primary',
        ]);
    }

    public function render(array $atts, string $content): string
    {
        return sprintf('%s|%s', $content, $atts['style']);
    }
}
