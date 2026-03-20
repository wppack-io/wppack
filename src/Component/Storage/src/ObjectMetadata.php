<?php

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
