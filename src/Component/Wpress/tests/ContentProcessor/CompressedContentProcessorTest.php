<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Tests\ContentProcessor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Wpress\ContentProcessor\CompressedContentProcessor;
use WpPack\Component\Wpress\Exception\ArchiveException;

final class CompressedContentProcessorTest extends TestCase
{
    #[Test]
    public function gzipRoundTrip(): void
    {
        $processor = new CompressedContentProcessor('gzip');

        $original = 'Hello, World! This is a test of gzip compression.';
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function gzipCompressesData(): void
    {
        $processor = new CompressedContentProcessor('gzip');

        // Highly compressible data
        $original = str_repeat('AAAA', 10000);
        $encoded = $processor->encode($original);

        // Compressed + 4-byte size header should be smaller than original
        self::assertLessThan(\strlen($original), \strlen($encoded));
    }

    #[Test]
    public function emptyStringRoundTrip(): void
    {
        $processor = new CompressedContentProcessor('gzip');

        $encoded = $processor->encode('');
        $decoded = $processor->decode($encoded);

        self::assertSame('', $decoded);
    }

    #[Test]
    public function largeDataRoundTrip(): void
    {
        $processor = new CompressedContentProcessor('gzip');

        // Larger than one chunk
        $original = str_repeat('Test data for compression. ', 30000);
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function multipleChunksRoundTrip(): void
    {
        $processor = new CompressedContentProcessor('gzip');

        // 3+ chunks
        $original = random_bytes(524288 * 3 + 500);
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function exactChunkSizeRoundTrip(): void
    {
        $processor = new CompressedContentProcessor('gzip');

        $original = str_repeat('X', 524288);
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function bzip2RoundTripIfAvailable(): void
    {
        if (!\function_exists('bzcompress')) {
            self::markTestSkipped('bzip2 extension is not available.');
        }

        $processor = new CompressedContentProcessor('bzip2');

        $original = 'Hello, World! This is a test of bzip2 compression.';
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function invalidCompressionTypeThrows(): void
    {
        $this->expectException(ArchiveException::class);

        new CompressedContentProcessor('lz4');
    }

    #[Test]
    public function truncatedSizeHeaderThrows(): void
    {
        $processor = new CompressedContentProcessor('gzip');

        $this->expectException(ArchiveException::class);
        $processor->decode("\x00\x00");
    }

    #[Test]
    public function truncatedChunkDataThrows(): void
    {
        $processor = new CompressedContentProcessor('gzip');

        // Size header says 1000 bytes, but only 5 bytes follow
        $data = pack('N', 1000) . 'short';

        $this->expectException(ArchiveException::class);
        $processor->decode($data);
    }

    #[Test]
    public function encodedFormatHasSizeHeaders(): void
    {
        $processor = new CompressedContentProcessor('gzip');

        $original = 'Some data to compress';
        $encoded = $processor->encode($original);

        // First 4 bytes should be a big-endian size header
        $sizeData = unpack('N', substr($encoded, 0, 4));
        $chunkSize = $sizeData[1];

        // Size should match the rest of the data
        self::assertSame(\strlen($encoded) - 4, $chunkSize);
    }
}
