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

namespace WpPack\Plugin\S3StoragePlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsController;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;

#[CoversClass(S3StorageSettingsController::class)]
final class S3StorageSettingsControllerTest extends TestCase
{
    private S3StorageSettingsController $controller;

    protected function setUp(): void
    {
        $this->controller = new S3StorageSettingsController();
    }

    protected function tearDown(): void
    {
        delete_option(S3StorageConfiguration::OPTION_NAME);
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

        self::assertArrayHasKey('definitions', $data);
        self::assertArrayHasKey('storages', $data);
        self::assertArrayHasKey('primary', $data);
        self::assertArrayHasKey('source', $data);
        self::assertArrayHasKey('awsRegion', $data);
    }

    #[Test]
    public function getSettingsReturnsS3Definition(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('s3', $data['definitions']);
        self::assertSame('Amazon S3', $data['definitions']['s3']['label']);
    }

    #[Test]
    public function getSettingsReturnsDefaultSource(): void
    {
        delete_option(S3StorageConfiguration::OPTION_NAME);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('default', $data['source']);
    }

    #[Test]
    public function getSettingsReturnsOptionSourceWhenConfigured(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'test-bucket', 'region' => 'us-east-1'],
                    'prefix' => 'uploads',
                ],
            ],
            'primary' => 'media',
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('option', $data['source']);
        self::assertArrayHasKey('media', $data['storages']);
        self::assertSame('s3', $data['storages']['media']['provider']);
    }

    #[Test]
    public function getSettingsMasksPasswordFields(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'test', 'region' => 'us-east-1', 'secretKey' => 'my-secret'],
                    'prefix' => 'uploads',
                ],
            ],
            'primary' => 'media',
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame(S3StorageConfiguration::MASKED_VALUE, $data['storages']['media']['fields']['secretKey']);
    }

    #[Test]
    public function saveSettingsPersistsToOption(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'new-bucket', 'region' => 'ap-northeast-1'],
                    'prefix' => 'uploads',
                    'cdnUrl' => 'https://cdn.example.com',
                ],
            ],
            'primary' => 'media',
        ]));

        $response = $this->controller->saveSettings($request);

        self::assertSame(200, $response->statusCode);

        $saved = get_option(S3StorageConfiguration::OPTION_NAME);
        self::assertSame('new-bucket', $saved['storages']['media']['fields']['bucket']);
        self::assertSame('media', $saved['primary']);
    }

    #[Test]
    public function saveSettingsRestoresMaskedPasswords(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'test', 'region' => 'us-east-1', 'secretKey' => 'original-secret'],
                    'prefix' => 'uploads',
                ],
            ],
            'primary' => 'media',
        ]);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'test', 'region' => 'us-east-1', 'secretKey' => S3StorageConfiguration::MASKED_VALUE],
                    'prefix' => 'uploads',
                ],
            ],
            'primary' => 'media',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(S3StorageConfiguration::OPTION_NAME);
        self::assertSame('original-secret', $saved['storages']['media']['fields']['secretKey']);
    }

    #[Test]
    public function saveSettingsSkipsReadonlyStorages(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'test', 'region' => 'us-east-1'],
                    'prefix' => 'uploads',
                    'readonly' => true,
                ],
            ],
            'primary' => 'media',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(S3StorageConfiguration::OPTION_NAME);
        self::assertEmpty($saved['storages']);
    }

    #[Test]
    public function getSettingsReturnsMultipleProviderDefinitions(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        // S3, Azure, GCS, and Local should all be present
        self::assertArrayHasKey('s3', $data['definitions']);
        self::assertArrayHasKey('azure', $data['definitions']);
        self::assertArrayHasKey('gcs', $data['definitions']);
        self::assertArrayHasKey('local', $data['definitions']);
    }

    #[Test]
    public function saveSettingsHandlesMultipleStorages(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'media-bucket', 'region' => 'us-east-1'],
                    'prefix' => 'uploads',
                    'cdnUrl' => '',
                ],
                'backup' => [
                    'provider' => 'gcs',
                    'fields' => ['bucket' => 'backup-bucket'],
                    'prefix' => 'archives',
                    'cdnUrl' => '',
                ],
            ],
            'primary' => 'media',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(S3StorageConfiguration::OPTION_NAME);
        self::assertCount(2, $saved['storages']);
        self::assertSame('s3', $saved['storages']['media']['provider']);
        self::assertSame('gcs', $saved['storages']['backup']['provider']);
        self::assertSame('media', $saved['primary']);
    }
}
