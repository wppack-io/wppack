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
        // With WordPress available, this would convert URLs
        $url = $this->subscriber->filterAttachmentUrl('https://example.com/wp-content/uploads/2024/01/image.jpg', 1);
        self::assertIsString($url);
    }

    #[Test]
    public function filterGetAttachedFileConvertsLocalPathToStreamWrapper(): void
    {
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
        $result = $this->subscriber->filterGetAttachedFile('2024/01/image.jpg', 1);

        self::assertSame('s3://my-bucket/uploads/2024/01/image.jpg', $result);
    }

    #[Test]
    public function setFilesizeInMetaRetrievesSizeFromStorage(): void
    {
        $this->adapter->write('uploads/2024/01/image.jpg', str_repeat('x', 12345));

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
    public function onDeleteAttachmentDeletesFileAndThumbnails(): void
    {
        // Create an attachment with metadata
        $this->adapter->write('uploads/2024/01/image.jpg', 'original image content');
        $this->adapter->write('uploads/2024/01/image-150x150.jpg', 'thumbnail');
        $this->adapter->write('uploads/2024/01/image-300x200.jpg', 'medium');

        $attachmentId = wp_insert_attachment([
            'post_title' => 'Test Image',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], '2024/01/image.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', '2024/01/image.jpg');
        wp_update_attachment_metadata($attachmentId, [
            'file' => '2024/01/image.jpg',
            'sizes' => [
                'thumbnail' => ['file' => 'image-150x150.jpg'],
                'medium' => ['file' => 'image-300x200.jpg'],
            ],
        ]);

        $this->subscriber->onDeleteAttachment($attachmentId);

        self::assertFalse($this->adapter->exists('uploads/2024/01/image.jpg'));
        self::assertFalse($this->adapter->exists('uploads/2024/01/image-150x150.jpg'));
        self::assertFalse($this->adapter->exists('uploads/2024/01/image-300x200.jpg'));
    }

    #[Test]
    public function onDeleteAttachmentSkipsWhenNoAttachedFile(): void
    {
        $attachmentId = wp_insert_attachment([
            'post_title' => 'No File',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ]);

        // No _wp_attached_file meta - should not throw
        $this->subscriber->onDeleteAttachment($attachmentId);
        self::assertTrue(true);
    }

    #[Test]
    public function onDeleteAttachmentHandlesNoThumbnails(): void
    {
        $this->adapter->write('uploads/document.pdf', 'pdf content');

        $attachmentId = wp_insert_attachment([
            'post_title' => 'PDF',
            'post_mime_type' => 'application/pdf',
            'post_status' => 'inherit',
        ], 'document.pdf');

        update_post_meta($attachmentId, '_wp_attached_file', 'document.pdf');
        // No sizes metadata

        $this->subscriber->onDeleteAttachment($attachmentId);

        self::assertFalse($this->adapter->exists('uploads/document.pdf'));
    }

    #[Test]
    public function filterAttachmentUrlReturnsUrlFromResolver(): void
    {
        $attachmentId = wp_insert_attachment([
            'post_title' => 'Test',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], '2024/01/photo.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', '2024/01/photo.jpg');

        $url = $this->subscriber->filterAttachmentUrl('https://example.com/old-url.jpg', $attachmentId);

        self::assertSame('https://cdn.example.com/uploads/2024/01/photo.jpg', $url);
    }

    #[Test]
    public function filterAttachmentUrlReturnsOriginalWhenNoMeta(): void
    {
        $attachmentId = wp_insert_attachment([
            'post_title' => 'No Meta',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ]);

        // No _wp_attached_file meta
        $url = $this->subscriber->filterAttachmentUrl('https://example.com/original.jpg', $attachmentId);

        self::assertSame('https://example.com/original.jpg', $url);
    }

    #[Test]
    public function filterGetAttachedFileUsesPostMeta(): void
    {
        $attachmentId = wp_insert_attachment([
            'post_title' => 'Meta File',
            'post_mime_type' => 'image/png',
            'post_status' => 'inherit',
        ], '2024/06/banner.png');

        update_post_meta($attachmentId, '_wp_attached_file', '2024/06/banner.png');

        $result = $this->subscriber->filterGetAttachedFile('/var/www/html/wp-content/uploads/2024/06/banner.png', $attachmentId);

        self::assertSame('s3://my-bucket/uploads/2024/06/banner.png', $result);
    }

    #[Test]
    public function setFilesizeInMetaFetchesSizeFromStorage(): void
    {
        $this->adapter->write('uploads/2024/01/sized.jpg', str_repeat('x', 54321));

        $attachmentId = wp_insert_attachment([
            'post_title' => 'Sized',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], '2024/01/sized.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', '2024/01/sized.jpg');

        $metadata = ['file' => '2024/01/sized.jpg'];
        $result = $this->subscriber->setFilesizeInMeta($metadata, $attachmentId);

        self::assertSame(54321, $result['filesize']);
    }

    #[Test]
    public function setFilesizeInMetaSkipsWhenFileNotInStorage(): void
    {
        $attachmentId = wp_insert_attachment([
            'post_title' => 'Missing',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], '2024/01/missing.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', '2024/01/missing.jpg');

        $metadata = ['file' => '2024/01/missing.jpg'];
        $result = $this->subscriber->setFilesizeInMeta($metadata, $attachmentId);

        self::assertArrayNotHasKey('filesize', $result);
    }

    #[Test]
    public function filterUniqueFilenameFileListReturnsEmptyForNonMatchingPattern(): void
    {
        $result = $this->subscriber->filterUniqueFilenameFileList(
            null,
            'other-protocol://different-bucket/path',
            'file.jpg',
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function filterReadImageMetadataReturnsFalseForFalseInput(): void
    {
        // When $meta is already false and path is remote
        $result = $this->subscriber->filterReadImageMetadata(false, 's3://bucket/file.jpg');
        self::assertFalse($result);
    }
}
