<?php

declare(strict_types=1);

namespace WpPack\Component\Mime;

final class WordPressMimeTypeGuesser implements MimeTypeGuesserInterface
{
    public function isGuesserSupported(): bool
    {
        return true;
    }

    public function guessMimeType(string $path): ?string
    {
        /** @var array{type: string|false, ext: string|false} $fileType */
        $fileType = wp_check_filetype(basename($path));

        if (\is_string($fileType['type']) && $fileType['type'] !== '') {
            return $fileType['type'];
        }

        return null;
    }
}
