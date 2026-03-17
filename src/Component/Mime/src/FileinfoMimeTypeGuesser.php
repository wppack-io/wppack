<?php

declare(strict_types=1);

namespace WpPack\Component\Mime;

final class FileinfoMimeTypeGuesser implements MimeTypeGuesserInterface
{
    public function isGuesserSupported(): bool
    {
        return \function_exists('finfo_open');
    }

    public function guessMimeType(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        return $mimeType !== false ? $mimeType : null;
    }
}
