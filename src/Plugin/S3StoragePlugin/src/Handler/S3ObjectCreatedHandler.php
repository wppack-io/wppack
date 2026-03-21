<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage;

#[AsMessageHandler]
final readonly class S3ObjectCreatedHandler
{
    public function __construct(
        private AttachmentRegistrar $registrar,
        private S3StorageConfiguration $config,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(S3ObjectCreatedMessage $message): void
    {
        if ($message->bucket !== $this->config->bucket) {
            $this->logger?->debug('Ignoring ObjectCreated event from bucket "{bucket}", expected "{expected}".', [
                'bucket' => $message->bucket,
                'expected' => $this->config->bucket,
            ]);

            return;
        }

        $this->registrar->register($message->key);
    }
}
