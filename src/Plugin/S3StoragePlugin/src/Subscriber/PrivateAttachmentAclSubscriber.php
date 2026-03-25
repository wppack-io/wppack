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

namespace WpPack\Plugin\S3StoragePlugin\Subscriber;

use Psr\Log\LoggerInterface;
use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Media\Storage\PrivateAttachmentChecker;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Visibility;

/**
 * Sets ACL on S3 objects for private attachments after metadata generation.
 *
 * Placed in S3StoragePlugin because ACL operations are a cloud-specific concept.
 */
final readonly class PrivateAttachmentAclSubscriber
{
    public function __construct(
        private StorageConfiguration $config,
        private StorageAdapterInterface $adapter,
        private PrivateAttachmentChecker $checker,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Set visibility on the main file and all thumbnails for private attachments.
     *
     * Runs after AttachmentSubscriber::setFilesizeInMeta (priority 10).
     */
    #[AsEventListener(event: 'wp_generate_attachment_metadata', priority: 20, acceptedArgs: 2)]
    public function setVisibilityOnGenerate(WordPressEvent $event): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $event->filterValue;
        /** @var int $attachmentId */
        $attachmentId = $event->args[1];

        if (!$this->checker->isPrivate($attachmentId)) {
            return;
        }

        $file = get_post_meta($attachmentId, '_wp_attached_file', true);
        if (!\is_string($file) || $file === '') {
            return;
        }

        $visibility = Visibility::PRIVATE;

        // Set visibility on main file
        $mainKey = $this->config->prefix . '/' . ltrim($file, '/');
        $this->setVisibilitySafe($mainKey, $visibility);

        // Set visibility on all thumbnails
        if (isset($metadata['sizes']) && \is_array($metadata['sizes'])) {
            $directory = \dirname($mainKey);
            /** @var array<string, mixed> $size */
            foreach ($metadata['sizes'] as $size) {
                if (isset($size['file']) && \is_string($size['file'])) {
                    $this->setVisibilitySafe($directory . '/' . $size['file'], $visibility);
                }
            }
        }
    }

    private function setVisibilitySafe(string $key, Visibility $visibility): void
    {
        try {
            $this->adapter->setVisibility($key, $visibility);
        } catch (\Throwable $e) {
            $this->logger?->warning('Failed to set visibility on storage object', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }
}
