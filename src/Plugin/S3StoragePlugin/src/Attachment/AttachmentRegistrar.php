<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Attachment;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;
use WpPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;

final readonly class AttachmentRegistrar
{
    private MimeTypesInterface $mimeTypes;

    public function __construct(
        private MessageBusInterface $bus,
        private string $prefix,
        ?MimeTypesInterface $mimeTypes = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->mimeTypes = $mimeTypes ?? MimeTypes::getDefault();
    }

    public function register(string $key, ?int $userId = null): ?int
    {
        if ($this->isResizedImage($key)) {
            return null;
        }

        $blogId = $this->parseBlogId($key);
        $isMultisite = $blogId > 1;

        if ($isMultisite) {
            switch_to_blog($blogId);
        }

        try {
            $relativePath = $this->extractRelativePath($key, $blogId);

            $existingId = $this->findExistingAttachment($relativePath);
            if ($existingId !== null) {
                $this->logger?->debug('Attachment already exists for "{path}" (ID: {id}), skipping.', [
                    'path' => $relativePath,
                    'id' => $existingId,
                ]);

                return $existingId;
            }

            $mimeType = $this->mimeTypes->guessMimeType($relativePath) ?? 'application/octet-stream';

            $attachmentData = [
                'post_title' => pathinfo($relativePath, \PATHINFO_FILENAME),
                'post_mime_type' => $mimeType,
                'post_status' => 'inherit',
            ];

            if ($userId !== null) {
                $attachmentData['post_author'] = $userId;
            }

            $attachmentId = wp_insert_attachment($attachmentData, $relativePath);

            if ($attachmentId instanceof \WP_Error) {
                $this->logger?->error('wp_insert_attachment failed for key "{key}": {error}', [
                    'key' => $key,
                    'error' => $attachmentId->get_error_message(),
                ]);

                return null;
            }

            if ($attachmentId > 0) {
                $this->bus->dispatch(new GenerateThumbnailsMessage(
                    attachmentId: $attachmentId,
                    blogId: $blogId,
                ));
            }

            return $attachmentId > 0 ? $attachmentId : null;
        } finally {
            if ($isMultisite) {
                restore_current_blog();
            }
        }
    }

    public function unregister(string $key): ?int
    {
        if ($this->isResizedImage($key)) {
            return null;
        }

        $blogId = $this->parseBlogId($key);
        $isMultisite = $blogId > 1;

        if ($isMultisite) {
            switch_to_blog($blogId);
        }

        try {
            $relativePath = $this->extractRelativePath($key, $blogId);
            $existingId = $this->findExistingAttachment($relativePath);

            if ($existingId === null) {
                $this->logger?->debug('No attachment found for "{path}", skipping deletion.', [
                    'path' => $relativePath,
                ]);

                return null;
            }

            $result = wp_delete_attachment($existingId, true);

            if (!$result instanceof \WP_Post) {
                $this->logger?->error('wp_delete_attachment failed for attachment ID {id}.', [
                    'id' => $existingId,
                ]);

                return null;
            }

            return $existingId;
        } finally {
            if ($isMultisite) {
                restore_current_blog();
            }
        }
    }

    /**
     * Detect resized/derivative images that should not create new attachments.
     *
     * Matches patterns:
     * - `-{width}x{height}` (e.g., `-100x200.jpg`)
     * - `-scaled` (e.g., `image-scaled.jpg`)
     * - `-rotated` (e.g., `image-rotated.png`)
     * - `-e{timestamp}` with 10+ digits (e.g., `image-e1234567890.jpg`)
     */
    public function isResizedImage(string $key): bool
    {
        $filename = pathinfo($key, \PATHINFO_FILENAME);

        if (preg_match('/-\d+x\d+$/', $filename) === 1) {
            return true;
        }

        if (str_ends_with($filename, '-scaled')) {
            return true;
        }

        if (str_ends_with($filename, '-rotated')) {
            return true;
        }

        if (preg_match('/-e\d{10,}$/', $filename) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Extract blog ID from multisite path pattern `/sites/{blog_id}/`.
     */
    public function parseBlogId(string $key): int
    {
        if (preg_match('#/sites/(\d+)/#', $key, $matches) === 1) {
            return (int) $matches[1];
        }

        return 1;
    }

    private function extractRelativePath(string $key, int $blogId): string
    {
        $prefix = rtrim($this->prefix, '/') . '/';

        $relativePath = $key;
        if (str_starts_with($key, $prefix)) {
            $relativePath = substr($key, \strlen($prefix));
        }

        if ($blogId > 1) {
            $sitePrefix = 'sites/' . $blogId . '/';
            if (str_starts_with($relativePath, $sitePrefix)) {
                $relativePath = substr($relativePath, \strlen($sitePrefix));
            }
        }

        return $relativePath;
    }

    private function findExistingAttachment(string $relativePath): ?int
    {
        $existing = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'meta_key' => '_wp_attached_file',
            'meta_value' => $relativePath,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if ($existing !== []) {
            return (int) $existing[0];
        }

        return null;
    }
}
