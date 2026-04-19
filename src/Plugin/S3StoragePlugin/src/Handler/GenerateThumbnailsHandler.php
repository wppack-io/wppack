<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Plugin\S3StoragePlugin\Handler;

use Psr\Log\LoggerInterface;
use WPPack\Component\Media\AttachmentManagerInterface;
use WPPack\Component\Messenger\Attribute\AsMessageHandler;
use WPPack\Component\Site\BlogSwitcherInterface;
use WPPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;

#[AsMessageHandler]
final readonly class GenerateThumbnailsHandler
{
    public function __construct(
        private BlogSwitcherInterface $blogSwitcher,
        private AttachmentManagerInterface $attachment,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(GenerateThumbnailsMessage $message): void
    {
        $this->blogSwitcher->runInBlogIfNeeded($message->blogId, function () use ($message): void {
            $file = $this->attachment->getFile($message->attachmentId);

            if ($file === null) {
                $this->logger?->warning('No attached file found for attachment ID {id}.', [
                    'id' => $message->attachmentId,
                ]);

                return;
            }

            $metadata = $this->attachment->generateMetadata($message->attachmentId, $file);

            if ($metadata !== []) {
                $this->attachment->updateMetadata($message->attachmentId, $metadata);
            } else {
                $this->logger?->warning('wp_generate_attachment_metadata returned empty for attachment ID {id}.', [
                    'id' => $message->attachmentId,
                ]);
            }
        });
    }
}
