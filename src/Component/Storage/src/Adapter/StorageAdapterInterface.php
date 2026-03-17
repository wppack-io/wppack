<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Adapter;

use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;
use WpPack\Component\Storage\ObjectMetadata;

interface StorageAdapterInterface
{
    public function getName(): string;

    /** @param array<string, string> $metadata */
    public function put(string $key, string $contents, array $metadata = []): void;

    /**
     * @param resource $resource
     * @param array<string, string> $metadata
     */
    public function putStream(string $key, mixed $resource, array $metadata = []): void;

    /** @throws ObjectNotFoundException */
    public function get(string $key): string;

    /**
     * @return resource
     * @throws ObjectNotFoundException
     */
    public function getStream(string $key): mixed;

    public function delete(string $key): void;

    /** @param list<string> $keys */
    public function deleteMultiple(array $keys): void;

    public function exists(string $key): bool;

    public function copy(string $sourceKey, string $destinationKey): void;

    public function move(string $sourceKey, string $destinationKey): void;

    /** @throws ObjectNotFoundException */
    public function metadata(string $key): ObjectMetadata;

    public function url(string $key): string;

    /** @throws UnsupportedOperationException */
    public function temporaryUrl(string $key, \DateTimeInterface $expiration): string;

    /** @return iterable<ObjectMetadata> */
    public function listContents(string $prefix = '', bool $recursive = true): iterable;
}
