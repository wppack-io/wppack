<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Wpress\Tests\ContentProcessor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Wpress\ContentProcessor\EncryptedContentProcessor;
use WPPack\Component\Wpress\Exception\EncryptionException;

final class EncryptedContentProcessorTest extends TestCase
{
    #[Test]
    public function encodeDecodeRoundTrip(): void
    {
        $processor = new EncryptedContentProcessor('secret');

        $original = 'Hello, World! This is a test of encryption.';
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function encodedDataDiffersFromOriginal(): void
    {
        $processor = new EncryptedContentProcessor('secret');

        $original = 'Plain text data';
        $encoded = $processor->encode($original);

        self::assertNotSame($original, $encoded);
    }

    #[Test]
    public function emptyStringRoundTrip(): void
    {
        $processor = new EncryptedContentProcessor('password');

        $encoded = $processor->encode('');
        $decoded = $processor->decode($encoded);

        self::assertSame('', $decoded);
    }

    #[Test]
    public function largeDataRoundTrip(): void
    {
        $processor = new EncryptedContentProcessor('my-password');

        // Create data larger than one chunk (512KB)
        $original = str_repeat('A', 600000);
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function multipleChunksRoundTrip(): void
    {
        $processor = new EncryptedContentProcessor('test123');

        // Create data spanning 3 chunks
        $original = random_bytes(524288 * 3 + 1000);
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function exactChunkSizeRoundTrip(): void
    {
        $processor = new EncryptedContentProcessor('pw');

        // Exactly one chunk
        $original = str_repeat('B', 524288);
        $encoded = $processor->encode($original);
        $decoded = $processor->decode($encoded);

        self::assertSame($original, $decoded);
    }

    #[Test]
    public function wrongPasswordCannotRecoverOriginal(): void
    {
        // The .wpress format (AI1WM) is specified as AES-256-CBC with
        // PKCS#7 padding (see docs/components/wpress/README.md). With
        // that scheme + a wrong key, PKCS#7 validation detects the bad
        // plaintext roughly 99.6% of the time — but in ~0.4% of runs the
        // decrypted garbage happens to end with bytes that look like
        // valid PKCS#7 padding, so openssl_decrypt returns truncated
        // garbage instead of false. We cannot eliminate this without
        // switching to an authenticated cipher (GCM / +HMAC), which
        // would break .wpress interoperability.
        //
        // The cryptographic guarantee we actually have is: a wrong key
        // cannot recover the original plaintext. Assert exactly that.
        $encoder = new EncryptedContentProcessor('correct-password');
        $decoder = new EncryptedContentProcessor('wrong-password');

        $encoded = $encoder->encode('secret data');

        try {
            $decoded = $decoder->decode($encoded);
            self::assertNotSame('secret data', $decoded);
        } catch (EncryptionException) {
            // PKCS#7 padding validation caught the wrong key — also a
            // valid, stronger outcome.
            self::assertTrue(true);
        }
    }

    #[Test]
    public function truncatedDataFails(): void
    {
        $processor = new EncryptedContentProcessor('pw');

        $this->expectException(EncryptionException::class);
        $processor->decode('short');
    }

    #[Test]
    public function differentPasswordsProduceDifferentOutput(): void
    {
        $processor1 = new EncryptedContentProcessor('password1');
        $processor2 = new EncryptedContentProcessor('password2');

        $data = 'Same plaintext';

        $encoded1 = $processor1->encode($data);
        $encoded2 = $processor2->encode($data);

        // Different passwords should produce different encrypted outputs
        // (also different IVs, but even with same IV the output would differ)
        self::assertNotSame($encoded1, $encoded2);
    }
}
