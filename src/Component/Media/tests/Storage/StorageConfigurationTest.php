<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Media\Storage\StorageConfiguration;

#[CoversClass(StorageConfiguration::class)]
final class StorageConfigurationTest extends TestCase
{
    #[Test]
    public function constructWithRequiredParameters(): void
    {
        $config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
        );

        self::assertSame('s3', $config->protocol);
        self::assertSame('my-bucket', $config->bucket);
        self::assertSame('uploads', $config->prefix);
        self::assertNull($config->cdnUrl);
    }

    #[Test]
    public function constructWithAllParameters(): void
    {
        $config = new StorageConfiguration(
            protocol: 'gcs',
            bucket: 'my-gcs-bucket',
            prefix: 'wp-uploads',
            cdnUrl: 'https://cdn.example.com',
        );

        self::assertSame('gcs', $config->protocol);
        self::assertSame('my-gcs-bucket', $config->bucket);
        self::assertSame('wp-uploads', $config->prefix);
        self::assertSame('https://cdn.example.com', $config->cdnUrl);
    }

    #[Test]
    public function constructWithCustomPrefix(): void
    {
        $config = new StorageConfiguration(
            protocol: 'azure',
            bucket: 'my-container',
            prefix: 'media/files',
        );

        self::assertSame('media/files', $config->prefix);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new \ReflectionClass(StorageConfiguration::class);

        self::assertTrue($reflection->isReadOnly());
    }
}
