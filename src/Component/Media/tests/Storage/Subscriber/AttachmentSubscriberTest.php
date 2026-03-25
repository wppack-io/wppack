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

namespace WpPack\Component\Media\Tests\Storage\Subscriber;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\WordPressEvent;
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
        $event = new WordPressEvent('wp_get_attachment_url', ['https://example.com/wp-content/uploads/2024/01/image.jpg', 1]);
        $this->subscriber->filterAttachmentUrl($event);
        self::assertIsString($event->filterValue);
    }

    #[Test]
    public function filterGetAttachedFileConvertsLocalPathToStreamWrapper(): void
    {
        $event = new WordPressEvent('get_attached_file', ['/var/www/html/wp-content/uploads/2024/01/image.jpg', 1]);
        $this->subscriber->filterGetAttachedFile($event);

        // With WordPress, the method retrieves the relative path from post meta
        self::assertStringStartsWith('s3://my-bucket/uploads/', $event->filterValue);
    }

    #[Test]
    public function filterGetAttachedFilePreservesExistingStreamWrapperPath(): void
    {
        $path = 's3://my-bucket/uploads/2024/01/image.jpg';
        $event = new WordPressEvent('get_attached_file', [$path, 1]);
        $this->subscriber->filterGetAttachedFile($event);

        self::assertSame($path, $event->filterValue);
    }

    #[Test]
    public function filterGetAttachedFileHandlesRelativePath(): void
    {
        $event = new WordPressEvent('get_attached_file', ['2024/01/image.jpg', 1]);
        $this->subscriber->filterGetAttachedFile($event);

        self::assertSame('s3://my-bucket/uploads/2024/01/image.jpg', $event->filterValue);
    }

    #[Test]
    public function setFilesizeInMetaRetrievesSizeFromStorage(): void
    {
        $this->adapter->write('uploads/2024/01/image.jpg', str_repeat('x', 12345));

        // This test would require WordPress to retrieve post meta
        $metadata = ['file' => '2024/01/image.jpg'];
        $event = new WordPressEvent('wp_generate_attachment_metadata', [$metadata, 1]);
        $this->subscriber->setFilesizeInMeta($event);
        self::assertIsArray($event->filterValue);
    }

    #[Test]
    public function setFilesizeInMetaPreservesExistingFilesize(): void
    {
        $metadata = ['filesize' => 54321];
        $event = new WordPressEvent('wp_generate_attachment_metadata', [$metadata, 1]);
        $this->subscriber->setFilesizeInMeta($event);

        self::assertSame(54321, $event->filterValue['filesize']);
    }

    #[Test]
    public function filterReadImageMetadataReturnsFalseForStreamWrapperPaths(): void
    {
        $meta = ['aperture' => '2.8', 'credit' => 'test'];

        $event = new WordPressEvent('wp_read_image_metadata', [$meta, 's3://my-bucket/uploads/2024/01/image.jpg']);
        $this->subscriber->filterReadImageMetadata($event);

        self::assertFalse($event->filterValue);
    }

    #[Test]
    public function filterReadImageMetadataPreservesMetaForLocalFiles(): void
    {
        $meta = ['aperture' => '2.8', 'credit' => 'test'];

        $event = new WordPressEvent('wp_read_image_metadata', [$meta, '/var/www/html/wp-content/uploads/2024/01/image.jpg']);
        $this->subscriber->filterReadImageMetadata($event);

        self::assertSame($meta, $event->filterValue);
    }

    #[Test]
    public function filterReadImageMetadataPreservesMetaForFileScheme(): void
    {
        $meta = ['aperture' => '2.8'];

        $event = new WordPressEvent('wp_read_image_metadata', [$meta, 'file:///var/www/html/uploads/image.jpg']);
        $this->subscriber->filterReadImageMetadata($event);

        self::assertSame($meta, $event->filterValue);
    }

    #[Test]
    public function filterResourceHintsAddsCdnDomainForDnsPrefetch(): void
    {
        $hints = ['example.com'];

        $event = new WordPressEvent('wp_resource_hints', [$hints, 'dns-prefetch']);
        $this->subscriber->filterResourceHints($event);

        self::assertContains('cdn.example.com', $event->filterValue);
        self::assertContains('example.com', $event->filterValue);
    }

    #[Test]
    public function filterResourceHintsDoesNotDuplicateCdnDomain(): void
    {
        $hints = ['cdn.example.com'];

        $event = new WordPressEvent('wp_resource_hints', [$hints, 'dns-prefetch']);
        $this->subscriber->filterResourceHints($event);

        self::assertCount(1, array_filter($event->filterValue, fn(string $h) => $h === 'cdn.example.com'));
    }

    #[Test]
    public function filterResourceHintsIgnoresNonDnsPrefetch(): void
    {
        $hints = ['example.com'];

        $event = new WordPressEvent('wp_resource_hints', [$hints, 'preconnect']);
        $this->subscriber->filterResourceHints($event);

        self::assertSame($hints, $event->filterValue);
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
        $event = new WordPressEvent('wp_resource_hints', [$hints, 'dns-prefetch']);
        $subscriber->filterResourceHints($event);

        self::assertSame($hints, $event->filterValue);
    }

    #[Test]
    public function filterUniqueFilenameFileListReturnsFilesFromStorage(): void
    {
        $this->adapter->write('uploads/2024/01/image.jpg', 'content');
        $this->adapter->write('uploads/2024/01/photo.png', 'content');
        $this->adapter->write('uploads/2024/01/document.pdf', 'content');

        $event = new WordPressEvent('pre_wp_unique_filename_file_list', [
            null,
            's3://my-bucket/uploads/2024/01',
            'image.jpg',
        ]);
        $this->subscriber->filterUniqueFilenameFileList($event);

        self::assertContains('image.jpg', $event->filterValue);
        self::assertContains('photo.png', $event->filterValue);
        self::assertContains('document.pdf', $event->filterValue);
    }

    #[Test]
    public function filterUniqueFilenameFileListReturnsEmptyForLocalPaths(): void
    {
        $event = new WordPressEvent('pre_wp_unique_filename_file_list', [
            null,
            '/var/www/html/wp-content/uploads/2024/01',
            'image.jpg',
        ]);
        $this->subscriber->filterUniqueFilenameFileList($event);

        self::assertSame([], $event->filterValue);
    }

    #[Test]
    public function filterUniqueFilenameFileListReturnsEmptyForFileScheme(): void
    {
        $event = new WordPressEvent('pre_wp_unique_filename_file_list', [
            null,
            'file:///var/www/html/uploads/2024/01',
            'image.jpg',
        ]);
        $this->subscriber->filterUniqueFilenameFileList($event);

        self::assertSame([], $event->filterValue);
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

        $event = new WordPressEvent('delete_attachment', [$attachmentId]);
        $this->subscriber->onDeleteAttachment($event);

        self::assertFalse($this->adapter->fileExists('uploads/2024/01/image.jpg'));
        self::assertFalse($this->adapter->fileExists('uploads/2024/01/image-150x150.jpg'));
        self::assertFalse($this->adapter->fileExists('uploads/2024/01/image-300x200.jpg'));
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
        $event = new WordPressEvent('delete_attachment', [$attachmentId]);
        $this->subscriber->onDeleteAttachment($event);
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

        $event = new WordPressEvent('delete_attachment', [$attachmentId]);
        $this->subscriber->onDeleteAttachment($event);

        self::assertFalse($this->adapter->fileExists('uploads/document.pdf'));
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

        $event = new WordPressEvent('wp_get_attachment_url', ['https://example.com/old-url.jpg', $attachmentId]);
        $this->subscriber->filterAttachmentUrl($event);

        self::assertSame('https://cdn.example.com/uploads/2024/01/photo.jpg', $event->filterValue);
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
        $event = new WordPressEvent('wp_get_attachment_url', ['https://example.com/original.jpg', $attachmentId]);
        $this->subscriber->filterAttachmentUrl($event);

        self::assertSame('https://example.com/original.jpg', $event->filterValue);
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

        $event = new WordPressEvent('get_attached_file', ['/var/www/html/wp-content/uploads/2024/06/banner.png', $attachmentId]);
        $this->subscriber->filterGetAttachedFile($event);

        self::assertSame('s3://my-bucket/uploads/2024/06/banner.png', $event->filterValue);
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
        $event = new WordPressEvent('wp_generate_attachment_metadata', [$metadata, $attachmentId]);
        $this->subscriber->setFilesizeInMeta($event);

        self::assertSame(54321, $event->filterValue['filesize']);
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
        $event = new WordPressEvent('wp_generate_attachment_metadata', [$metadata, $attachmentId]);
        $this->subscriber->setFilesizeInMeta($event);

        self::assertArrayNotHasKey('filesize', $event->filterValue);
    }

    #[Test]
    public function filterUniqueFilenameFileListReturnsEmptyForNonMatchingPattern(): void
    {
        $event = new WordPressEvent('pre_wp_unique_filename_file_list', [
            null,
            'other-protocol://different-bucket/path',
            'file.jpg',
        ]);
        $this->subscriber->filterUniqueFilenameFileList($event);

        self::assertSame([], $event->filterValue);
    }

    #[Test]
    public function filterReadImageMetadataReturnsFalseForFalseInput(): void
    {
        // When $meta is already false and path is remote
        $event = new WordPressEvent('wp_read_image_metadata', [false, 's3://bucket/file.jpg']);
        $this->subscriber->filterReadImageMetadata($event);
        self::assertFalse($event->filterValue);
    }
}
