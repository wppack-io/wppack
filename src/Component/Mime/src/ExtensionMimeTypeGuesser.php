<?php

declare(strict_types=1);

namespace WpPack\Component\Mime;

final class ExtensionMimeTypeGuesser implements MimeTypeGuesserInterface
{
    public function isGuesserSupported(): bool
    {
        return true;
    }

    public function guessMimeType(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        if ($extension === '') {
            return null;
        }

        $mimeTypes = MimeTypeMap::EXTENSIONS_TO_MIMES[$extension] ?? [];

        return $mimeTypes !== [] ? $mimeTypes[0] : null;
    }
}
