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

        putenv('STORAGE_DSN');
        putenv('WPPACK_STORAGE_UPLOADS_PATH');
        unset($_ENV['STORAGE_DSN'], $_ENV['WPPACK_STORAGE_UPLOADS_PATH']);
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
        self::assertArrayHasKey('uploadsPath', $data);
        self::assertArrayHasKey('source', $data);
    }

    #[Test]
    public function getSettingsReturnsS3Definition(): void
    {
        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertArrayHasKey('s3', $data['definitions']);
        self::assertSame('Amazon S3', $data['definitions']['s3']['label']);
        self::assertSame('s3', $data['definitions']['s3']['scheme']);
        self::assertSame([
            ['name' => 'bucket', 'label' => 'Bucket'],
            ['name' => 'region', 'label' => 'Region'],
            ['name' => 'accessKey', 'label' => 'Access Key', 'sensitive' => true],
            ['name' => 'secretKey', 'label' => 'Secret Key', 'sensitive' => true],
        ], $data['definitions']['s3']['fields']);
    }

    #[Test]
    public function getSettingsReturnsDefaultSource(): void
    {
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
                's3://test-bucket' => [
                    'dsn' => 's3://AKIA:secret@test-bucket?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://test-bucket',
            'uploadsPath' => 'wp-content/uploads',
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('option', $data['source']);
        self::assertArrayHasKey('s3://test-bucket', $data['storages']);
    }

    #[Test]
    public function getSettingsReturnsConstantSourceWithEnvDsn(): void
    {
        putenv('STORAGE_DSN=s3://AKIA:secret@env-bucket?region=ap-northeast-1');

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('constant', $data['source']);
        self::assertArrayHasKey('s3://env-bucket', $data['storages']);
        self::assertTrue($data['storages']['s3://env-bucket']['readonly']);
    }

    #[Test]
    public function getSettingsMasksDsnCredentials(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://test-bucket' => [
                    'dsn' => 's3://AKIA:secret@test-bucket?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://test-bucket',
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame(
            's3://********:********@test-bucket?region=us-east-1',
            $data['storages']['s3://test-bucket']['dsn'],
        );
    }

    #[Test]
    public function getSettingsReturnsCdnUrl(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://test-bucket' => [
                    'dsn' => 's3://test-bucket?region=us-east-1',
                    'cdnUrl' => 'https://cdn.example.com',
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://test-bucket',
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('https://cdn.example.com', $data['storages']['s3://test-bucket']['cdnUrl']);
    }

    #[Test]
    public function getSettingsReturnsUploadsPath(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://test-bucket' => [
                    'dsn' => 's3://test-bucket?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://test-bucket',
            'uploadsPath' => 'custom/uploads',
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('custom/uploads', $data['uploadsPath']);
    }

    #[Test]
    public function saveSettingsPersistsToOption(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'storages' => [
                's3://new-bucket' => [
                    'dsn' => 's3://AKIA:secret@new-bucket?region=ap-northeast-1',
                    'cdnUrl' => 'https://cdn.example.com',
                ],
            ],
            'primary' => 's3://new-bucket',
            'uploadsPath' => 'wp-content/uploads',
        ]));

        $response = $this->controller->saveSettings($request);

        self::assertSame(200, $response->statusCode);

        $saved = get_option(S3StorageConfiguration::OPTION_NAME);
        self::assertSame('s3://new-bucket', $saved['primary']);
        self::assertSame('wp-content/uploads', $saved['uploadsPath']);
        self::assertSame('s3://AKIA:secret@new-bucket?region=ap-northeast-1', $saved['storages']['s3://new-bucket']['dsn']);
    }

    #[Test]
    public function saveSettingsRestoresMaskedDsn(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://test-bucket' => [
                    'dsn' => 's3://AKIA:original-secret@test-bucket?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://test-bucket',
        ]);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'storages' => [
                's3://test-bucket' => [
                    'dsn' => 's3://********:********@test-bucket?region=us-east-1',
                    'cdnUrl' => '',
                ],
            ],
            'primary' => 's3://test-bucket',
            'uploadsPath' => 'wp-content/uploads',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(S3StorageConfiguration::OPTION_NAME);
        self::assertSame(
            's3://AKIA:original-secret@test-bucket?region=us-east-1',
            $saved['storages']['s3://test-bucket']['dsn'],
        );
    }

    #[Test]
    public function saveSettingsSkipsReadonlyStorages(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'storages' => [
                's3://readonly-bucket' => [
                    'dsn' => 's3://readonly-bucket?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => true,
                ],
            ],
            'primary' => 's3://readonly-bucket',
            'uploadsPath' => 'wp-content/uploads',
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
    public function saveSettingsDeletesRemovedStorages(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://bucket1' => [
                    'dsn' => 's3://bucket1?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
                's3://bucket2' => [
                    'dsn' => 's3://bucket2?region=eu-west-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://bucket1',
        ]);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'storages' => [
                's3://bucket1' => [
                    'dsn' => 's3://bucket1?region=us-east-1',
                    'cdnUrl' => '',
                ],
            ],
            'primary' => 's3://bucket1',
            'uploadsPath' => 'wp-content/uploads',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(S3StorageConfiguration::OPTION_NAME);
        self::assertCount(1, $saved['storages']);
        self::assertArrayHasKey('s3://bucket1', $saved['storages']);
        self::assertArrayNotHasKey('s3://bucket2', $saved['storages']);
    }

    #[Test]
    public function saveSettingsHandlesMultipleStorages(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'storages' => [
                's3://media-bucket' => [
                    'dsn' => 's3://media-bucket?region=us-east-1',
                    'cdnUrl' => '',
                ],
                's3://backup-bucket' => [
                    'dsn' => 's3://backup-bucket?region=eu-west-1',
                    'cdnUrl' => '',
                ],
            ],
            'primary' => 's3://media-bucket',
            'uploadsPath' => 'wp-content/uploads',
        ]));

        $this->controller->saveSettings($request);

        $saved = get_option(S3StorageConfiguration::OPTION_NAME);
        self::assertCount(2, $saved['storages']);
        self::assertArrayHasKey('s3://media-bucket', $saved['storages']);
        self::assertArrayHasKey('s3://backup-bucket', $saved['storages']);
        self::assertSame('s3://media-bucket', $saved['primary']);
    }

    #[Test]
    public function getSettingsReturnsStorageUri(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://test-bucket' => [
                    'dsn' => 's3://test-bucket?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                    'uri' => 's3://test-bucket',
                ],
            ],
            'primary' => 's3://test-bucket',
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('s3://test-bucket', $data['storages']['s3://test-bucket']['uri']);
    }

    #[Test]
    public function getSettingsConstantSourceReadsUploadsPathFromEnv(): void
    {
        putenv('STORAGE_DSN=s3://env-bucket?region=us-east-1');
        putenv('WPPACK_STORAGE_UPLOADS_PATH=custom/path');

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('custom/path', $data['uploadsPath']);
    }

    #[Test]
    public function getSettingsDsnWithoutCredentialsNotMasked(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://test-bucket' => [
                    'dsn' => 's3://test-bucket?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://test-bucket',
        ]);

        $response = $this->controller->getSettings();

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true);

        self::assertSame('s3://test-bucket?region=us-east-1', $data['storages']['s3://test-bucket']['dsn']);
    }
}
