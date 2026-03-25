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

    #[Test]
    public function getSizeReturnsNullAfterDetach(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        self::assertNull($stream->getSize());
    }

    #[Test]
    public function eofReturnsTrueAfterDetach(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        self::assertTrue($stream->eof());
    }

    #[Test]
    public function isReadableReturnsFalseAfterDetach(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        self::assertFalse($stream->isReadable());
    }

    #[Test]
    public function isWritableReturnsFalseAfterDetach(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        self::assertFalse($stream->isWritable());
    }

    #[Test]
    public function isSeekableReturnsFalseAfterDetach(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        self::assertFalse($stream->isSeekable());
    }

    #[Test]
    public function closeOnAlreadyClosedStreamIsNoop(): void
    {
        $stream = new Stream('hello');
        $stream->close();
        $stream->close();

        self::assertSame('', (string) $stream);
    }

    #[Test]
    public function seekFromEnd(): void
    {
        $stream = new Stream('hello');

        $stream->seek(-2, \SEEK_END);
        self::assertSame('lo', $stream->getContents());
    }

    #[Test]
    public function readOnlyResourceIsNotWritable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'stream_test_');
        file_put_contents($file, 'test data');

        try {
            $resource = fopen($file, 'r');
            $stream = new Stream($resource);

            self::assertFalse($stream->isWritable());

            $this->expectException(\RuntimeException::class);
            $stream->write('data');
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function writeOnlyResourceIsNotReadable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'stream_test_');

        try {
            $resource = fopen($file, 'w');
            $stream = new Stream($resource);

            self::assertFalse($stream->isReadable());

            $this->expectException(\RuntimeException::class);
            $stream->read(1);
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function appendModeIsWritable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'stream_test_');

        try {
            $resource = fopen($file, 'a');
            $stream = new Stream($resource);

            self::assertTrue($stream->isWritable());
            $stream->close();
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function exclusiveModeIsWritable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'stream_test_');
        // Remove the file first since x mode fails if file exists
        unlink($file);

        try {
            $resource = fopen($file, 'x');
            $stream = new Stream($resource);

            self::assertTrue($stream->isWritable());
            $stream->close();
        } finally {
            @unlink($file);
        }
    }

    #[Test]
    public function cModeIsWritable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'stream_test_');

        try {
            $resource = fopen($file, 'c');
            $stream = new Stream($resource);

            self::assertTrue($stream->isWritable());
            $stream->close();
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function toStringReturnsContentsFromCurrentPosition(): void
    {
        $stream = new Stream('hello world');
        $stream->read(6); // read "hello "

        // __toString should rewind and return full contents
        self::assertSame('hello world', (string) $stream);
    }

    #[Test]
    public function toStringReturnsEmptyWhenNotSeekable(): void
    {
        $stream = new Stream('hello');
        $stream->close();

        // After close, stream is not seekable; __toString catches exception and returns ''
        self::assertSame('', (string) $stream);
    }

    #[Test]
    public function getContentsOnDetachedStreamThrowsRuntimeException(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stream is not readable');
        $stream->getContents();
    }

    #[Test]
    public function seekFromCurrent(): void
    {
        $stream = new Stream('hello world');

        $stream->read(5); // "hello"
        $stream->seek(1, \SEEK_CUR);
        self::assertSame('world', $stream->getContents());
    }

    #[Test]
    public function getMetadataReturnsSpecificKey(): void
    {
        $stream = new Stream('hello');

        $mode = $stream->getMetadata('mode');
        self::assertNotNull($mode);
        self::assertIsString($mode);
    }
}
