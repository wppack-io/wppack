<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Tests\Storage\Subscriber;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\Subscriber\AttachmentSubscriber;
use WpPack\Component\Media\Storage\UrlResolver;
use WpPack\Component\Storage\Test\InMemoryStorageAdapter;

final class AttachmentSubscriberTest extends TestCase
{
    private InMemoryStorageAdapter $adapter;
    private StorageConfiguration $config;
    private UrlResolver $resolver;
    private AttachmentSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryStorageAdapter();
        $this->config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
            cdnUrl: 'https://cdn.example.com',
        );
        $this->resolver = new UrlResolver($this->adapter, 'https://cdn.example.com');
        $this->subscriber = new AttachmentSubscriber($this->config, $this->resolver, $this->adapter);
    }

    #[Test]
    public function filterAttachmentUrlRequiresWordPressFunctions(): void
    {
        if (!function_exists('get_post_meta')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        // With WordPress available, this would convert URLs
        $url = $this->subscriber->filterAttachmentUrl('https://example.com/wp-content/uploads/2024/01/image.jpg', 1);
        self::assertIsString($url);
    }

    #[Test]
    public function filterGetAttachedFileConvertsLocalPathToStreamWrapper(): void
    {
        if (!function_exists('get_post_meta')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $result = $this->subscriber->filterGetAttachedFile('/var/www/html/wp-content/uploads/2024/01/image.jpg', 1);

        // With WordPress, the method retrieves the relative path from post meta
        self::assertStringStartsWith('s3://my-bucket/uploads/', $result);
    }

    #[Test]
    public function filterGetAttachedFilePreservesExistingStreamWrapperPath(): void
    {
        $path = 's3://my-bucket/uploads/2024/01/image.jpg';
        $result = $this->subscriber->filterGetAttachedFile($path, 1);

        self::assertSame($path, $result);
    }

    #[Test]
    public function filterGetAttachedFileHandlesRelativePath(): void
    {
        if (function_exists('get_post_meta')) {
            // With WordPress, the method uses get_post_meta to get relative path
            self::markTestSkipped('This test verifies fallback behavior without WordPress.');
        }

        $result = $this->subscriber->filterGetAttachedFile('2024/01/image.jpg', 1);

        self::assertSame('s3://my-bucket/uploads/2024/01/image.jpg', $result);
    }

    #[Test]
    public function setFilesizeInMetaRetrievesSizeFromStorage(): void
    {
        $this->adapter->write('uploads/2024/01/image.jpg', str_repeat('x', 12345));

        if (!function_exists('get_post_meta')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        // This test would require WordPress to retrieve post meta
        $metadata = ['file' => '2024/01/image.jpg'];
        $result = $this->subscriber->setFilesizeInMeta($metadata, 1);
        self::assertIsArray($result);
    }

    #[Test]
    public function setFilesizeInMetaPreservesExistingFilesize(): void
    {
        $metadata = ['filesize' => 54321];
        $result = $this->subscriber->setFilesizeInMeta($metadata, 1);

        self::assertSame(54321, $result['filesize']);
    }

    #[Test]
    public function filterReadImageMetadataReturnsFalseForStreamWrapperPaths(): void
    {
        $meta = ['aperture' => '2.8', 'credit' => 'test'];

        $result = $this->subscriber->filterReadImageMetadata($meta, 's3://my-bucket/uploads/2024/01/image.jpg');

        self::assertFalse($result);
    }

    #[Test]
    public function filterReadImageMetadataPreservesMetaForLocalFiles(): void
    {
        $meta = ['aperture' => '2.8', 'credit' => 'test'];

        $result = $this->subscriber->filterReadImageMetadata($meta, '/var/www/html/wp-content/uploads/2024/01/image.jpg');

        self::assertSame($meta, $result);
    }

    #[Test]
    public function filterReadImageMetadataPreservesMetaForFileScheme(): void
    {
        $meta = ['aperture' => '2.8'];

        $result = $this->subscriber->filterReadImageMetadata($meta, 'file:///var/www/html/uploads/image.jpg');

        self::assertSame($meta, $result);
    }

    #[Test]
    public function filterResourceHintsAddsCdnDomainForDnsPrefetch(): void
    {
        $hints = ['example.com'];

        $result = $this->subscriber->filterResourceHints($hints, 'dns-prefetch');

        self::assertContains('cdn.example.com', $result);
        self::assertContains('example.com', $result);
    }

    #[Test]
    public function filterResourceHintsDoesNotDuplicateCdnDomain(): void
    {
        $hints = ['cdn.example.com'];

        $result = $this->subscriber->filterResourceHints($hints, 'dns-prefetch');

        self::assertCount(1, array_filter($result, fn(string $h) => $h === 'cdn.example.com'));
    }

    #[Test]
    public function filterResourceHintsIgnoresNonDnsPrefetch(): void
    {
        $hints = ['example.com'];

        $result = $this->subscriber->filterResourceHints($hints, 'preconnect');

        self::assertSame($hints, $result);
    }

    #[Test]
    public function filterResourceHintsSkipsWhenNoCdnUrl(): void
    {
        $config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
        );
        $resolver = new UrlResolver($this->adapter);
        $subscriber = new AttachmentSubscriber($config, $resolver, $this->adapter);

        $hints = ['example.com'];
        $result = $subscriber->filterResourceHints($hints, 'dns-prefetch');

        self::assertSame($hints, $result);
    }

    #[Test]
    public function filterUniqueFilenameFileListReturnsFilesFromStorage(): void
    {
        $this->adapter->write('uploads/2024/01/image.jpg', 'content');
        $this->adapter->write('uploads/2024/01/photo.png', 'content');
        $this->adapter->write('uploads/2024/01/document.pdf', 'content');

        $result = $this->subscriber->filterUniqueFilenameFileList(
            null,
            's3://my-bucket/uploads/2024/01',
            'image.jpg',
        );

        self::assertContains('image.jpg', $result);
        self::assertContains('photo.png', $result);
        self::assertContains('document.pdf', $result);
    }

    #[Test]
    public function filterUniqueFilenameFileListReturnsEmptyForLocalPaths(): void
    {
        $result = $this->subscriber->filterUniqueFilenameFileList(
            null,
            '/var/www/html/wp-content/uploads/2024/01',
            'image.jpg',
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function filterUniqueFilenameFileListReturnsEmptyForFileScheme(): void
    {
        $result = $this->subscriber->filterUniqueFilenameFileList(
            null,
            'file:///var/www/html/uploads/2024/01',
            'image.jpg',
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function onDeleteAttachmentRequiresWordPressFunctions(): void
    {
        if (!function_exists('get_post_meta')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->subscriber->onDeleteAttachment(1);
        self::assertTrue(true); // No exception thrown
    }
}
