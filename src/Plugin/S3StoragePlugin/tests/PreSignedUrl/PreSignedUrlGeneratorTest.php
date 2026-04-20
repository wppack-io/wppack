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

namespace WPPack\Plugin\S3StoragePlugin\Tests\PreSignedUrl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Storage\Adapter\StorageAdapterInterface;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlGenerator;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlResult;

#[CoversClass(PreSignedUrlGenerator::class)]
#[CoversClass(PreSignedUrlResult::class)]
final class PreSignedUrlGeneratorTest extends TestCase
{
    #[Test]
    public function resultCarriesUrlKeyAndExpiry(): void
    {
        $result = new PreSignedUrlResult(url: 'https://example.com/upload', key: '2024/06/xyz-file.jpg', expiresIn: 3600);

        self::assertSame('https://example.com/upload', $result->url);
        self::assertSame('2024/06/xyz-file.jpg', $result->key);
        self::assertSame(3600, $result->expiresIn);
    }

    #[Test]
    public function generateDelegatesToStorageWithDateBasedPath(): void
    {
        $capturedPath = null;
        $capturedExpiration = null;
        $capturedHeaders = null;

        $storage = $this->createMock(StorageAdapterInterface::class);
        $storage->expects(self::once())
            ->method('temporaryUploadUrl')
            ->willReturnCallback(function (string $path, \DateTimeImmutable $expiration, array $headers) use (&$capturedPath, &$capturedExpiration, &$capturedHeaders): string {
                $capturedPath = $path;
                $capturedExpiration = $expiration;
                $capturedHeaders = $headers;

                return 'https://bucket.s3.example.com/' . $path;
            });

        $generator = new PreSignedUrlGenerator($storage);
        $result = $generator->generate('my photo.jpg', 'image/jpeg', 1024, 1800);

        self::assertInstanceOf(PreSignedUrlResult::class, $result);
        self::assertSame(1800, $result->expiresIn);

        // Path: YYYY/MM/{16-hex}-sanitized-filename
        self::assertMatchesRegularExpression(
            '#^\d{4}/\d{2}/[a-f0-9]{16}-[\w.-]+$#',
            (string) $capturedPath,
        );
        self::assertStringContainsString('my-photo', (string) $capturedPath, 'filename is sanitized');
        self::assertStringEndsWith('.jpg', (string) $capturedPath);
        self::assertSame(['Content-Type' => 'image/jpeg', 'Content-Length' => 1024], $capturedHeaders);

        // Expiration ≈ now + expiresIn (±5 sec slop)
        $expectedTs = time() + 1800;
        self::assertEqualsWithDelta($expectedTs, $capturedExpiration->getTimestamp(), 5);
    }

    #[Test]
    public function generateStripsDirectoryTraversalFromFilename(): void
    {
        $capturedPath = null;
        $storage = $this->createMock(StorageAdapterInterface::class);
        $storage->method('temporaryUploadUrl')
            ->willReturnCallback(function (string $path) use (&$capturedPath): string {
                $capturedPath = $path;

                return 'https://x';
            });

        $generator = new PreSignedUrlGenerator($storage);
        $generator->generate('../../etc/passwd', 'text/plain', 10);

        self::assertStringNotContainsString('..', (string) $capturedPath, 'basename strips directory parts');
        self::assertStringNotContainsString('/etc/', (string) $capturedPath);
    }

    #[Test]
    public function generateUsesDefaultExpiryWhenNotSpecified(): void
    {
        $storage = $this->createMock(StorageAdapterInterface::class);
        $storage->method('temporaryUploadUrl')->willReturn('https://x');

        $result = (new PreSignedUrlGenerator($storage))->generate('a.png', 'image/png', 100);

        self::assertSame(3600, $result->expiresIn);
    }
}
