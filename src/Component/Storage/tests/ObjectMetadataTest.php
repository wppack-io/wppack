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

namespace WpPack\Component\Storage\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\ObjectMetadata;

#[CoversClass(ObjectMetadata::class)]
final class ObjectMetadataTest extends TestCase
{
    #[Test]
    public function constructsWithAllProperties(): void
    {
        $lastModified = new \DateTimeImmutable('2024-01-01 12:00:00');

        $metadata = new ObjectMetadata(
            path: 'path/to/file.txt',
            size: 1024,
            lastModified: $lastModified,
            mimeType: 'text/plain',
        );

        self::assertSame('path/to/file.txt', $metadata->path);
        self::assertSame(1024, $metadata->size);
        self::assertSame($lastModified, $metadata->lastModified);
        self::assertSame('text/plain', $metadata->mimeType);
        self::assertFalse($metadata->isDirectory);
    }

    #[Test]
    public function constructsWithOnlyPath(): void
    {
        $metadata = new ObjectMetadata(path: 'file.txt');

        self::assertSame('file.txt', $metadata->path);
        self::assertNull($metadata->size);
        self::assertNull($metadata->lastModified);
        self::assertNull($metadata->mimeType);
        self::assertFalse($metadata->isDirectory);
    }

    #[Test]
    public function constructsWithIsDirectoryTrue(): void
    {
        $metadata = new ObjectMetadata(
            path: 'some/directory',
            isDirectory: true,
        );

        self::assertSame('some/directory', $metadata->path);
        self::assertTrue($metadata->isDirectory);
        self::assertNull($metadata->size);
        self::assertNull($metadata->lastModified);
        self::assertNull($metadata->mimeType);
    }
}
