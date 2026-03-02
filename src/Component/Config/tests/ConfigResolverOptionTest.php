<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Config\ConfigResolver;
use WpPack\Component\Config\Exception\ConfigResolverException;
use WpPack\Component\Config\Tests\Fixtures\OptionConfig;
use WpPack\Component\Config\Tests\Fixtures\RequiredDotOptionConfig;
use WpPack\Component\Config\Tests\Fixtures\RequiredOptionConfig;

final class ConfigResolverOptionTest extends TestCase
{
    private ConfigResolver $resolver;

    protected function setUp(): void
    {
        if (!function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->resolver = new ConfigResolver();
    }

    #[Test]
    public function resolvesSimpleOption(): void
    {
        update_option('blogname', 'Test Site');

        $config = $this->resolver->resolve(OptionConfig::class);

        self::assertSame('Test Site', $config->siteName);
    }

    #[Test]
    public function resolvesDotNotationOption(): void
    {
        update_option('my_plugin_settings', [
            'api_endpoint' => 'https://custom.api.com',
        ]);

        $config = $this->resolver->resolve(OptionConfig::class);

        self::assertSame('https://custom.api.com', $config->apiEndpoint);
    }

    #[Test]
    public function resolvesArrayOption(): void
    {
        $settings = ['key1' => 'value1', 'key2' => 'value2'];
        update_option('my_plugin_settings', $settings);

        $config = $this->resolver->resolve(OptionConfig::class);

        self::assertSame($settings, $config->allSettings);
    }

    #[Test]
    public function usesDefaultWhenOptionNotSet(): void
    {
        delete_option('blogname');
        delete_option('my_plugin_settings');

        $config = $this->resolver->resolve(OptionConfig::class);

        self::assertSame('', $config->siteName);
        self::assertSame('https://api.example.com', $config->apiEndpoint);
        self::assertSame([], $config->allSettings);
    }

    #[Test]
    public function usesDefaultWhenDotNotationKeyMissing(): void
    {
        update_option('my_plugin_settings', ['other_key' => 'value']);

        $config = $this->resolver->resolve(OptionConfig::class);

        self::assertSame('https://api.example.com', $config->apiEndpoint);
    }

    #[Test]
    public function throwsExceptionForMissingRequiredOption(): void
    {
        delete_option('required_option');

        $this->expectException(ConfigResolverException::class);
        $this->expectExceptionMessage('required_option');

        $this->resolver->resolve(RequiredOptionConfig::class);
    }

    #[Test]
    public function throwsExceptionForMissingRequiredDotNotationKey(): void
    {
        update_option('my_settings', ['nested' => ['other' => 'value']]);

        $this->expectException(ConfigResolverException::class);
        $this->expectExceptionMessage('my_settings.nested.key');

        $this->resolver->resolve(RequiredDotOptionConfig::class);
    }

    #[Test]
    public function throwsExceptionWhenIntermediatePathIsNotArray(): void
    {
        update_option('my_settings', ['nested' => 'not-an-array']);

        $this->expectException(ConfigResolverException::class);
        $this->expectExceptionMessage('my_settings.nested.key');

        $this->resolver->resolve(RequiredDotOptionConfig::class);
    }

    #[Test]
    public function resolvesDeepNestedDotNotation(): void
    {
        update_option('my_settings', [
            'nested' => ['key' => 'deep-value'],
        ]);

        $config = $this->resolver->resolve(RequiredDotOptionConfig::class);

        self::assertSame('deep-value', $config->value);
    }
}
