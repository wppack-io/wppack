<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;

#[AsMessageHandler]
final readonly class GenerateThumbnailsHandler
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(GenerateThumbnailsMessage $message): void
    {
        $isMultisite = $message->blogId > 1;

        if ($isMultisite) {
            switch_to_blog($message->blogId);
        }

        try {
            $file = get_attached_file($message->attachmentId);

            if (!\is_string($file) || $file === '') {
                $this->logger?->warning('No attached file found for attachment ID {id}.', [
                    'id' => $message->attachmentId,
                ]);

                return;
            }

            $metadata = wp_generate_attachment_metadata($message->attachmentId, $file);

            if ($metadata !== []) {
                wp_update_attachment_metadata($message->attachmentId, $metadata);
            } else {
                $this->logger?->warning('wp_generate_attachment_metadata returned empty for attachment ID {id}.', [
                    'id' => $message->attachmentId,
                ]);
            }
        } finally {
            if ($isMultisite) {
                restore_current_blog();
            }
        }
    }
}
