<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Handler;

use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage;

#[AsMessageHandler]
final readonly class S3ObjectCreatedHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private string $prefix,
    ) {}

    public function __invoke(S3ObjectCreatedMessage $message): void
    {
        if ($this->isResizedImage($message->key)) {
            return;
        }

        $blogId = $this->parseBlogId($message->key);
        $isMultisite = $blogId > 1 && function_exists('switch_to_blog');

        if ($isMultisite) {
            switch_to_blog($blogId);
        }

        try {
            $relativePath = $this->extractRelativePath($message->key, $blogId);

            if (!function_exists('wp_insert_attachment')) {
                return;
            }

            $mimeType = $this->guessMimeType($relativePath);

            $attachmentData = [
                'post_title' => pathinfo($relativePath, \PATHINFO_FILENAME),
                'post_mime_type' => $mimeType,
                'post_status' => 'inherit',
            ];

            $attachmentId = wp_insert_attachment($attachmentData, $relativePath);

            if (\is_int($attachmentId) && $attachmentId > 0) {
                $this->bus->dispatch(new GenerateThumbnailsMessage(
                    attachmentId: $attachmentId,
                    blogId: $blogId,
                ));
            }
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

        // Match `-{width}x{height}` suffix
        if (preg_match('/-\d+x\d+$/', $filename) === 1) {
            return true;
        }

        // Match `-scaled` suffix
        if (str_ends_with($filename, '-scaled')) {
            return true;
        }

        // Match `-rotated` suffix
        if (str_ends_with($filename, '-rotated')) {
            return true;
        }

        // Match `-e{timestamp}` suffix (10+ digits)
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

    /**
     * Extract the relative path after the prefix (and optional sites/{id}/) portion.
     */
    private function extractRelativePath(string $key, int $blogId): string
    {
        $prefix = rtrim($this->prefix, '/') . '/';

        $relativePath = $key;
        if (str_starts_with($key, $prefix)) {
            $relativePath = substr($key, \strlen($prefix));
        }

        // Remove sites/{blog_id}/ prefix for multisite
        if ($blogId > 1) {
            $sitePrefix = 'sites/' . $blogId . '/';
            if (str_starts_with($relativePath, $sitePrefix)) {
                $relativePath = substr($relativePath, \strlen($sitePrefix));
            }
        }

        return $relativePath;
    }

    private function guessMimeType(string $path): string
    {
        if (function_exists('wp_check_filetype')) {
            /** @var array{type: string|false, ext: string|false} $fileType */
            $fileType = wp_check_filetype(basename($path));

            if (\is_string($fileType['type']) && $fileType['type'] !== '') {
                return $fileType['type'];
            }
        }

        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }
}
