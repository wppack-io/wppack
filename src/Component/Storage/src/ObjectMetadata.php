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

namespace WpPack\Component\Storage;

final readonly class ObjectMetadata
{
    public function __construct(
        public string $path,
        public ?int $size = null,
        public ?\DateTimeImmutable $lastModified = null,
        public ?string $mimeType = null,
        public bool $isDirectory = false,
    ) {}
}
