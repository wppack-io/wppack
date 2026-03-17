<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage;

final readonly class StorageConfiguration
{
    public function __construct(
        public string $protocol,
        public string $bucket,
        public string $prefix = 'uploads',
        public ?string $cdnUrl = null,
    ) {}
}
