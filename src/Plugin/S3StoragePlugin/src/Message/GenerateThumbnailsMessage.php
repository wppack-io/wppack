<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Message;

final readonly class GenerateThumbnailsMessage
{
    public function __construct(
        public int $attachmentId,
        public int $blogId,
    ) {}
}
