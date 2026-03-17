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

    public function getName(): string
    {
        return 'in-memory';
    }

    public function put(string $key, string $contents, array $metadata = []): void
    {
        $this->objects[$key] = ['contents' => $contents, 'metadata' => $metadata];
    }

    public function putStream(string $key, mixed $resource, array $metadata = []): void
    {
        $contents = stream_get_contents($resource);
        if ($contents === false) {
            $contents = '';
        }

        $this->put($key, $contents, $metadata);
    }

    public function get(string $key): string
    {
        if (!isset($this->objects[$key])) {
            throw new ObjectNotFoundException($key);
        }

        return $this->objects[$key]['contents'];
    }

    public function getStream(string $key): mixed
    {
        $contents = $this->get($key);

        $stream = fopen('php://memory', 'r+');
        \assert($stream !== false);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function delete(string $key): void
    {
        unset($this->objects[$key]);
    }

    public function deleteMultiple(array $keys): void
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function exists(string $key): bool
    {
        return isset($this->objects[$key]);
    }

    public function copy(string $sourceKey, string $destinationKey): void
    {
        if (!isset($this->objects[$sourceKey])) {
            throw new ObjectNotFoundException($sourceKey);
        }

        $this->objects[$destinationKey] = $this->objects[$sourceKey];
    }

    public function move(string $sourceKey, string $destinationKey): void
    {
        $this->copy($sourceKey, $destinationKey);
        $this->delete($sourceKey);
    }

    public function metadata(string $key): ObjectMetadata
    {
        if (!isset($this->objects[$key])) {
            throw new ObjectNotFoundException($key);
        }

        $object = $this->objects[$key];

        return new ObjectMetadata(
            key: $key,
            size: \strlen($object['contents']),
            lastModified: new \DateTimeImmutable(),
            mimeType: $object['metadata']['Content-Type'] ?? null,
        );
    }

    public function url(string $key): string
    {
        return 'memory://' . $key;
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expiration): string
    {
        throw new UnsupportedOperationException('temporaryUrl', $this->getName());
    }

    public function listContents(string $prefix = '', bool $recursive = true): iterable
    {
        foreach ($this->objects as $key => $object) {
            if ($prefix !== '' && !str_starts_with($key, $prefix)) {
                continue;
            }

            if (!$recursive && substr_count(substr($key, \strlen($prefix)), '/') > 0) {
                continue;
            }

            yield new ObjectMetadata(
                key: $key,
                size: \strlen($object['contents']),
                mimeType: $object['metadata']['Content-Type'] ?? null,
            );
        }
    }
}
