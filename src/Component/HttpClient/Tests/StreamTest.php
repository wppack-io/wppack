<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\Stream;

final class StreamTest extends TestCase
{
    #[Test]
    public function constructFromString(): void
    {
        $stream = new Stream('hello');

        self::assertSame('hello', (string) $stream);
        self::assertSame(5, $stream->getSize());
    }

    #[Test]
    public function constructFromResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'from resource');
        rewind($resource);

        $stream = new Stream($resource);

        self::assertSame('from resource', (string) $stream);
    }

    #[Test]
    public function constructFromInvalidTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Stream(123);
    }

    #[Test]
    public function emptyStream(): void
    {
        $stream = new Stream();

        self::assertSame('', (string) $stream);
        self::assertSame(0, $stream->getSize());
    }

    #[Test]
    public function read(): void
    {
        $stream = new Stream('hello world');

        self::assertSame('hello', $stream->read(5));
        self::assertSame(' world', $stream->read(6));
    }

    #[Test]
    public function write(): void
    {
        $stream = new Stream();

        $bytes = $stream->write('hello');

        self::assertSame(5, $bytes);
        self::assertSame('hello', (string) $stream);
    }

    #[Test]
    public function tell(): void
    {
        $stream = new Stream('hello');

        self::assertSame(0, $stream->tell());
        $stream->read(3);
        self::assertSame(3, $stream->tell());
    }

    #[Test]
    public function eof(): void
    {
        $stream = new Stream('hi');

        self::assertFalse($stream->eof());
        $stream->read(2);
        $stream->read(1);
        self::assertTrue($stream->eof());
    }

    #[Test]
    public function seekAndRewind(): void
    {
        $stream = new Stream('hello');

        $stream->seek(3);
        self::assertSame(3, $stream->tell());

        $stream->rewind();
        self::assertSame(0, $stream->tell());
    }

    #[Test]
    public function isReadableWritableSeekable(): void
    {
        $stream = new Stream('hello');

        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
    }

    #[Test]
    public function getContents(): void
    {
        $stream = new Stream('hello world');

        $stream->read(6);

        self::assertSame('world', $stream->getContents());
    }

    #[Test]
    public function getMetadata(): void
    {
        $stream = new Stream('hello');

        $meta = $stream->getMetadata();
        self::assertIsArray($meta);
        self::assertArrayHasKey('mode', $meta);

        self::assertTrue($stream->getMetadata('seekable'));
        self::assertNull($stream->getMetadata('nonexistent'));
    }

    #[Test]
    public function closeDetachesResource(): void
    {
        $stream = new Stream('hello');
        $stream->close();

        self::assertSame('', (string) $stream);
        self::assertNull($stream->getSize());
    }

    #[Test]
    public function detach(): void
    {
        $stream = new Stream('hello');
        $resource = $stream->detach();

        self::assertIsResource($resource);
        self::assertNull($stream->detach());
    }

    #[Test]
    public function toStringReturnsEmptyOnError(): void
    {
        $stream = new Stream('hello');
        $stream->close();

        self::assertSame('', (string) $stream);
    }

    #[Test]
    public function readOnDetachedStreamThrows(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->read(5);
    }

    #[Test]
    public function tellOnDetachedStreamThrows(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->tell();
    }

    #[Test]
    public function seekOnNonSeekableStreamThrows(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->seek(0);
    }

    #[Test]
    public function writeOnDetachedStreamThrows(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->write('data');
    }

    #[Test]
    public function getContentsOnDetachedStreamThrows(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->getContents();
    }

    #[Test]
    public function getMetadataOnDetachedStream(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('mode'));
    }
}
