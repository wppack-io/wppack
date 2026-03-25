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

namespace WpPack\Component\Media\Storage\Subscriber;

use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\UrlResolver;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;

final readonly class AttachmentSubscriber
{
    public function __construct(
        private StorageConfiguration $config,
        private UrlResolver $urlResolver,
        private StorageAdapterInterface $adapter,
    ) {}

    /**
     * Convert WordPress attachment URL to CDN/storage URL.
     */
    #[AsEventListener(event: 'wp_get_attachment_url', acceptedArgs: 2)]
    public function filterAttachmentUrl(WordPressEvent $event): void
    {
        /** @var int $postId */
        $postId = $event->args[1];

        $file = get_post_meta($postId, '_wp_attached_file', true);
        if (!\is_string($file) || $file === '') {
            return;
        }

        $key = $this->config->prefix . '/' . ltrim($file, '/');

        $event->filterValue = $this->urlResolver->resolve($key);
    }

    /**
     * Convert local file path to stream wrapper path.
     */
    #[AsEventListener(event: 'get_attached_file', acceptedArgs: 2)]
    public function filterGetAttachedFile(WordPressEvent $event): void
    {
        /** @var string $file */
        $file = $event->filterValue;
        /** @var int $attachmentId */
        $attachmentId = $event->args[1];

        // If already a stream wrapper path, return as is
        if (str_contains($file, '://')) {
            return;
        }

        // Get the relative path from post meta (stored as relative to uploads dir)
        $relativePath = get_post_meta($attachmentId, '_wp_attached_file', true);
        if (\is_string($relativePath) && $relativePath !== '') {
            $event->filterValue = sprintf(
                '%s://%s/%s/%s',
                $this->config->protocol,
                $this->config->bucket,
                $this->config->prefix,
                ltrim($relativePath, '/'),
            );

            return;
        }

        // Fallback: treat the file path as relative
        $key = ltrim($file, '/');

        $event->filterValue = sprintf('%s://%s/%s/%s', $this->config->protocol, $this->config->bucket, $this->config->prefix, $key);
    }

    /**
     * Delete file and thumbnails from storage when attachment is deleted.
     */
    #[AsEventListener(event: 'delete_attachment')]
    public function onDeleteAttachment(WordPressEvent $event): void
    {
        /** @var int $postId */
        $postId = $event->args[0];

        $file = get_post_meta($postId, '_wp_attached_file', true);
        if (!\is_string($file) || $file === '') {
            return;
        }

        $keysToDelete = [];
        $key = $this->config->prefix . '/' . ltrim($file, '/');
        $keysToDelete[] = $key;

        // Delete thumbnails
        $metadata = wp_get_attachment_metadata($postId);
        if (\is_array($metadata) && isset($metadata['sizes']) && \is_array($metadata['sizes'])) {
            $directory = \dirname($key);
            /** @var array<string, mixed> $size */
            foreach ($metadata['sizes'] as $size) {
                if (isset($size['file']) && \is_string($size['file'])) {
                    $keysToDelete[] = $directory . '/' . $size['file'];
                }
            }
        }

        $this->adapter->deleteMultiple($keysToDelete);
    }

    /**
     * Set filesize in attachment metadata for remote files.
     */
    #[AsEventListener(event: 'wp_generate_attachment_metadata', acceptedArgs: 2)]
    public function setFilesizeInMeta(WordPressEvent $event): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $event->filterValue;
        /** @var int $attachmentId */
        $attachmentId = $event->args[1];

        if (isset($metadata['filesize']) && $metadata['filesize'] > 0) {
            return;
        }

        $file = get_post_meta($attachmentId, '_wp_attached_file', true);
        if (!\is_string($file) || $file === '') {
            return;
        }

        $key = $this->config->prefix . '/' . ltrim($file, '/');

        if ($this->adapter->fileExists($key)) {
            $objectMetadata = $this->adapter->metadata($key);
            if ($objectMetadata->size !== null) {
                $metadata['filesize'] = $objectMetadata->size;
            }
        }

        $event->filterValue = $metadata;
    }

    /**
     * Return false for stream wrapper paths to skip EXIF reading on remote files.
     */
    #[AsEventListener(event: 'wp_read_image_metadata', acceptedArgs: 2)]
    public function filterReadImageMetadata(WordPressEvent $event): void
    {
        /** @var string $file */
        $file = $event->args[1];

        // Skip EXIF reading for stream wrapper paths (remote files)
        if (str_contains($file, '://') && !str_starts_with($file, 'file://')) {
            $event->filterValue = false;
        }
    }

    /**
     * Add CDN domain to dns-prefetch hints.
     */
    #[AsEventListener(event: 'wp_resource_hints', acceptedArgs: 2)]
    public function filterResourceHints(WordPressEvent $event): void
    {
        /** @var list<string> $hints */
        $hints = $event->filterValue;
        /** @var string $relationType */
        $relationType = $event->args[1];

        if ($relationType !== 'dns-prefetch') {
            return;
        }

        if ($this->config->cdnUrl === null) {
            return;
        }

        $cdnHost = (string) parse_url($this->config->cdnUrl, \PHP_URL_HOST);
        if ($cdnHost !== '' && !\in_array($cdnHost, $hints, true)) {
            $hints[] = $cdnHost;
        }

        $event->filterValue = $hints;
    }

    /**
     * List files in storage directory for unique filename generation.
     */
    #[AsEventListener(event: 'pre_wp_unique_filename_file_list', acceptedArgs: 3)]
    public function filterUniqueFilenameFileList(WordPressEvent $event): void
    {
        /** @var string $dir */
        $dir = $event->args[1];

        // Only handle storage paths
        if (!str_contains($dir, '://') || str_starts_with($dir, 'file://')) {
            $event->filterValue = $event->filterValue ?? [];

            return;
        }

        // Extract the storage key prefix from the stream wrapper path
        $pattern = sprintf('#^%s://%s/(.+)$#', preg_quote($this->config->protocol, '#'), preg_quote($this->config->bucket, '#'));
        if (!preg_match($pattern, rtrim($dir, '/'), $matches)) {
            $event->filterValue = $event->filterValue ?? [];

            return;
        }

        $prefix = $matches[1] . '/';
        $result = [];

        foreach ($this->adapter->listContents($prefix, false) as $object) {
            $result[] = basename($object->path);
        }

        $event->filterValue = $result;
    }
}
