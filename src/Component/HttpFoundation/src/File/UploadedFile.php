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

class UploadedFile extends File
{
    private const ERROR_MESSAGES = [
        \UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        \UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
        \UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        \UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        \UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        \UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        \UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];

    public function __construct(
        string $path,
        public readonly string $originalName,
        public readonly ?string $mimeType = null,
        public readonly int $error = \UPLOAD_ERR_OK,
    ) {
        parent::__construct($path, false);
    }

    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function isValid(): bool
    {
        return $this->error === \UPLOAD_ERR_OK;
    }

    public function getErrorMessage(): string
    {
        return self::ERROR_MESSAGES[$this->error] ?? 'Unknown upload error.';
    }

    #[\ReturnTypeWillChange]
    public function getSize(): ?int
    {
        if (!$this->isValid()) {
            return null;
        }

        $size = @filesize($this->getPathname());

        return $size === false ? null : $size;
    }

    public function getMimeType(): ?string
    {
        if (!$this->isValid()) {
            return null;
        }

        return parent::getMimeType();
    }

    public function move(string $directory, ?string $name = null): File
    {
        if (!$this->isValid()) {
            throw new FileException($this->getErrorMessage());
        }

        // Strip any directory separators the client may have sent in either
        // the caller-supplied name or the browser-supplied original name;
        // a '../' in \$this->originalName would otherwise escape \$directory.
        $basename = basename($name ?? $this->originalName);
        if ($basename === '' || $basename === '.' || $basename === '..' || str_contains($basename, "\0")) {
            throw new FileException(sprintf('Invalid target filename "%s".', $name ?? $this->originalName));
        }

        $target = rtrim($directory, '/') . '/' . $basename;

        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        if (!@move_uploaded_file($this->getPathname(), $target)) {
            throw new FileException(sprintf('Could not move the file "%s" to "%s".', $this->getPathname(), $target));
        }

        return new File($target);
    }

    /**
     * Delegates file upload handling to WordPress' wp_handle_upload().
     *
     * @param array<string, mixed> $overrides
     * @return array{file: string, url: string, type: string}|array{error: string}
     */
    public function wpHandleUpload(array $overrides = []): array
    {
        $fileArray = $this->toFilesArray();
        $fileArray['size'] ??= 0;

        /** @var array{file: string, url: string, type: string}|array{error: string} $result */
        $result = wp_handle_upload($fileArray, $overrides);

        return $result;
    }

    /**
     * Converts the UploadedFile to the $_FILES array format WordPress expects.
     *
     * @return array{name: string, type: string, tmp_name: string, error: int, size: ?int}
     */
    public function toFilesArray(): array
    {
        return [
            'name' => $this->originalName,
            'type' => $this->mimeType ?? '',
            'tmp_name' => $this->getPathname(),
            'error' => $this->error,
            'size' => $this->getSize(),
        ];
    }
}
