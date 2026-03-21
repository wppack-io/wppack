<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Message;

final readonly class S3ObjectRemovedMessage
{
    public function __construct(
        public string $bucket,
        public string $key,
    ) {}
}
