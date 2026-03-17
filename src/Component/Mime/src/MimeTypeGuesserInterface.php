<?php

declare(strict_types=1);

namespace WpPack\Component\Mime;

interface MimeTypeGuesserInterface
{
    public function isGuesserSupported(): bool;

    public function guessMimeType(string $path): ?string;
}
