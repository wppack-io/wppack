<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Handler;

use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;

#[AsMessageHandler]
final readonly class GenerateThumbnailsHandler
{
    public function __invoke(GenerateThumbnailsMessage $message): void
    {
        $isMultisite = $message->blogId > 1;

        if ($isMultisite) {
            switch_to_blog($message->blogId);
        }

        try {
            $file = get_attached_file($message->attachmentId);

            if (!\is_string($file) || $file === '') {
                return;
            }

            $metadata = wp_generate_attachment_metadata($message->attachmentId, $file);

            if ($metadata !== []) {
                wp_update_attachment_metadata($message->attachmentId, $metadata);
            }
        } finally {
            if ($isMultisite) {
                restore_current_blog();
            }
        }
    }
}
