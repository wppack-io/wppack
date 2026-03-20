<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Test;

use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;
use WpPack\Component\Storage\ObjectMetadata;

final class InMemoryStorageAdapter implements StorageAdapterInterface
{
    /** @var array<string, array{contents: string, metadata: array<string, string>}> */
    private array $objects = [];

    /** @var array<string, true> */
    private array $directories = [];

    public function getName(): string
    {
        return 'in-memory';
    }

    public function write(string $path, string $contents, array $metadata = []): void
    {
        $this->objects[$path] = ['contents' => $contents, 'metadata' => $metadata];
    }

    public function writeStream(string $path, mixed $resource, array $metadata = []): void
    {
        $contents = stream_get_contents($resource);
        if ($contents === false) {
            $contents = '';
        }

        $this->write($path, $contents, $metadata);
    }

    public function read(string $path): string
    {
        if (!isset($this->objects[$path])) {
            throw new ObjectNotFoundException($path);
        }

        return $this->objects[$path]['contents'];
    }

    public function readStream(string $path): mixed
    {
        $contents = $this->read($path);

        $stream = fopen('php://memory', 'r+');
        \assert($stream !== false);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        unset($this->objects[$path]);
    }

    public function deleteMultiple(array $paths): void
    {
        foreach ($paths as $path) {
            $this->delete($path);
        }
    }

    public function fileExists(string $path): bool
    {
        return isset($this->objects[$path]);
    }

    public function createDirectory(string $path): void
    {
        $this->directories[rtrim($path, '/')] = true;
    }

    public function deleteDirectory(string $path): void
    {
        $normalizedPath = rtrim($path, '/');

        unset($this->directories[$normalizedPath]);

        $prefix = $normalizedPath . '/';
        foreach (array_keys($this->objects) as $objectPath) {
            if (str_starts_with($objectPath, $prefix)) {
                unset($this->objects[$objectPath]);
            }
        }
    }

    public function directoryExists(string $path): bool
    {
        $normalizedPath = rtrim($path, '/');

        if (isset($this->directories[$normalizedPath])) {
            return true;
        }

        $prefix = $normalizedPath . '/';
        foreach (array_keys($this->objects) as $objectPath) {
            if (str_starts_with($objectPath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function copy(string $source, string $destination): void
    {
        if (!isset($this->objects[$source])) {
            throw new ObjectNotFoundException($source);
        }

        $this->objects[$destination] = $this->objects[$source];
    }

    public function move(string $source, string $destination): void
    {
        $this->copy($source, $destination);
        $this->delete($source);
    }

    public function metadata(string $path): ObjectMetadata
    {
        if (!isset($this->objects[$path])) {
            throw new ObjectNotFoundException($path);
        }

        $object = $this->objects[$path];

        return new ObjectMetadata(
            path: $path,
            size: \strlen($object['contents']),
            lastModified: new \DateTimeImmutable(),
            mimeType: $object['metadata']['Content-Type'] ?? null,
        );
    }

    public function publicUrl(string $path): string
    {
        return 'memory://' . $path;
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        throw new UnsupportedOperationException('temporaryUrl', $this->getName());
    }

    public function listContents(string $path = '', bool $deep = false): iterable
    {
        $prefix = $path !== '' ? rtrim($path, '/') . '/' : '';
        $yieldedDirectories = [];

        foreach ($this->objects as $objectPath => $object) {
            if ($prefix !== '' && !str_starts_with($objectPath, $prefix)) {
                continue;
            }

            $relativePath = substr($objectPath, \strlen($prefix));

            if (!$deep) {
                $slashPos = strpos($relativePath, '/');
                if ($slashPos !== false) {
                    // This object is in a subdirectory; yield the directory entry instead
                    $dirName = substr($relativePath, 0, $slashPos);
                    $dirPath = $prefix . $dirName;
                    if (!isset($yieldedDirectories[$dirPath])) {
                        $yieldedDirectories[$dirPath] = true;
                        yield new ObjectMetadata(
                            path: $dirPath,
                            isDirectory: true,
                        );
                    }
                    continue;
                }
            }

            yield new ObjectMetadata(
                path: $objectPath,
                size: \strlen($object['contents']),
                mimeType: $object['metadata']['Content-Type'] ?? null,
            );
        }

        // Yield explicitly created directories that match the prefix
        foreach (array_keys($this->directories) as $dirPath) {
            if (isset($yieldedDirectories[$dirPath])) {
                continue;
            }

            if ($prefix !== '' && !str_starts_with($dirPath . '/', $prefix)) {
                continue;
            }

            if (!$deep) {
                $relativeDirPath = substr($dirPath, \strlen($prefix));
                if (str_contains($relativeDirPath, '/')) {
                    continue;
                }
            }

            // Skip if the directory is the path itself
            if ($dirPath === rtrim($path, '/') && $path !== '') {
                continue;
            }

            $yieldedDirectories[$dirPath] = true;
            yield new ObjectMetadata(
                path: $dirPath,
                isDirectory: true,
            );
        }
    }
}
