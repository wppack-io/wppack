<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\HttpFoundation\File;

use WPPack\Component\HttpFoundation\File\Exception\FileException;
use WPPack\Component\HttpFoundation\File\Exception\FileNotFoundException;
use WPPack\Component\Mime\MimeTypes;

class File extends \SplFileInfo
{
    public function __construct(string $path, bool $checkPath = true)
    {
        if ($checkPath && !is_file($path)) {
            throw new FileNotFoundException(sprintf('The file "%s" does not exist.', $path));
        }

        parent::__construct($path);
    }

    /**
     * Static singleton is synced with DI-managed instance via MimeServiceProvider::setDefault().
     */
    public function getMimeType(): ?string
    {
        $path = $this->getPathname();

        if (!is_file($path)) {
            return null;
        }

        return MimeTypes::getDefault()->guessMimeType($path);
    }

    public function guessExtension(): ?string
    {
        $mimeType = $this->getMimeType();

        if ($mimeType === null) {
            return null;
        }

        $extensions = MimeTypes::getDefault()->getExtensions($mimeType);

        return $extensions !== [] ? $extensions[0] : null;
    }

    public function move(string $directory, ?string $name = null): self
    {
        $target = rtrim($directory, '/') . '/' . ($name ?? $this->getBasename());

        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        if (!@rename($this->getPathname(), $target)) {
            throw new FileException(sprintf('Could not move the file "%s" to "%s".', $this->getPathname(), $target));
        }

        return new self($target);
    }
}
