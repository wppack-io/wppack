<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Adapter;

use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;
use WpPack\Component\Storage\ObjectMetadata;

final class LocalStorageAdapter extends AbstractStorageAdapter
{
    private readonly string $rootDir;

    public function __construct(
        string $rootDir,
        private readonly ?string $publicUrl = null,
    ) {
        $this->rootDir = rtrim($rootDir, '/');
    }

    public function getName(): string
    {
        return 'local';
    }

    protected function doWrite(string $key, string $contents, array $metadata = []): void
    {
        $path = $this->fullPath($key);
        $this->ensureDirectory(\dirname($path));

        file_put_contents($path, $contents);
    }

    protected function doWriteStream(string $key, mixed $resource, array $metadata = []): void
    {
        $path = $this->fullPath($key);
        $this->ensureDirectory(\dirname($path));

        $dest = fopen($path, 'w');
        \assert($dest !== false);
        stream_copy_to_stream($resource, $dest);
        fclose($dest);
    }

    protected function doRead(string $key): string
    {
        $path = $this->fullPath($key);

        if (!is_file($path)) {
            throw new ObjectNotFoundException($key);
        }

        $contents = file_get_contents($path);
        \assert($contents !== false);

        return $contents;
    }

    protected function doReadStream(string $key): mixed
    {
        $path = $this->fullPath($key);

        if (!is_file($path)) {
            throw new ObjectNotFoundException($key);
        }

        $stream = fopen($path, 'r');
        \assert($stream !== false);

        return $stream;
    }

    protected function doDelete(string $key): void
    {
        $path = $this->fullPath($key);

        if (is_file($path)) {
            unlink($path);
        }
    }

    protected function doExists(string $key): bool
    {
        return is_file($this->fullPath($key));
    }

    protected function doCopy(string $sourceKey, string $destinationKey): void
    {
        $sourcePath = $this->fullPath($sourceKey);

        if (!is_file($sourcePath)) {
            throw new ObjectNotFoundException($sourceKey);
        }

        $destPath = $this->fullPath($destinationKey);
        $this->ensureDirectory(\dirname($destPath));

        copy($sourcePath, $destPath);
    }

    protected function doMove(string $sourceKey, string $destinationKey): void
    {
        $sourcePath = $this->fullPath($sourceKey);

        if (!is_file($sourcePath)) {
            throw new ObjectNotFoundException($sourceKey);
        }

        $destPath = $this->fullPath($destinationKey);
        $this->ensureDirectory(\dirname($destPath));

        rename($sourcePath, $destPath);
    }

    protected function doMetadata(string $key): ObjectMetadata
    {
        $path = $this->fullPath($key);

        if (!is_file($path)) {
            throw new ObjectNotFoundException($key);
        }

        $mtime = filemtime($path);

        return new ObjectMetadata(
            key: $key,
            size: (int) filesize($path),
            lastModified: $mtime !== false ? (new \DateTimeImmutable())->setTimestamp($mtime) : null,
            mimeType: mime_content_type($path) ?: null,
        );
    }

    protected function doPublicUrl(string $key): string
    {
        if ($this->publicUrl !== null) {
            return rtrim($this->publicUrl, '/') . '/' . ltrim($key, '/');
        }

        return $this->fullPath($key);
    }

    protected function doTemporaryUrl(string $key, \DateTimeInterface $expiration): string
    {
        throw new UnsupportedOperationException('temporaryUrl', $this->getName());
    }

    protected function doListContents(string $prefix, bool $recursive): iterable
    {
        $dir = $this->rootDir;
        if ($prefix !== '') {
            $dir .= '/' . rtrim($prefix, '/');
        }

        if (!is_dir($dir)) {
            return;
        }

        $flags = \RecursiveDirectoryIterator::SKIP_DOTS;
        $dirIterator = new \RecursiveDirectoryIterator($dir, $flags);

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator($dirIterator);
        } else {
            $iterator = $dirIterator;
        }

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $fullPath = $file->getPathname();
            $key = substr($fullPath, \strlen($this->rootDir) + 1);

            $mtime = $file->getMTime();

            yield new ObjectMetadata(
                key: $key,
                size: (int) $file->getSize(),
                lastModified: (new \DateTimeImmutable())->setTimestamp($mtime),
            );
        }
    }

    private function fullPath(string $key): string
    {
        return $this->rootDir . '/' . ltrim($key, '/');
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
