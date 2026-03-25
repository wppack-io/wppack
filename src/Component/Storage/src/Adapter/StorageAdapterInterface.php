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

use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;
use WpPack\Component\Storage\ObjectMetadata;
use WpPack\Component\Storage\Visibility;

interface StorageAdapterInterface
{
    public function getName(): string;

    /** @param array<string, string> $metadata */
    public function write(string $path, string $contents, array $metadata = []): void;

    /**
     * @param resource $resource
     * @param array<string, string> $metadata
     */
    public function writeStream(string $path, mixed $resource, array $metadata = []): void;

    /** @throws ObjectNotFoundException */
    public function read(string $path): string;

    /**
     * @return resource
     * @throws ObjectNotFoundException
     */
    public function readStream(string $path): mixed;

    public function delete(string $path): void;

    /** @param list<string> $paths */
    public function deleteMultiple(array $paths): void;

    public function fileExists(string $path): bool;

    public function createDirectory(string $path): void;

    public function deleteDirectory(string $path): void;

    public function directoryExists(string $path): bool;

    public function copy(string $source, string $destination): void;

    public function move(string $source, string $destination): void;

    /** @throws ObjectNotFoundException */
    public function metadata(string $path): ObjectMetadata;

    public function publicUrl(string $path): string;

    /** @throws UnsupportedOperationException */
    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string;

    /**
     * @param array<string, string|int> $options
     * @throws UnsupportedOperationException
     */
    public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string;

    /** @throws UnsupportedOperationException */
    public function setVisibility(string $path, Visibility $visibility): void;

    /** @return iterable<ObjectMetadata> */
    public function listContents(string $path = '', bool $deep = false): iterable;
}
