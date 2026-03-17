<?php

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
            key: 'path/to/file.txt',
            size: 1024,
            lastModified: $lastModified,
            mimeType: 'text/plain',
        );

        self::assertSame('path/to/file.txt', $metadata->key);
        self::assertSame(1024, $metadata->size);
        self::assertSame($lastModified, $metadata->lastModified);
        self::assertSame('text/plain', $metadata->mimeType);
    }

    #[Test]
    public function constructsWithOnlyKey(): void
    {
        $metadata = new ObjectMetadata(key: 'file.txt');

        self::assertSame('file.txt', $metadata->key);
        self::assertNull($metadata->size);
        self::assertNull($metadata->lastModified);
        self::assertNull($metadata->mimeType);
    }
}
