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

namespace WpPack\Plugin\RedisCachePlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsController;
use WpPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;

#[CoversClass(RedisCacheSettingsController::class)]
final class RedisCacheSettingsControllerTest extends TestCase
{
    private RedisCacheSettingsController $controller;

    protected function setUp(): void
    {
        delete_option(RedisCacheConfiguration::OPTION_NAME);
        $this->controller = new RedisCacheSettingsController();
    }

    protected function tearDown(): void
    {
        delete_option(RedisCacheConfiguration::OPTION_NAME);
    }

    #[Test]
    public function extendsAbstractRestController(): void
    {
        self::assertInstanceOf(AbstractRestController::class, $this->controller);
    }

    #[Test]
    public function getSettingsReturnsJsonResponse(): void
    {
        $response = $this->controller->getSettings();

        self::assertSame(200, $response->statusCode);

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('dsn', $data);
        self::assertArrayHasKey('source', $data);
        self::assertArrayHasKey('readonly', $data);
        self::assertArrayHasKey('definitions', $data);
        self::assertArrayHasKey('globalOptions', $data);
        self::assertArrayHasKey('extensions', $data);
    }

    #[Test]
    public function getSettingsReturnsDefaultSource(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('default', $data['source']);
        self::assertFalse($data['readonly']);
    }

    #[Test]
    public function getSettingsReturnsOptionSourceWithMaskedPassword(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, [
            'dsn' => 'redis://:secret@127.0.0.1:6379',
            'provider' => 'redis',
            'fields' => ['host' => '127.0.0.1', 'port' => '6379', 'password' => 'secret'],
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('option', $data['source']);
        self::assertStringContainsString(RedisCacheConfiguration::MASKED_VALUE, $data['dsn']);
        self::assertStringNotContainsString('secret', $data['dsn']);
    }

    #[Test]
    public function getSettingsReturnsAdapterDefinitions(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        // Should have redis definitions and DSN
        self::assertArrayHasKey('redis', $data['definitions']);
        self::assertArrayHasKey('dsn', $data['definitions']);
        self::assertSame('DSN (Direct Input)', $data['definitions']['dsn']['label']);
    }

    #[Test]
    public function getSettingsReturnsGlobalOptionsWithDefaults(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('wp:', $data['globalOptions']['prefix']);
        self::assertFalse($data['globalOptions']['hashAlloptions']);
        self::assertFalse($data['globalOptions']['asyncFlush']);
        self::assertSame('none', $data['globalOptions']['compression']);
        self::assertSame('none', $data['globalOptions']['serializer']);
    }

    #[Test]
    public function getSettingsReturnsSavedGlobalOptions(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, [
            'prefix' => 'mysite:',
            'compression' => 'zstd',
            'serializer' => 'igbinary',
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('mysite:', $data['globalOptions']['prefix']);
        self::assertSame('zstd', $data['globalOptions']['compression']);
        self::assertSame('igbinary', $data['globalOptions']['serializer']);
    }

    #[Test]
    public function getSettingsReturnsExtensions(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('redis', $data['extensions']);
        self::assertArrayHasKey('relay', $data['extensions']);
        self::assertArrayHasKey('igbinary', $data['extensions']);
        self::assertArrayHasKey('zstd', $data['extensions']);
        self::assertArrayHasKey('lz4', $data['extensions']);
        self::assertArrayHasKey('lzf', $data['extensions']);
        self::assertIsBool($data['extensions']['redis']);
    }

    #[Test]
    public function getSettingsReturnsDefinitionCapabilities(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        // Redis should have capabilities
        self::assertArrayHasKey('capabilities', $data['definitions']['redis']);
        self::assertIsArray($data['definitions']['redis']['capabilities']);
    }

    #[Test]
    public function saveSettingsPersistsDsnProvider(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'dsn',
            'fields' => ['dsn' => 'redis://127.0.0.1:6379'],
        ]));

        $response = $this->controller->saveSettings($request);

        self::assertSame(200, $response->statusCode);

        $saved = get_option(RedisCacheConfiguration::OPTION_NAME);
        self::assertSame('redis://127.0.0.1:6379', $saved['dsn']);
        self::assertSame('dsn', $saved['provider']);
    }

    #[Test]
    public function saveSettingsSkipsMaskedDsn(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, [
            'dsn' => 'redis://original',
            'provider' => 'dsn',
            'fields' => ['dsn' => 'redis://original'],
        ]);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'dsn',
            'fields' => ['dsn' => RedisCacheConfiguration::MASKED_VALUE],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(RedisCacheConfiguration::OPTION_NAME);
        self::assertSame('redis://original', $saved['dsn']);
    }

    #[Test]
    public function saveSettingsBuildsDsnFromProviderFields(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'redis',
            'fields' => ['host' => '10.0.0.1', 'port' => '6380', 'password' => 'mypass'],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(RedisCacheConfiguration::OPTION_NAME);
        self::assertStringContainsString('redis://', $saved['dsn']);
        self::assertStringContainsString('10.0.0.1', $saved['dsn']);
    }

    #[Test]
    public function saveSettingsPersistsGlobalOptions(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => '',
            'fields' => [],
            'globalOptions' => [
                'prefix' => 'test:',
                'compression' => 'zstd',
                'serializer' => 'igbinary',
            ],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(RedisCacheConfiguration::OPTION_NAME);
        self::assertSame('test:', $saved['prefix']);
        self::assertSame('zstd', $saved['compression']);
        self::assertSame('igbinary', $saved['serializer']);
    }

    #[Test]
    public function testConnectionReturnsResult(): void
    {
        $response = $this->controller->testConnection();

        self::assertSame(200, $response->statusCode);

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('success', $data);
    }

    #[Test]
    public function saveSettingsRestoresMaskedPasswords(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, [
            'dsn' => 'redis://:original-pass@127.0.0.1:6379',
            'provider' => 'redis',
            'fields' => ['host' => '127.0.0.1', 'port' => '6379', 'password' => 'original-pass'],
        ]);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'redis',
            'fields' => ['host' => '127.0.0.1', 'port' => '6379', 'password' => RedisCacheConfiguration::MASKED_VALUE],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(RedisCacheConfiguration::OPTION_NAME);
        self::assertSame('original-pass', $saved['fields']['password']);
    }

    #[Test]
    public function saveSettingsHandlesUnknownProvider(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'unknown-provider',
            'fields' => ['host' => 'localhost'],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(RedisCacheConfiguration::OPTION_NAME);
        // DSN should not be set because findDefinition returned null
        self::assertArrayNotHasKey('dsn', $saved);
        self::assertSame('unknown-provider', $saved['provider']);
    }

    #[Test]
    public function saveSettingsHandlesEmptyProvider(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => '',
            'fields' => [],
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(RedisCacheConfiguration::OPTION_NAME);
        self::assertSame('', $saved['provider']);
    }
}
