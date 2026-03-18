<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Logger\Attribute\LoggerChannel;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;
use WpPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage;

#[AsMessageHandler]
final readonly class S3ObjectCreatedHandler
{
    private MimeTypesInterface $mimeTypes;

    public function __construct(
        private MessageBusInterface $bus,
        private string $prefix,
        ?MimeTypesInterface $mimeTypes = null,
        #[LoggerChannel('s3-storage')]
        private ?LoggerInterface $logger = null,
    ) {
        $this->mimeTypes = $mimeTypes ?? MimeTypes::getDefault();
    }

    public function __invoke(S3ObjectCreatedMessage $message): void
    {
        if ($this->isResizedImage($message->key)) {
            return;
        }

        $blogId = $this->parseBlogId($message->key);
        $isMultisite = $blogId > 1;

        if ($isMultisite) {
            switch_to_blog($blogId);
        }

        try {
            $relativePath = $this->extractRelativePath($message->key, $blogId);

            $mimeType = $this->mimeTypes->guessMimeType($relativePath) ?? 'application/octet-stream';

            $attachmentData = [
                'post_title' => pathinfo($relativePath, \PATHINFO_FILENAME),
                'post_mime_type' => $mimeType,
                'post_status' => 'inherit',
            ];

            $attachmentId = wp_insert_attachment($attachmentData, $relativePath);

            if ($attachmentId instanceof \WP_Error) {
                $this->logger?->error('wp_insert_attachment failed for key "{key}": {error}', [
                    'key' => $message->key,
                    'error' => $attachmentId->get_error_message(),
                ]);

                return;
            }

            if ($attachmentId > 0) {
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

}
