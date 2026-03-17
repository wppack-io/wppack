<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

final readonly class PreSignedUrlResult
{
    public function __construct(
        public string $url,
        public string $key,
        public int $expiresIn,
    ) {}
}
