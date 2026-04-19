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

namespace WPPack\Plugin\S3StoragePlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Media\Storage\StorageConfiguration;
use WPPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;

final class S3StorageConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('STORAGE_DSN');
        putenv('WPPACK_STORAGE_UPLOADS_PATH');

        unset($_ENV['STORAGE_DSN'], $_ENV['WPPACK_STORAGE_UPLOADS_PATH']);

        delete_option(S3StorageConfiguration::OPTION_NAME);
    }

    // ──────────────────────────────────────────────
    // parseDsn
    // ──────────────────────────────────────────────

    #[Test]
    public function parseDsnWithFullCredentials(): void
    {
        $dsn = 's3://AKIAIOSFODNN7EXAMPLE:wJalrXUtnFEMI%2FK7MDENG%2FbPxRfiCYEXAMPLEKEY@my-bucket?region=ap-northeast-1';

        $result = S3StorageConfiguration::parseDsn($dsn);

        self::assertSame('s3', $result['scheme']);
        self::assertSame('my-bucket', $result['bucket']);
        self::assertSame('ap-northeast-1', $result['region']);
        self::assertSame('AKIAIOSFODNN7EXAMPLE', $result['accessKeyId']);
        self::assertSame('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', $result['secretAccessKey']);
    }

    #[Test]
    public function parseDsnWithoutCredentials(): void
    {
        $dsn = 's3://my-bucket?region=us-west-2';

        $result = S3StorageConfiguration::parseDsn($dsn);

        self::assertSame('s3', $result['scheme']);
        self::assertSame('my-bucket', $result['bucket']);
        self::assertSame('us-west-2', $result['region']);
        self::assertSame('', $result['accessKeyId']);
        self::assertSame('', $result['secretAccessKey']);
    }

    #[Test]
    public function parseDsnDefaultsRegionToUsEast1(): void
    {
        $dsn = 's3://my-bucket';

        $result = S3StorageConfiguration::parseDsn($dsn);

        self::assertSame('us-east-1', $result['region']);
    }

    #[Test]
    public function parseDsnThrowsForInvalidDsn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DSN format');

        S3StorageConfiguration::parseDsn('not-a-valid-dsn');
    }

    #[Test]
    public function parseDsnUrlDecodesCredentials(): void
    {
        $dsn = 's3://key%2Fwith%2Fslashes:secret%40with%40at@bucket?region=eu-west-1';

        $result = S3StorageConfiguration::parseDsn($dsn);

        self::assertSame('key/with/slashes', $result['accessKeyId']);
        self::assertSame('secret@with@at', $result['secretAccessKey']);
    }

    // ──────────────────────────────────────────────
    // maskDsn
    // ──────────────────────────────────────────────

    #[Test]
    public function maskDsnMasksCredentials(): void
    {
        $dsn = 's3://AKIAEXAMPLE:secretkey@my-bucket?region=ap-northeast-1';

        $masked = S3StorageConfiguration::maskDsn($dsn);

        self::assertSame('s3://********:********@my-bucket?region=ap-northeast-1', $masked);
    }

    #[Test]
    public function maskDsnWithoutCredentials(): void
    {
        $dsn = 's3://my-bucket?region=us-west-2';

        $masked = S3StorageConfiguration::maskDsn($dsn);

        self::assertSame('s3://my-bucket?region=us-west-2', $masked);
    }

    #[Test]
    public function maskDsnWithUserOnlyNoPassword(): void
    {
        $dsn = 's3://AKIAEXAMPLE@my-bucket?region=eu-west-1';

        $masked = S3StorageConfiguration::maskDsn($dsn);

        self::assertSame('s3://********@my-bucket?region=eu-west-1', $masked);
    }

    #[Test]
    public function maskDsnReturnsOriginalForInvalidDsn(): void
    {
        $dsn = 'invalid';

        self::assertSame('invalid', S3StorageConfiguration::maskDsn($dsn));
    }

    // ──────────────────────────────────────────────
    // buildUri
    // ──────────────────────────────────────────────

    #[Test]
    public function buildUriReturnsBucketUri(): void
    {
        self::assertSame('s3://my-bucket', S3StorageConfiguration::buildUri('my-bucket'));
    }

    // ──────────────────────────────────────────────
    // fromEnvironmentOrOptions
    // ──────────────────────────────────────────────

    #[Test]
    public function fromEnvironmentOrOptionsWithDsnConstant(): void
    {
        putenv('STORAGE_DSN=s3://AKIA:secret@my-bucket?region=ap-northeast-1');

        $config = S3StorageConfiguration::fromEnvironmentOrOptions();

        self::assertSame('my-bucket', $config->bucket);
        self::assertSame('ap-northeast-1', $config->region);
        self::assertSame('AKIA', $config->accessKeyId);
        self::assertSame('secret', $config->secretAccessKey);
        self::assertSame('wp-content/uploads', $config->uploadsPath);
        self::assertNull($config->cdnUrl);
    }

    #[Test]
    public function fromEnvironmentOrOptionsWithDsnEnvSuperglobal(): void
    {
        $_ENV['STORAGE_DSN'] = 's3://my-bucket?region=us-west-2';

        $config = S3StorageConfiguration::fromEnvironmentOrOptions();

        self::assertSame('my-bucket', $config->bucket);
        self::assertSame('us-west-2', $config->region);
        self::assertNull($config->accessKeyId);
        self::assertNull($config->secretAccessKey);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsUploadsPathFromEnv(): void
    {
        putenv('STORAGE_DSN=s3://my-bucket?region=us-east-1');
        putenv('WPPACK_STORAGE_UPLOADS_PATH=custom/uploads');

        $config = S3StorageConfiguration::fromEnvironmentOrOptions();

        self::assertSame('custom/uploads', $config->uploadsPath);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsWpOption(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://option-bucket' => [
                    'dsn' => 's3://AKIA:secret@option-bucket?region=eu-west-1',
                    'cdnUrl' => 'https://cdn.example.com',
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://option-bucket',
            'uploadsPath' => 'media-files',
        ]);

        $config = S3StorageConfiguration::fromEnvironmentOrOptions();

        self::assertSame('option-bucket', $config->bucket);
        self::assertSame('eu-west-1', $config->region);
        self::assertSame('media-files', $config->uploadsPath);
        self::assertSame('https://cdn.example.com', $config->cdnUrl);
        self::assertSame('AKIA', $config->accessKeyId);
        self::assertSame('secret', $config->secretAccessKey);
    }

    #[Test]
    public function fromEnvironmentOrOptionsPrefersEnvOverOption(): void
    {
        putenv('STORAGE_DSN=s3://env-bucket?region=us-east-1');

        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://option-bucket' => [
                    'dsn' => 's3://option-bucket?region=eu-west-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://option-bucket',
        ]);

        $config = S3StorageConfiguration::fromEnvironmentOrOptions();

        self::assertSame('env-bucket', $config->bucket);
    }

    #[Test]
    public function fromEnvironmentOrOptionsThrowsWhenNothingConfigured(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('S3 storage is not configured.');

        S3StorageConfiguration::fromEnvironmentOrOptions();
    }

    #[Test]
    public function fromEnvironmentOrOptionsThrowsForEmptyDsn(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://bucket' => [
                    'dsn' => '',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://bucket',
        ]);

        $this->expectException(\RuntimeException::class);

        S3StorageConfiguration::fromEnvironmentOrOptions();
    }

    #[Test]
    public function fromEnvironmentOrOptionsNullCdnUrlWhenEmpty(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://test' => [
                    'dsn' => 's3://test?region=us-east-1',
                    'cdnUrl' => '',
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://test',
        ]);

        $config = S3StorageConfiguration::fromEnvironmentOrOptions();

        self::assertNull($config->cdnUrl);
    }

    #[Test]
    public function fromEnvironmentOrOptionsDefaultsUploadsPath(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://test' => [
                    'dsn' => 's3://test?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://test',
        ]);

        $config = S3StorageConfiguration::fromEnvironmentOrOptions();

        self::assertSame('wp-content/uploads', $config->uploadsPath);
    }

    // ──────────────────────────────────────────────
    // hasConfiguration
    // ──────────────────────────────────────────────

    #[Test]
    public function hasConfigurationReturnsFalseByDefault(): void
    {
        self::assertFalse(S3StorageConfiguration::hasConfiguration());
    }

    #[Test]
    public function hasConfigurationReturnsTrueWithEnvVar(): void
    {
        putenv('STORAGE_DSN=s3://my-bucket?region=us-east-1');

        self::assertTrue(S3StorageConfiguration::hasConfiguration());
    }

    #[Test]
    public function hasConfigurationReturnsTrueWithEnvSuperglobal(): void
    {
        $_ENV['STORAGE_DSN'] = 's3://my-bucket?region=us-east-1';

        self::assertTrue(S3StorageConfiguration::hasConfiguration());
    }

    #[Test]
    public function hasConfigurationReturnsTrueWithWpOption(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://my-bucket' => [
                    'dsn' => 's3://my-bucket?region=us-east-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://my-bucket',
        ]);

        self::assertTrue(S3StorageConfiguration::hasConfiguration());
    }

    #[Test]
    public function hasConfigurationReturnsFalseWhenOptionExistsButEmpty(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [],
            'primary' => '',
        ]);

        self::assertFalse(S3StorageConfiguration::hasConfiguration());
    }

    #[Test]
    public function hasConfigurationReturnsFalseWhenStorageDsnIsEmpty(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://bucket' => [
                    'dsn' => '',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://bucket',
        ]);

        self::assertFalse(S3StorageConfiguration::hasConfiguration());
    }

    // ──────────────────────────────────────────────
    // toStorageConfiguration
    // ──────────────────────────────────────────────

    #[Test]
    public function toStorageConfiguration(): void
    {
        $config = new S3StorageConfiguration(
            dsn: 's3://test-bucket?region=ap-northeast-1',
            bucket: 'test-bucket',
            region: 'ap-northeast-1',
            uploadsPath: 'wp-uploads',
            cdnUrl: 'https://cdn.example.com',
        );

        $storageConfig = $config->toStorageConfiguration();

        self::assertInstanceOf(StorageConfiguration::class, $storageConfig);
        self::assertSame('s3', $storageConfig->protocol);
        self::assertSame('test-bucket', $storageConfig->bucket);
        self::assertSame('wp-uploads', $storageConfig->prefix);
        self::assertSame('https://cdn.example.com', $storageConfig->cdnUrl);
    }

    #[Test]
    public function toStorageConfigurationWithDefaults(): void
    {
        $config = new S3StorageConfiguration(
            dsn: 's3://test-bucket?region=us-east-1',
            bucket: 'test-bucket',
            region: 'us-east-1',
        );

        $storageConfig = $config->toStorageConfiguration();

        self::assertSame('s3', $storageConfig->protocol);
        self::assertSame('test-bucket', $storageConfig->bucket);
        self::assertSame('wp-content/uploads', $storageConfig->prefix);
        self::assertNull($storageConfig->cdnUrl);
    }

    // ──────────────────────────────────────────────
    // Constants
    // ──────────────────────────────────────────────

    #[Test]
    public function optionNameConstant(): void
    {
        self::assertSame('wppack_storage', S3StorageConfiguration::OPTION_NAME);
    }

    #[Test]
    public function maskedValueConstant(): void
    {
        self::assertSame('********', S3StorageConfiguration::MASKED_VALUE);
    }
}
