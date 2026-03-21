<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Handler;

use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectRemovedMessage;

#[AsMessageHandler]
final readonly class S3ObjectRemovedHandler
{
    public function __construct(
        private AttachmentRegistrar $registrar,
    ) {}

    public function __invoke(S3ObjectRemovedMessage $message): void
    {
        $this->registrar->unregister($message->key);
    }
}
