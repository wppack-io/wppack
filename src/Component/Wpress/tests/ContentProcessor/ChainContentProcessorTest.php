<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Tests\ContentProcessor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Wpress\ContentProcessor\ChainContentProcessor;
use WpPack\Component\Wpress\Exception\EncryptionException;

final class ChainContentProcessorTest extends TestCase
{
    #[Test]
    public function encodeDecodeRoundTrip(): void
    {
        $processor = new ChainContentProcessor('secret', 'gzip');

        $original = 'Hello, World! Compressed and encrypted.';
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function encodedDataDiffersFromOriginal(): void
    {
        $processor = new ChainContentProcessor('secret', 'gzip');

        $original = 'Plain text data';
        $encoded = $processor->encode($original);

        self::assertNotSame($original, $encoded);
    }

    #[Test]
    public function emptyStringRoundTrip(): void
    {
        $processor = new ChainContentProcessor('password', 'gzip');

        $encoded = $processor->encode('');
        $decoded = $processor->decode($encoded);

        self::assertSame('', $decoded);
    }

    #[Test]
    public function largeDataRoundTrip(): void
    {
        $processor = new ChainContentProcessor('my-password', 'gzip');

        // Larger than one chunk
        $original = str_repeat('Large data block. ', 50000);
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function multipleChunksRoundTrip(): void
    {
        $processor = new ChainContentProcessor('test123', 'gzip');

        // 3+ chunks of random data
        $original = random_bytes(524288 * 3 + 1000);
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function exactChunkSizeRoundTrip(): void
    {
        $processor = new ChainContentProcessor('pw', 'gzip');

        $original = str_repeat('C', 524288);
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function wrongPasswordFails(): void
    {
        $encoder = new ChainContentProcessor('correct', 'gzip');
        $decoder = new ChainContentProcessor('wrong', 'gzip');

        $encoded = $encoder->encode('secret data');

        $this->expectException(EncryptionException::class);
        $decoder->decode($encoded);
    }

    #[Test]
    public function bzip2RoundTripIfAvailable(): void
    {
        if (!\function_exists('bzcompress')) {
            self::markTestSkipped('bzip2 extension is not available.');
        }

        $processor = new ChainContentProcessor('secret', 'bzip2');

        $original = 'Hello, World! Compressed with bzip2 and encrypted.';
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function encodedFormatHasSizeHeaders(): void
    {
        $processor = new ChainContentProcessor('pw', 'gzip');

        $original = 'Some data';
        $encoded = $processor->encode($original);

        // First 4 bytes should be a big-endian size header
        $sizeData = unpack('N', substr($encoded, 0, 4));
        $chunkSize = $sizeData[1];

        // Size should be: len(IV) + len(encrypted_compressed)
        // The remaining data after the 4-byte header should match this size
        self::assertSame(\strlen($encoded) - 4, $chunkSize);
    }

    #[Test]
    public function chunkFormatIsCorrect(): void
    {
        $processor = new ChainContentProcessor('pw', 'gzip');

        $original = 'Test chunk format';
        $encoded = $processor->encode($original);

        // Read size header
        $sizeData = unpack('N', substr($encoded, 0, 4));
        $chunkSize = $sizeData[1];

        // After size header, first 16 bytes should be IV
        $ivAndEncrypted = substr($encoded, 4, $chunkSize);
        self::assertSame($chunkSize, \strlen($ivAndEncrypted));

        // IV is 16 bytes
        self::assertGreaterThan(16, $chunkSize);
    }
}
