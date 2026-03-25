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

namespace WpPack\Component\Filesystem;

use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;

/**
 * DI-injectable wrapper around WP_Filesystem_Base.
 */
final class Filesystem
{
    private readonly MimeTypesInterface $mimeTypes;

    public function __construct(
        private readonly \WP_Filesystem_Base $wpFilesystem,
        ?MimeTypesInterface $mimeTypes = null,
    ) {
        $this->mimeTypes = $mimeTypes ?? MimeTypes::getDefault();
    }

    public function read(string $path): ?string
    {
        $result = $this->wpFilesystem->get_contents($path);

        return $result === false ? null : $result;
    }

    public function write(string $path, string $content): bool
    {
        return $this->wpFilesystem->put_contents($path, $content);
    }

    public function append(string $path, string $content): bool
    {
        $existing = $this->wpFilesystem->get_contents($path);
        if (!\is_string($existing)) {
            $existing = '';
        }

        return $this->wpFilesystem->put_contents($path, $existing . $content);
    }

    public function exists(string $path): bool
    {
        return $this->wpFilesystem->exists($path);
    }

    public function isFile(string $path): bool
    {
        return $this->wpFilesystem->is_file($path);
    }

    public function isDirectory(string $path): bool
    {
        return $this->wpFilesystem->is_dir($path);
    }

    public function delete(string $path): bool
    {
        return $this->wpFilesystem->delete($path);
    }

    public function deleteDirectory(string $path): bool
    {
        return $this->wpFilesystem->rmdir($path, true);
    }

    public function copy(string $source, string $dest): bool
    {
        return $this->wpFilesystem->copy($source, $dest);
    }

    public function move(string $source, string $dest): bool
    {
        return $this->wpFilesystem->move($source, $dest);
    }

    public function mkdir(string $path, bool $recursive = false): bool
    {
        if ($recursive) {
            return $this->mkdirRecursiveWp($path);
        }

        return $this->wpFilesystem->mkdir($path);
    }

    public function chmod(string $path, int $mode): bool
    {
        return $this->wpFilesystem->chmod($path, $mode);
    }

    public function size(string $path): ?int
    {
        $result = $this->wpFilesystem->size($path);

        return $result === false ? null : $result;
    }

    public function lastModified(string $path): ?int
    {
        $result = $this->wpFilesystem->mtime($path);

        return $result === false ? null : $result;
    }

    public function mimeType(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        return $this->mimeTypes->guessMimeType($path);
    }

    /**
     * @return list<string> File names in the directory
     */
    public function files(string $directory): array
    {
        return $this->listByType($directory, 'f');
    }

    /**
     * @return list<string> Directory names in the directory
     */
    public function directories(string $directory): array
    {
        return $this->listByType($directory, 'd');
    }

    /**
     * @return list<string> All entry names in the directory
     */
    public function listContents(string $directory, bool $recursive = false): array
    {
        return $this->listAllWp($directory, $recursive);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function mkdirRecursiveWp(string $path): bool
    {
        if ($this->wpFilesystem->is_dir($path)) {
            return true;
        }

        $parent = dirname($path);
        if ($parent !== $path && !$this->wpFilesystem->is_dir($parent)) {
            $this->mkdirRecursiveWp($parent);
        }

        return $this->wpFilesystem->mkdir($path);
    }

    /**
     * @return list<string>
     */
    private function listByType(string $directory, string $type): array
    {
        $list = $this->wpFilesystem->dirlist($directory);
        if ($list === false) {
            return [];
        }

        $result = [];
        foreach ($list as $name => $info) {
            if ($info['type'] === $type) {
                $result[] = (string) $name;
            }
        }

        sort($result);

        return $result;
    }

    /**
     * @return list<string>
     */
    private function listAllWp(string $directory, bool $recursive): array
    {
        $list = $this->wpFilesystem->dirlist($directory, true, $recursive);
        if ($list === false) {
            return [];
        }

        $result = [];
        $this->flattenDirlist($list, '', $result);
        sort($result);

        return $result;
    }

    /**
     * @param array<string, array{type: string, files?: array<string, mixed>}> $list
     * @param list<string> $result
     */
    private function flattenDirlist(array $list, string $prefix, array &$result): void
    {
        foreach ($list as $name => $info) {
            $path = $prefix === '' ? (string) $name : $prefix . '/' . $name;
            $result[] = $path;

            if (isset($info['files'])) {
                $this->flattenDirlist($info['files'], $path, $result);
            }
        }
    }
}
