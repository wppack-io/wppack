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

namespace WpPack\Component\Monitoring\Bridge\CloudWatch\Tests\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Monitoring\Bridge\CloudWatch\Discovery\S3Discovery;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;

final class S3DiscoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('STORAGE_DSN');
        putenv('WPPACK_STORAGE_UPLOADS_PATH');

        unset(
            $_ENV['STORAGE_DSN'],
            $_ENV['WPPACK_STORAGE_UPLOADS_PATH'],
            $_ENV['S3_BUCKET'],
            $_ENV['WPPACK_S3_BUCKET'],
            $_ENV['S3_REGION'],
            $_ENV['WPPACK_S3_REGION'],
            $_ENV['AWS_DEFAULT_REGION'],
        );

        delete_option(S3StorageConfiguration::OPTION_NAME);
    }

    // ──────────────────────────────────────────────
    // No configuration
    // ──────────────────────────────────────────────

    #[Test]
    public function returnsEmptyWhenNothingConfigured(): void
    {
        $discovery = new S3Discovery();

        self::assertSame([], $discovery->getProviders());
    }

    // ──────────────────────────────────────────────
    // STORAGE_DSN (via S3StorageConfiguration)
    // ──────────────────────────────────────────────

    #[Test]
    public function discoversFromStorageDsnEnv(): void
    {
        putenv('STORAGE_DSN=s3://AKIA:secret@my-bucket?region=ap-northeast-1');

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);

        $provider = $providers[0];
        self::assertSame('s3', $provider->id);
        self::assertSame('S3 Storage', $provider->label);
        self::assertSame('cloudwatch', $provider->bridge);
        self::assertSame('ap-northeast-1', $provider->settings->region);
        self::assertTrue($provider->locked);
    }

    #[Test]
    public function discoversFromStorageDsnSuperglobal(): void
    {
        $_ENV['STORAGE_DSN'] = 's3://my-bucket?region=us-west-2';

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('us-west-2', $providers[0]->settings->region);
    }

    #[Test]
    public function dimensionsContainBucketName(): void
    {
        putenv('STORAGE_DSN=s3://my-bucket?region=us-east-1');

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        self::assertNotEmpty($providers[0]->metrics);
        $firstMetric = $providers[0]->metrics[0];
        self::assertSame('my-bucket', $firstMetric->dimensions['BucketName']);
        self::assertSame('AllStorageTypes', $firstMetric->dimensions['StorageType']);
    }

    // ──────────────────────────────────────────────
    // wp_options (via S3StorageConfiguration)
    // ──────────────────────────────────────────────

    #[Test]
    public function discoversFromWpOption(): void
    {
        update_option(S3StorageConfiguration::OPTION_NAME, [
            'storages' => [
                's3://option-bucket' => [
                    'dsn' => 's3://AKIA:secret@option-bucket?region=eu-west-1',
                    'cdnUrl' => null,
                    'readonly' => false,
                ],
            ],
            'primary' => 's3://option-bucket',
            'uploadsPath' => 'wp-content/uploads',
        ]);

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('eu-west-1', $providers[0]->settings->region);

        $firstMetric = $providers[0]->metrics[0];
        self::assertSame('option-bucket', $firstMetric->dimensions['BucketName']);
    }

    #[Test]
    public function prefersStorageDsnOverWpOption(): void
    {
        putenv('STORAGE_DSN=s3://env-bucket?region=ap-northeast-1');

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

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);

        $firstMetric = $providers[0]->metrics[0];
        self::assertSame('env-bucket', $firstMetric->dimensions['BucketName']);
    }

    // ──────────────────────────────────────────────
    // Non-S3 scheme is ignored
    // ──────────────────────────────────────────────

    #[Test]
    public function ignoresNonS3StorageDsn(): void
    {
        putenv('STORAGE_DSN=local:///var/www');

        $discovery = new S3Discovery();

        self::assertSame([], $discovery->getProviders());
    }

    // ──────────────────────────────────────────────
    // Legacy constant fallback
    // ──────────────────────────────────────────────

    #[Test]
    public function discoversFromLegacyS3BucketEnv(): void
    {
        $_ENV['S3_BUCKET'] = 'legacy-bucket';
        $_ENV['S3_REGION'] = 'us-west-1';

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('us-west-1', $providers[0]->settings->region);

        $firstMetric = $providers[0]->metrics[0];
        self::assertSame('legacy-bucket', $firstMetric->dimensions['BucketName']);
    }

    #[Test]
    public function discoversFromLegacyWppackS3BucketEnv(): void
    {
        $_ENV['WPPACK_S3_BUCKET'] = 'wppack-bucket';
        $_ENV['WPPACK_S3_REGION'] = 'eu-central-1';

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('eu-central-1', $providers[0]->settings->region);

        $firstMetric = $providers[0]->metrics[0];
        self::assertSame('wppack-bucket', $firstMetric->dimensions['BucketName']);
    }

    #[Test]
    public function legacyFallbackDefaultsRegionToUsEast1(): void
    {
        $_ENV['S3_BUCKET'] = 'fallback-bucket';

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('us-east-1', $providers[0]->settings->region);
    }

    #[Test]
    public function legacyFallbackUsesAwsDefaultRegionEnv(): void
    {
        $_ENV['S3_BUCKET'] = 'fallback-bucket';
        $_ENV['AWS_DEFAULT_REGION'] = 'sa-east-1';

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('sa-east-1', $providers[0]->settings->region);
    }

    // ──────────────────────────────────────────────
    // Metrics
    // ──────────────────────────────────────────────

    #[Test]
    public function metricsIncludeBucketSizeAndObjectCount(): void
    {
        putenv('STORAGE_DSN=s3://my-bucket?region=us-east-1');

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();
        $metrics = $providers[0]->metrics;

        $metricNames = array_map(fn($m) => $m->metricName, $metrics);

        self::assertContains('BucketSizeBytes', $metricNames);
        self::assertContains('NumberOfObjects', $metricNames);
    }

    #[Test]
    public function metricsUseDailyPeriod(): void
    {
        putenv('STORAGE_DSN=s3://my-bucket?region=us-east-1');

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        foreach ($providers[0]->metrics as $metric) {
            self::assertSame(86400, $metric->periodSeconds, "Metric {$metric->id} should use 86400s period");
        }
    }

    #[Test]
    public function allMetricsAreLocked(): void
    {
        putenv('STORAGE_DSN=s3://my-bucket?region=us-east-1');

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        foreach ($providers[0]->metrics as $metric) {
            self::assertTrue($metric->locked, "Metric {$metric->id} should be locked");
        }
    }

    #[Test]
    public function allMetricsUseS3Namespace(): void
    {
        putenv('STORAGE_DSN=s3://my-bucket?region=us-east-1');

        $discovery = new S3Discovery();
        $providers = $discovery->getProviders();

        foreach ($providers[0]->metrics as $metric) {
            self::assertSame('AWS/S3', $metric->namespace, "Metric {$metric->id} should use AWS/S3 namespace");
        }
    }
}
