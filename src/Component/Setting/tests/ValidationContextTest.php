<?php

declare(strict_types=1);

namespace WpPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Setting\ValidationContext;

final class ValidationContextTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('add_settings_error')) {
            self::markTestSkipped('WordPress functions are not available.');
        }
    }

    #[Test]
    public function errorAddsSettingsError(): void
    {
        global $wp_settings_errors;
        $wp_settings_errors = [];

        $context = new ValidationContext('my_group', 'my_option');
        $context->error('api_key_required', 'API Key is required.');

        self::assertCount(1, $wp_settings_errors);
        self::assertSame('my_group', $wp_settings_errors[0]['setting']);
        self::assertSame('api_key_required', $wp_settings_errors[0]['code']);
        self::assertSame('API Key is required.', $wp_settings_errors[0]['message']);
        self::assertSame('error', $wp_settings_errors[0]['type']);
    }

    #[Test]
    public function warningAddsSettingsWarning(): void
    {
        global $wp_settings_errors;
        $wp_settings_errors = [];

        $context = new ValidationContext('my_group', 'my_option');
        $context->warning('cache_ttl_min', 'Cache TTL must be at least 60 seconds.');

        self::assertCount(1, $wp_settings_errors);
        self::assertSame('warning', $wp_settings_errors[0]['type']);
        self::assertSame('cache_ttl_min', $wp_settings_errors[0]['code']);
    }

    #[Test]
    public function infoAddsSettingsInfo(): void
    {
        global $wp_settings_errors;
        $wp_settings_errors = [];

        $context = new ValidationContext('my_group', 'my_option');
        $context->info('settings_saved', 'Settings have been saved.');

        self::assertCount(1, $wp_settings_errors);
        self::assertSame('info', $wp_settings_errors[0]['type']);
        self::assertSame('settings_saved', $wp_settings_errors[0]['code']);
    }

    #[Test]
    public function oldValueReturnsStoredValue(): void
    {
        update_option('my_option', ['api_key' => 'old-key', 'debug' => true]);

        $context = new ValidationContext('my_group', 'my_option');

        self::assertSame('old-key', $context->oldValue('api_key'));
        self::assertTrue($context->oldValue('debug'));

        delete_option('my_option');
    }

    #[Test]
    public function oldValueReturnsDefaultWhenKeyMissing(): void
    {
        update_option('my_option', ['api_key' => 'key']);

        $context = new ValidationContext('my_group', 'my_option');

        self::assertNull($context->oldValue('nonexistent'));
        self::assertSame('fallback', $context->oldValue('nonexistent', 'fallback'));

        delete_option('my_option');
    }

    #[Test]
    public function oldValueHandlesNonArrayOption(): void
    {
        update_option('my_option', 'not-an-array');

        $context = new ValidationContext('my_group', 'my_option');

        self::assertSame('default', $context->oldValue('any_key', 'default'));

        delete_option('my_option');
    }

    #[Test]
    public function oldValueHandlesMissingOption(): void
    {
        delete_option('nonexistent_option');

        $context = new ValidationContext('my_group', 'nonexistent_option');

        self::assertNull($context->oldValue('any_key'));
        self::assertSame('default', $context->oldValue('any_key', 'default'));
    }

    #[Test]
    public function multipleErrorsAndWarningsAccumulate(): void
    {
        global $wp_settings_errors;
        $wp_settings_errors = [];

        $context = new ValidationContext('my_group', 'my_option');
        $context->error('err_one', 'First error.');
        $context->error('err_two', 'Second error.');
        $context->warning('warn_one', 'First warning.');

        self::assertCount(3, $wp_settings_errors);
        self::assertSame('error', $wp_settings_errors[0]['type']);
        self::assertSame('err_one', $wp_settings_errors[0]['code']);
        self::assertSame('error', $wp_settings_errors[1]['type']);
        self::assertSame('err_two', $wp_settings_errors[1]['code']);
        self::assertSame('warning', $wp_settings_errors[2]['type']);
        self::assertSame('warn_one', $wp_settings_errors[2]['code']);
    }
}
