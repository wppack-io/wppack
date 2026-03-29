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

namespace WpPack\Component\Storage\Adapter;

use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;
use WpPack\Component\Storage\ObjectMetadata;
use WpPack\Component\Storage\Visibility;

final class LocalStorageAdapter extends AbstractStorageAdapter
{
    private readonly string $rootDir;
    private readonly MimeTypesInterface $mimeTypes;

    public function __construct(
        string $rootDir,
        private readonly ?string $publicUrl = null,
        ?MimeTypesInterface $mimeTypes = null,
    ) {
        $this->rootDir = rtrim($rootDir, '/');
        $this->mimeTypes = $mimeTypes ?? MimeTypes::getDefault();
    }

    public function getName(): string
    {
        return 'local';
    }

    protected function doWrite(string $path, string $contents, array $metadata = []): void
    {
        $fullPath = $this->fullPath($path);
        $this->ensureDirectory(\dirname($fullPath));

        file_put_contents($fullPath, $contents);
    }

    protected function doWriteStream(string $path, mixed $resource, array $metadata = []): void
    {
        $fullPath = $this->fullPath($path);
        $this->ensureDirectory(\dirname($fullPath));

        $dest = fopen($fullPath, 'w');

        if ($dest === false) {
            throw new \RuntimeException(\sprintf('Failed to open file for writing: %s', $fullPath));
        }

        stream_copy_to_stream($resource, $dest);
        fclose($dest);
    }

    protected function doRead(string $path): string
    {
        $fullPath = $this->fullPath($path);

        if (!is_file($fullPath)) {
            throw new ObjectNotFoundException($path);
        }

        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Failed to read file: %s', $fullPath));
        }

        return $contents;
    }

    protected function doReadStream(string $path): mixed
    {
        $fullPath = $this->fullPath($path);

        if (!is_file($fullPath)) {
            throw new ObjectNotFoundException($path);
        }

        $stream = fopen($fullPath, 'r');

        if ($stream === false) {
            throw new \RuntimeException(\sprintf('Failed to open file for reading: %s', $fullPath));
        }

        return $stream;
    }

    protected function doDelete(string $path): void
    {
        $fullPath = $this->fullPath($path);

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    protected function doFileExists(string $path): bool
    {
        return is_file($this->fullPath($path));
    }

    protected function doCreateDirectory(string $path): void
    {
        $fullPath = $this->fullPath($path);

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }
    }

    protected function doDeleteDirectory(string $path): void
    {
        $fullPath = $this->fullPath($path);

        if (!is_dir($fullPath)) {
            return;
        }

        $flags = \RecursiveDirectoryIterator::SKIP_DOTS;
        $dirIterator = new \RecursiveDirectoryIterator($fullPath, $flags);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::CHILD_FIRST);

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($fullPath);
    }

    protected function doDirectoryExists(string $path): bool
    {
        return is_dir($this->fullPath($path));
    }

    protected function doCopy(string $source, string $destination): void
    {
        $sourcePath = $this->fullPath($source);

        if (!is_file($sourcePath)) {
            throw new ObjectNotFoundException($source);
        }

        $destPath = $this->fullPath($destination);
        $this->ensureDirectory(\dirname($destPath));

        copy($sourcePath, $destPath);
    }

    protected function doMove(string $source, string $destination): void
    {
        $sourcePath = $this->fullPath($source);

        if (!is_file($sourcePath)) {
            throw new ObjectNotFoundException($source);
        }

        $destPath = $this->fullPath($destination);
        $this->ensureDirectory(\dirname($destPath));

        rename($sourcePath, $destPath);
    }

    protected function doMetadata(string $path): ObjectMetadata
    {
        $fullPath = $this->fullPath($path);

        if (!is_file($fullPath)) {
            throw new ObjectNotFoundException($path);
        }

        $mtime = filemtime($fullPath);

        return new ObjectMetadata(
            path: $path,
            size: (int) filesize($fullPath),
            lastModified: $mtime !== false ? (new \DateTimeImmutable())->setTimestamp($mtime) : null,
            mimeType: $this->mimeTypes->guessMimeType($fullPath),
        );
    }

    protected function doPublicUrl(string $path): string
    {
        if ($this->publicUrl !== null) {
            return rtrim($this->publicUrl, '/') . '/' . ltrim($path, '/');
        }

        return $this->fullPath($path);
    }

    protected function doTemporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        throw new UnsupportedOperationException('temporaryUrl', $this->getName());
    }

    protected function doSetVisibility(string $path, Visibility $visibility): void
    {
        // No-op: local filesystem does not support ACL-based visibility
    }

    protected function doListContents(string $path, bool $deep): iterable
    {
        $dir = $this->rootDir;
        if ($path !== '') {
            $dir .= '/' . rtrim($path, '/');
        }

        if (!is_dir($dir)) {
            return;
        }

        $flags = \RecursiveDirectoryIterator::SKIP_DOTS;
        $dirIterator = new \RecursiveDirectoryIterator($dir, $flags);

        if ($deep) {
            $iterator = new \RecursiveIteratorIterator($dirIterator);
        } else {
            $iterator = $dirIterator;
        }

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $itemFullPath = $file->getPathname();
            $relativePath = substr($itemFullPath, \strlen($this->rootDir) + 1);

            if ($file->isDir()) {
                if (!$deep) {
                    yield new ObjectMetadata(
                        path: $relativePath,
                        isDirectory: true,
                    );
                }

                continue;
            }

            $mtime = $file->getMTime();

            yield new ObjectMetadata(
                path: $relativePath,
                size: (int) $file->getSize(),
                lastModified: (new \DateTimeImmutable())->setTimestamp($mtime),
            );
        }
    }

    private function fullPath(string $path): string
    {
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Invalid storage path.');
        }

        return $this->rootDir . '/' . ltrim($path, '/');
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
