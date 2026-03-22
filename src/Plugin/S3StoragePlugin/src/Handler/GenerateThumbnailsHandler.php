<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Component\Site\BlogSwitcherInterface;
use WpPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;

#[AsMessageHandler]
final readonly class GenerateThumbnailsHandler
{
    public function __construct(
        private BlogSwitcherInterface $blogSwitcher,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(GenerateThumbnailsMessage $message): void
    {
        $this->blogSwitcher->runInBlogIfNeeded($message->blogId, function () use ($message): void {
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
        });
    }
}
