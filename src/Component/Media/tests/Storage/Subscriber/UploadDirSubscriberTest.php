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

namespace WPPack\Component\Media\Tests\Storage\Subscriber;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\EventDispatcher\WordPressEvent;
use WPPack\Component\Media\Storage\StorageConfiguration;
use WPPack\Component\Media\Storage\Subscriber\UploadDirSubscriber;
use WPPack\Component\Media\Storage\UrlResolver;
use WPPack\Component\Storage\Test\InMemoryStorageAdapter;

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

        $event = new WordPressEvent('upload_dir', [$dirs]);
        $subscriber->filterUploadDir($event);

        self::assertSame('s3://my-bucket/uploads/2024/01', $event->filterValue['path']);
        self::assertSame('s3://my-bucket/uploads', $event->filterValue['basedir']);
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

        $event = new WordPressEvent('upload_dir', [$dirs]);
        $subscriber->filterUploadDir($event);

        self::assertSame('https://cdn.example.com/uploads/2024/01', $event->filterValue['url']);
        self::assertSame('https://cdn.example.com/uploads', $event->filterValue['baseurl']);
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

        $event = new WordPressEvent('upload_dir', [$dirs]);
        $subscriber->filterUploadDir($event);

        self::assertSame('gcs://my-gcs-bucket/wp-uploads', $event->filterValue['path']);
        self::assertSame('gcs://my-gcs-bucket/wp-uploads', $event->filterValue['basedir']);
    }

    #[Test]
    public function filterUploadDirPreservesSubdirKey(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $config = new StorageConfiguration(
            protocol: 'azure',
            bucket: 'my-container',
            prefix: 'media',
        );
        $resolver = new UrlResolver($adapter, 'https://cdn.azure.com');
        $subscriber = new UploadDirSubscriber($config, $resolver);

        $dirs = [
            'path' => '/var/www/html/wp-content/uploads/2025/03',
            'url' => 'https://example.com/wp-content/uploads/2025/03',
            'subdir' => '/2025/03',
            'basedir' => '/var/www/html/wp-content/uploads',
            'baseurl' => 'https://example.com/wp-content/uploads',
        ];

        $event = new WordPressEvent('upload_dir', [$dirs]);
        $subscriber->filterUploadDir($event);

        self::assertSame('azure://my-container/media/2025/03', $event->filterValue['path']);
        self::assertSame('https://cdn.azure.com/media/2025/03', $event->filterValue['url']);
        self::assertSame('azure://my-container/media', $event->filterValue['basedir']);
        self::assertSame('https://cdn.azure.com/media', $event->filterValue['baseurl']);
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

        $event = new WordPressEvent('upload_dir', [$dirs]);
        $subscriber->filterUploadDir($event);

        // InMemoryStorageAdapter returns 'memory://' prefix for publicUrl
        self::assertSame('memory://uploads/2024/01', $event->filterValue['url']);
        self::assertSame('memory://uploads', $event->filterValue['baseurl']);
    }
}
