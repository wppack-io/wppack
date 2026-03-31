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

namespace WpPack\Plugin\S3StoragePlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;

final class S3StorageConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('S3_BUCKET');
        putenv('S3_REGION');
        putenv('S3_PREFIX');
        putenv('CDN_URL');
        putenv('AWS_REGION');

        unset($_ENV['S3_BUCKET'], $_ENV['S3_REGION'], $_ENV['S3_PREFIX'], $_ENV['CDN_URL'], $_ENV['AWS_REGION']);
    }

    #[Test]
    public function fromEnvironmentWithAllEnvVars(): void
    {
        putenv('S3_BUCKET=my-bucket');
        putenv('S3_REGION=ap-northeast-1');
        putenv('S3_PREFIX=media');
        putenv('CDN_URL=https://cdn.example.com');

        $config = S3StorageConfiguration::fromEnvironment();

        self::assertSame('my-bucket', $config->bucket);
        self::assertSame('ap-northeast-1', $config->region);
        self::assertSame('media', $config->prefix);
        self::assertSame('https://cdn.example.com', $config->cdnUrl);
    }

    #[Test]
    public function fromEnvironmentWithMinimalEnvVars(): void
    {
        putenv('S3_BUCKET=my-bucket');

        $config = S3StorageConfiguration::fromEnvironment();

        self::assertSame('my-bucket', $config->bucket);
        self::assertSame('us-east-1', $config->region);
        self::assertSame('uploads', $config->prefix);
        self::assertNull($config->cdnUrl);
    }

    #[Test]
    public function fromEnvironmentFallsBackToAwsRegion(): void
    {
        putenv('S3_BUCKET=my-bucket');
        putenv('AWS_REGION=eu-west-1');

        $config = S3StorageConfiguration::fromEnvironment();

        self::assertSame('eu-west-1', $config->region);
    }

    #[Test]
    public function fromEnvironmentS3RegionTakesPrecedenceOverAwsRegion(): void
    {
        putenv('S3_BUCKET=my-bucket');
        putenv('S3_REGION=ap-southeast-1');
        putenv('AWS_REGION=eu-west-1');

        $config = S3StorageConfiguration::fromEnvironment();

        self::assertSame('ap-southeast-1', $config->region);
    }

    #[Test]
    public function fromEnvironmentThrowsWhenBucketMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable "S3_BUCKET" is not set.');

        S3StorageConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentReadsFromEnvSuperglobal(): void
    {
        $_ENV['S3_BUCKET'] = 'env-bucket';
        $_ENV['S3_REGION'] = 'us-west-2';

        $config = S3StorageConfiguration::fromEnvironment();

        self::assertSame('env-bucket', $config->bucket);
        self::assertSame('us-west-2', $config->region);
    }

    #[Test]
    public function toStorageConfiguration(): void
    {
        $config = new S3StorageConfiguration(
            bucket: 'test-bucket',
            region: 'ap-northeast-1',
            prefix: 'wp-uploads',
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
            bucket: 'test-bucket',
            region: 'us-east-1',
        );

        $storageConfig = $config->toStorageConfiguration();

        self::assertSame('s3', $storageConfig->protocol);
        self::assertSame('test-bucket', $storageConfig->bucket);
        self::assertSame('uploads', $storageConfig->prefix);
        self::assertNull($storageConfig->cdnUrl);
    }

    #[Test]
    public function hasConfigurationReturnsFalseByDefault(): void
    {
        delete_option(S3StorageConfiguration::OPTION_NAME);

        self::assertFalse(S3StorageConfiguration::hasConfiguration());
    }

    #[Test]
    public function hasConfigurationReturnsTrueWithWpOption(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'my-bucket', 'region' => 'us-east-1'],
                    'prefix' => 'uploads',
                ],
            ],
            'primary' => 'media',
        ]);

        self::assertTrue(S3StorageConfiguration::hasConfiguration());

        delete_option(S3StorageConfiguration::OPTION_NAME);
    }

    #[Test]
    public function hasConfigurationReturnsTrueWithEnvVar(): void
    {
        putenv('S3_BUCKET=env-bucket');

        self::assertTrue(S3StorageConfiguration::hasConfiguration());
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsWpOption(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'option-bucket', 'region' => 'eu-west-1'],
                    'prefix' => 'media-files',
                    'cdnUrl' => 'https://cdn.example.com',
                ],
            ],
            'primary' => 'media',
        ]);

        $config = S3StorageConfiguration::fromEnvironmentOrOptions();

        self::assertSame('option-bucket', $config->bucket);
        self::assertSame('eu-west-1', $config->region);
        self::assertSame('media-files', $config->prefix);
        self::assertSame('https://cdn.example.com', $config->cdnUrl);

        delete_option(S3StorageConfiguration::OPTION_NAME);
    }

    #[Test]
    public function hasConfigurationReturnsFalseWhenOptionExistsButEmpty(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [],
            'primary' => 'media',
        ]);

        self::assertFalse(S3StorageConfiguration::hasConfiguration());

        delete_option(S3StorageConfiguration::OPTION_NAME);
    }

    #[Test]
    public function fromEnvironmentOrOptionsThrowsWhenNothingConfigured(): void
    {
        delete_option(S3StorageConfiguration::OPTION_NAME);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('S3 storage is not configured.');

        S3StorageConfiguration::fromEnvironmentOrOptions();
    }

    #[Test]
    public function fromEnvironmentOrOptionsPrefersEnvOverOption(): void
    {
        putenv('S3_BUCKET=env-bucket');

        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                'media' => [
                    'provider' => 's3',
                    'fields' => ['bucket' => 'option-bucket'],
                    'prefix' => 'uploads',
                ],
            ],
            'primary' => 'media',
        ]);

        $config = S3StorageConfiguration::fromEnvironmentOrOptions();

        self::assertSame('env-bucket', $config->bucket);

        delete_option(S3StorageConfiguration::OPTION_NAME);
    }

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
