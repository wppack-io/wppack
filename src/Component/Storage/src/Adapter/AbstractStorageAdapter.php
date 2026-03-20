<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Adapter;

use WpPack\Component\Storage\Exception\StorageException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;
use WpPack\Component\Storage\ObjectMetadata;

abstract class AbstractStorageAdapter implements StorageAdapterInterface
{
    abstract public function getName(): string;

    /** @param array<string, string> $metadata */
    abstract protected function doWrite(string $path, string $contents, array $metadata = []): void;

    /**
     * @param resource $resource
     * @param array<string, string> $metadata
     */
    abstract protected function doWriteStream(string $path, mixed $resource, array $metadata = []): void;

    abstract protected function doRead(string $path): string;

    /** @return resource */
    abstract protected function doReadStream(string $path): mixed;

    abstract protected function doDelete(string $path): void;

    /** @param list<string> $paths */
    protected function doDeleteMultiple(array $paths): void
    {
        foreach ($paths as $path) {
            $this->doDelete($path);
        }
    }

    abstract protected function doFileExists(string $path): bool;

    abstract protected function doCreateDirectory(string $path): void;

    abstract protected function doDeleteDirectory(string $path): void;

    abstract protected function doDirectoryExists(string $path): bool;

    abstract protected function doCopy(string $source, string $destination): void;

    protected function doMove(string $source, string $destination): void
    {
        $this->copy($source, $destination);
        $this->delete($source);
    }

    abstract protected function doMetadata(string $path): ObjectMetadata;

    abstract protected function doPublicUrl(string $path): string;

    protected function doTemporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        throw new UnsupportedOperationException('temporaryUrl', $this->getName());
    }

    /** @return iterable<ObjectMetadata> */
    abstract protected function doListContents(string $path, bool $deep): iterable;

    public function write(string $path, string $contents, array $metadata = []): void
    {
        $this->execute(function () use ($path, $contents, $metadata): void {
            $this->doWrite($path, $contents, $metadata);
        });
    }

    public function writeStream(string $path, mixed $resource, array $metadata = []): void
    {
        $this->execute(function () use ($path, $resource, $metadata): void {
            $this->doWriteStream($path, $resource, $metadata);
        });
    }

    public function read(string $path): string
    {
        return $this->execute(fn(): string => $this->doRead($path));
    }

    public function readStream(string $path): mixed
    {
        return $this->execute(fn(): mixed => $this->doReadStream($path));
    }

    public function delete(string $path): void
    {
        $this->execute(function () use ($path): void {
            $this->doDelete($path);
        });
    }

    public function deleteMultiple(array $paths): void
    {
        $this->execute(function () use ($paths): void {
            $this->doDeleteMultiple($paths);
        });
    }

    public function fileExists(string $path): bool
    {
        return $this->execute(fn(): bool => $this->doFileExists($path));
    }

    public function createDirectory(string $path): void
    {
        $this->execute(function () use ($path): void {
            $this->doCreateDirectory($path);
        });
    }

    public function deleteDirectory(string $path): void
    {
        $this->execute(function () use ($path): void {
            $this->doDeleteDirectory($path);
        });
    }

    public function directoryExists(string $path): bool
    {
        return $this->execute(fn(): bool => $this->doDirectoryExists($path));
    }

    public function copy(string $source, string $destination): void
    {
        $this->execute(function () use ($source, $destination): void {
            $this->doCopy($source, $destination);
        });
    }

    public function move(string $source, string $destination): void
    {
        $this->execute(function () use ($source, $destination): void {
            $this->doMove($source, $destination);
        });
    }

    public function metadata(string $path): ObjectMetadata
    {
        return $this->execute(fn(): ObjectMetadata => $this->doMetadata($path));
    }

    public function publicUrl(string $path): string
    {
        return $this->execute(fn(): string => $this->doPublicUrl($path));
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        return $this->execute(fn(): string => $this->doTemporaryUrl($path, $expiration));
    }

    public function listContents(string $path = '', bool $deep = false): iterable
    {
        return $this->execute(fn(): iterable => $this->doListContents($path, $deep));
    }

    /**
     * @template T
     * @param \Closure(): T $operation
     * @return T
     */
    protected function execute(\Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (StorageException $e) {
            throw $e;
        } catch (UnsupportedOperationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }
}
