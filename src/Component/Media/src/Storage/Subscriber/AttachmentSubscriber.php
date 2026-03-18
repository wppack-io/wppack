<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage\Subscriber;

use WpPack\Component\Hook\Attribute\Filesystem\Filter\PreWpUniqueFilenameFileListFilter;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Hook\Attribute\Media\Action\DeleteAttachmentAction;
use WpPack\Component\Hook\Attribute\Media\Filter\GetAttachedFileFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\WpGenerateAttachmentMetadataFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\WpGetAttachmentUrlFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\WpReadImageMetadataFilter;
use WpPack\Component\Hook\Attribute\Media\Filter\WpResourceHintsFilter;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\UrlResolver;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;

#[AsHookSubscriber]
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
    #[WpGetAttachmentUrlFilter]
    public function filterAttachmentUrl(string $url, int $postId): string
    {
        $file = get_post_meta($postId, '_wp_attached_file', true);
        if (!\is_string($file) || $file === '') {
            return $url;
        }

        $key = $this->config->prefix . '/' . ltrim($file, '/');

        return $this->urlResolver->resolve($key);
    }

    /**
     * Convert local file path to stream wrapper path.
     */
    #[GetAttachedFileFilter]
    public function filterGetAttachedFile(string $file, int $attachmentId): string
    {
        // If already a stream wrapper path, return as is
        if (str_contains($file, '://')) {
            return $file;
        }

        // Get the relative path from post meta (stored as relative to uploads dir)
        $relativePath = get_post_meta($attachmentId, '_wp_attached_file', true);
        if (\is_string($relativePath) && $relativePath !== '') {
            return sprintf(
                '%s://%s/%s/%s',
                $this->config->protocol,
                $this->config->bucket,
                $this->config->prefix,
                ltrim($relativePath, '/'),
            );
        }

        // Fallback: treat the file path as relative
        $key = ltrim($file, '/');

        return sprintf('%s://%s/%s/%s', $this->config->protocol, $this->config->bucket, $this->config->prefix, $key);
    }

    /**
     * Delete file and thumbnails from storage when attachment is deleted.
     */
    #[DeleteAttachmentAction]
    public function onDeleteAttachment(int $postId): void
    {
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
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    #[WpGenerateAttachmentMetadataFilter]
    public function setFilesizeInMeta(array $metadata, int $attachmentId): array
    {
        if (isset($metadata['filesize']) && $metadata['filesize'] > 0) {
            return $metadata;
        }

        $file = get_post_meta($attachmentId, '_wp_attached_file', true);
        if (!\is_string($file) || $file === '') {
            return $metadata;
        }

        $key = $this->config->prefix . '/' . ltrim($file, '/');

        if ($this->adapter->exists($key)) {
            $objectMetadata = $this->adapter->metadata($key);
            if ($objectMetadata->size !== null) {
                $metadata['filesize'] = $objectMetadata->size;
            }
        }

        return $metadata;
    }

    /**
     * Return false for stream wrapper paths to skip EXIF reading on remote files.
     *
     * @param array<string, mixed>|false $meta
     * @return array<string, mixed>|false
     */
    #[WpReadImageMetadataFilter]
    public function filterReadImageMetadata(array|false $meta, string $file): array|false
    {
        // Skip EXIF reading for stream wrapper paths (remote files)
        if (str_contains($file, '://') && !str_starts_with($file, 'file://')) {
            return false;
        }

        return $meta;
    }

    /**
     * Add CDN domain to dns-prefetch hints.
     *
     * @param list<string> $hints
     * @return list<string>
     */
    #[WpResourceHintsFilter]
    public function filterResourceHints(array $hints, string $relationType): array
    {
        if ($relationType !== 'dns-prefetch') {
            return $hints;
        }

        if ($this->config->cdnUrl === null) {
            return $hints;
        }

        $cdnHost = (string) parse_url($this->config->cdnUrl, \PHP_URL_HOST);
        if ($cdnHost !== '' && !\in_array($cdnHost, $hints, true)) {
            $hints[] = $cdnHost;
        }

        return $hints;
    }

    /**
     * List files in storage directory for unique filename generation.
     *
     * @param list<string>|null $files
     * @return list<string>
     */
    #[PreWpUniqueFilenameFileListFilter]
    public function filterUniqueFilenameFileList(?array $files, string $dir, string $filename): array
    {
        // Only handle storage paths
        if (!str_contains($dir, '://') || str_starts_with($dir, 'file://')) {
            return $files ?? [];
        }

        // Extract the storage key prefix from the stream wrapper path
        $pattern = sprintf('#^%s://%s/(.+)$#', preg_quote($this->config->protocol, '#'), preg_quote($this->config->bucket, '#'));
        if (!preg_match($pattern, rtrim($dir, '/'), $matches)) {
            return $files ?? [];
        }

        $prefix = $matches[1] . '/';
        $result = [];

        foreach ($this->adapter->listContents($prefix, false) as $object) {
            $result[] = basename($object->key);
        }

        return $result;
    }
}
