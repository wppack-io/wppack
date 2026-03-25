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

namespace WpPack\Component\HttpFoundation;

use WpPack\Component\HttpFoundation\File\UploadedFile;

class FileBag implements \Countable
{
    /** @var array<string, UploadedFile|array<string, UploadedFile>> */
    private array $files = [];

    /**
     * @param array<string, mixed> $files Raw $_FILES array
     */
    public function __construct(array $files = [])
    {
        foreach ($files as $key => $file) {
            $this->files[$key] = self::normalize($file);
        }
    }

    public static function createFromGlobals(): self
    {
        return new self($_FILES);
    }

    /**
     * @return UploadedFile|array<string, UploadedFile>|null
     */
    public function get(string $key): UploadedFile|array|null
    {
        return $this->files[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->files[$key]);
    }

    /**
     * @return array<string, UploadedFile|array<string, UploadedFile>>
     */
    public function all(): array
    {
        return $this->files;
    }

    public function count(): int
    {
        return \count($this->files);
    }

    /**
     * @return UploadedFile|array<string, UploadedFile>
     */
    private static function normalize(mixed $file): UploadedFile|array
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }

        if (!\is_array($file)) {
            throw new \InvalidArgumentException('Invalid file data provided.');
        }

        // Standard single file: ['name' => '...', 'tmp_name' => '...', ...]
        if (isset($file['tmp_name']) && \is_string($file['tmp_name'])) {
            return new UploadedFile(
                $file['tmp_name'],
                $file['name'] ?? '',
                $file['type'] ?? null,
                $file['error'] ?? \UPLOAD_ERR_OK,
            );
        }

        // Multiple files: ['name' => ['a', 'b'], 'tmp_name' => ['/tmp/a', '/tmp/b'], ...]
        if (isset($file['tmp_name']) && \is_array($file['tmp_name'])) {
            $normalized = [];
            foreach ($file['tmp_name'] as $key => $tmpName) {
                $normalized[$key] = new UploadedFile(
                    $tmpName,
                    $file['name'][$key] ?? '',
                    $file['type'][$key] ?? null,
                    $file['error'][$key] ?? \UPLOAD_ERR_OK,
                );
            }

            return $normalized;
        }

        throw new \InvalidArgumentException('Invalid file data provided.');
    }
}
