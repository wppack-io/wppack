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

namespace WPPack\Component\Media\Storage;

use WPPack\Component\Storage\Adapter\StorageAdapterInterface;

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
