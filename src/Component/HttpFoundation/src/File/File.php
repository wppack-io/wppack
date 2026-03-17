<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\File;

use WpPack\Component\HttpFoundation\File\Exception\FileException;
use WpPack\Component\HttpFoundation\File\Exception\FileNotFoundException;
use WpPack\Component\Mime\MimeTypes;

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
