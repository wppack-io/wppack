<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Tests\Storage\Subscriber;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\Subscriber\UploadDirSubscriber;
use WpPack\Component\Media\Storage\UrlResolver;
use WpPack\Component\Storage\Test\InMemoryStorageAdapter;

final class UploadDirSubscriberTest extends TestCase
{
    #[Test]
    public function filterUploadDirRewritesPathToStreamWrapperPath(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
        );
        $resolver = new UrlResolver($adapter);
        $subscriber = new UploadDirSubscriber($config, $resolver);

        $dirs = [
            'path' => '/var/www/html/wp-content/uploads/2024/01',
            'url' => 'https://example.com/wp-content/uploads/2024/01',
            'subdir' => '/2024/01',
            'basedir' => '/var/www/html/wp-content/uploads',
            'baseurl' => 'https://example.com/wp-content/uploads',
        ];

        $result = $subscriber->filterUploadDir($dirs);

        self::assertSame('s3://my-bucket/uploads/2024/01', $result['path']);
        self::assertSame('s3://my-bucket/uploads', $result['basedir']);
    }

    #[Test]
    public function filterUploadDirRewritesUrlToCdn(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
            cdnUrl: 'https://cdn.example.com',
        );
        $resolver = new UrlResolver($adapter, 'https://cdn.example.com');
        $subscriber = new UploadDirSubscriber($config, $resolver);

        $dirs = [
            'path' => '/var/www/html/wp-content/uploads/2024/01',
            'url' => 'https://example.com/wp-content/uploads/2024/01',
            'subdir' => '/2024/01',
            'basedir' => '/var/www/html/wp-content/uploads',
            'baseurl' => 'https://example.com/wp-content/uploads',
        ];

        $result = $subscriber->filterUploadDir($dirs);

        self::assertSame('https://cdn.example.com/uploads/2024/01', $result['url']);
        self::assertSame('https://cdn.example.com/uploads', $result['baseurl']);
    }

    #[Test]
    public function filterUploadDirHandlesEmptySubdir(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $config = new StorageConfiguration(
            protocol: 'gcs',
            bucket: 'my-gcs-bucket',
            prefix: 'wp-uploads',
        );
        $resolver = new UrlResolver($adapter);
        $subscriber = new UploadDirSubscriber($config, $resolver);

        $dirs = [
            'path' => '/var/www/html/wp-content/uploads',
            'url' => 'https://example.com/wp-content/uploads',
            'subdir' => '',
            'basedir' => '/var/www/html/wp-content/uploads',
            'baseurl' => 'https://example.com/wp-content/uploads',
        ];

        $result = $subscriber->filterUploadDir($dirs);

        self::assertSame('gcs://my-gcs-bucket/wp-uploads', $result['path']);
        self::assertSame('gcs://my-gcs-bucket/wp-uploads', $result['basedir']);
    }

    #[Test]
    public function filterUploadDirUsesAdapterPublicUrlWhenNoCdn(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
        );
        $resolver = new UrlResolver($adapter);
        $subscriber = new UploadDirSubscriber($config, $resolver);

        $dirs = [
            'path' => '/var/www/html/wp-content/uploads/2024/01',
            'url' => 'https://example.com/wp-content/uploads/2024/01',
            'subdir' => '/2024/01',
            'basedir' => '/var/www/html/wp-content/uploads',
            'baseurl' => 'https://example.com/wp-content/uploads',
        ];

        $result = $subscriber->filterUploadDir($dirs);

        // InMemoryStorageAdapter returns 'memory://' prefix for publicUrl
        self::assertSame('memory://uploads/2024/01', $result['url']);
        self::assertSame('memory://uploads', $result['baseurl']);
    }
}
