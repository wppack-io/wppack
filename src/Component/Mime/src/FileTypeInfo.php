<?php

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
