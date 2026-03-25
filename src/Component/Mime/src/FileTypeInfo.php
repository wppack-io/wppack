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

namespace WpPack\Component\Mime;

final readonly class FileTypeInfo
{
    public function __construct(
        public ?string $extension,
        public ?string $mimeType,
        public ?string $properFilename = null,
    ) {}

    public function isValid(): bool
    {
        return $this->extension !== null && $this->mimeType !== null;
    }
}
