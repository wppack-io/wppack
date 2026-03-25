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
