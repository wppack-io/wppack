<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage;

use WpPack\Component\Storage\Adapter\StorageAdapterInterface;

final readonly class UrlResolver
{
    public function __construct(
        private StorageAdapterInterface $adapter,
        private ?string $cdnUrl = null,
    ) {}

    public function resolve(string $key): string
    {
        if ($this->cdnUrl !== null) {
            return rtrim($this->cdnUrl, '/') . '/' . ltrim($key, '/');
        }

        return $this->adapter->publicUrl($key);
    }
}
