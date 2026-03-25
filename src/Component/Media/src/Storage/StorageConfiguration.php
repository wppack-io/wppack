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
