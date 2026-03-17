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
    abstract protected function doPut(string $key, string $contents, array $metadata = []): void;

    /**
     * @param resource $resource
     * @param array<string, string> $metadata
     */
    abstract protected function doPutStream(string $key, mixed $resource, array $metadata = []): void;

    abstract protected function doGet(string $key): string;

    /** @return resource */
    abstract protected function doGetStream(string $key): mixed;

    abstract protected function doDelete(string $key): void;

    /** @param list<string> $keys */
    protected function doDeleteMultiple(array $keys): void
    {
        foreach ($keys as $key) {
            $this->doDelete($key);
        }
    }

    abstract protected function doExists(string $key): bool;

    abstract protected function doCopy(string $sourceKey, string $destinationKey): void;

    protected function doMove(string $sourceKey, string $destinationKey): void
    {
        $this->copy($sourceKey, $destinationKey);
        $this->delete($sourceKey);
    }

    abstract protected function doMetadata(string $key): ObjectMetadata;

    abstract protected function doUrl(string $key): string;

    protected function doTemporaryUrl(string $key, \DateTimeInterface $expiration): string
    {
        throw new UnsupportedOperationException('temporaryUrl', $this->getName());
    }

    /** @return iterable<ObjectMetadata> */
    abstract protected function doListContents(string $prefix, bool $recursive): iterable;

    public function put(string $key, string $contents, array $metadata = []): void
    {
        $this->execute(function () use ($key, $contents, $metadata): void {
            $this->doPut($key, $contents, $metadata);
        });
    }

    public function putStream(string $key, mixed $resource, array $metadata = []): void
    {
        $this->execute(function () use ($key, $resource, $metadata): void {
            $this->doPutStream($key, $resource, $metadata);
        });
    }

    public function get(string $key): string
    {
        return $this->execute(fn(): string => $this->doGet($key));
    }

    public function getStream(string $key): mixed
    {
        return $this->execute(fn(): mixed => $this->doGetStream($key));
    }

    public function delete(string $key): void
    {
        $this->execute(function () use ($key): void {
            $this->doDelete($key);
        });
    }

    public function deleteMultiple(array $keys): void
    {
        $this->execute(function () use ($keys): void {
            $this->doDeleteMultiple($keys);
        });
    }

    public function exists(string $key): bool
    {
        return $this->execute(fn(): bool => $this->doExists($key));
    }

    public function copy(string $sourceKey, string $destinationKey): void
    {
        $this->execute(function () use ($sourceKey, $destinationKey): void {
            $this->doCopy($sourceKey, $destinationKey);
        });
    }

    public function move(string $sourceKey, string $destinationKey): void
    {
        $this->execute(function () use ($sourceKey, $destinationKey): void {
            $this->doMove($sourceKey, $destinationKey);
        });
    }

    public function metadata(string $key): ObjectMetadata
    {
        return $this->execute(fn(): ObjectMetadata => $this->doMetadata($key));
    }

    public function url(string $key): string
    {
        return $this->execute(fn(): string => $this->doUrl($key));
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expiration): string
    {
        return $this->execute(fn(): string => $this->doTemporaryUrl($key, $expiration));
    }

    public function listContents(string $prefix = '', bool $recursive = true): iterable
    {
        return $this->execute(fn(): iterable => $this->doListContents($prefix, $recursive));
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
