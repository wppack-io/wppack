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

namespace WpPack\Plugin\S3StoragePlugin\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectRemovedMessage;

#[AsMessageHandler]
final readonly class S3ObjectRemovedHandler
{
    public function __construct(
        private AttachmentRegistrar $registrar,
        private S3StorageConfiguration $config,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(S3ObjectRemovedMessage $message): void
    {
        if ($message->bucket !== $this->config->bucket) {
            $this->logger?->debug('Ignoring ObjectRemoved event from bucket "{bucket}", expected "{expected}".', [
                'bucket' => $message->bucket,
                'expected' => $this->config->bucket,
            ]);

            return;
        }

        $this->registrar->unregister($message->key);
    }
}
