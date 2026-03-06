<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\File;

use WpPack\Component\HttpFoundation\File\Exception\FileException;
use WpPack\Component\HttpFoundation\File\Exception\FileNotFoundException;

class File extends \SplFileInfo
{
    public function __construct(string $path, bool $checkPath = true)
    {
        if ($checkPath && !is_file($path)) {
            throw new FileNotFoundException(sprintf('The file "%s" does not exist.', $path));
        }

        parent::__construct($path);
    }

    public function getMimeType(): ?string
    {
        $path = $this->getPathname();

        if (!is_file($path)) {
            return null;
        }

        $mimeType = @mime_content_type($path);

        return $mimeType !== false ? $mimeType : null;
    }

    public function guessExtension(): ?string
    {
        $mimeType = $this->getMimeType();

        if ($mimeType === null) {
            return null;
        }

        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'application/json' => 'json',
            'text/plain' => 'txt',
            'text/html' => 'html',
            'text/css' => 'css',
            'application/javascript' => 'js',
            'application/xml', 'text/xml' => 'xml',
            'application/zip' => 'zip',
            default => null,
        };
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
