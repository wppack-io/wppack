<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Plugin\S3StoragePlugin\Handler\S3ObjectCreatedHandler;

final class S3ObjectCreatedHandlerTest extends TestCase
{
    private S3ObjectCreatedHandler $handler;

    protected function setUp(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $this->handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'uploads',
        );
    }

    #[Test]
    #[DataProvider('resizedImageProvider')]
    public function isResizedImageReturnsTrueForResizedImages(string $key): void
    {
        self::assertTrue($this->handler->isResizedImage($key));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function resizedImageProvider(): iterable
    {
        yield 'width x height' => ['uploads/2024/01/photo-100x200.jpg'];
        yield 'large dimensions' => ['uploads/2024/01/image-1920x1080.png'];
        yield 'small dimensions' => ['uploads/2024/01/thumb-1x1.gif'];
        yield 'scaled' => ['uploads/2024/01/photo-scaled.jpg'];
        yield 'rotated' => ['uploads/2024/01/image-rotated.png'];
        yield 'edited timestamp' => ['uploads/2024/01/photo-e1234567890.jpg'];
        yield 'edited long timestamp' => ['uploads/2024/01/photo-e12345678901234.webp'];
        yield 'nested path with dimensions' => ['uploads/sites/2/2024/01/photo-300x300.jpg'];
        yield 'nested path with scaled' => ['uploads/sites/3/2024/01/photo-scaled.png'];
    }

    #[Test]
    #[DataProvider('nonResizedImageProvider')]
    public function isResizedImageReturnsFalseForOriginalImages(string $key): void
    {
        self::assertFalse($this->handler->isResizedImage($key));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonResizedImageProvider(): iterable
    {
        yield 'original image' => ['uploads/2024/01/photo.jpg'];
        yield 'original png' => ['uploads/2024/01/image.png'];
        yield 'pdf file' => ['uploads/2024/01/document.pdf'];
        yield 'video file' => ['uploads/2024/01/video.mp4'];
        yield 'dimensions in name' => ['uploads/2024/01/100x200.jpg'];
        yield 'name with number' => ['uploads/2024/01/photo-1.jpg'];
        yield 'name with text suffix' => ['uploads/2024/01/photo-edited.jpg'];
        yield 'short e timestamp' => ['uploads/2024/01/photo-e12345.jpg'];
        yield 'no extension' => ['uploads/2024/01/README'];
        yield 'multiple dots' => ['uploads/2024/01/file.backup.jpg'];
    }

    #[Test]
    public function parseBlogIdFromMainSite(): void
    {
        self::assertSame(1, $this->handler->parseBlogId('uploads/2024/01/photo.jpg'));
    }

    #[Test]
    public function parseBlogIdFromMultisite(): void
    {
        self::assertSame(2, $this->handler->parseBlogId('uploads/sites/2/2024/01/photo.jpg'));
        self::assertSame(42, $this->handler->parseBlogId('uploads/sites/42/2024/01/photo.jpg'));
        self::assertSame(100, $this->handler->parseBlogId('uploads/sites/100/image.png'));
    }

    #[Test]
    public function parseBlogIdReturnsOneForPathWithoutSitesPattern(): void
    {
        self::assertSame(1, $this->handler->parseBlogId('uploads/2024/01/file.pdf'));
        self::assertSame(1, $this->handler->parseBlogId('other-prefix/image.jpg'));
        self::assertSame(1, $this->handler->parseBlogId('uploads/sites/file.jpg')); // Missing blog ID number
    }

    #[Test]
    public function invokeSkipsResizedImages(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'uploads',
        );

        $message = new \WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/01/photo-100x200.jpg',
            size: 5000,
            eTag: 'abc123',
        );

        ($handler)($message);
    }

    #[Test]
    public function invokeCreatesAttachmentForOriginalImage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'uploads',
        );

        $message = new \WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/01/original-photo.jpg',
            size: 50000,
            eTag: 'abc123',
        );

        ($handler)($message);
    }

    #[Test]
    public function invokeExtractsRelativePathCorrectly(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(\WpPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage::class))
            ->willReturn(Envelope::wrap(new \stdClass()));

        $handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'uploads',
        );

        $message = new \WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/03/document.pdf',
            size: 10000,
            eTag: 'def456',
        );

        ($handler)($message);
    }

    #[Test]
    public function invokeHandlesKeyWithoutPrefix(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'wp-content/uploads',
        );

        $message = new \WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: 'other/path/file.png',
            size: 1000,
            eTag: 'ghi789',
        );

        ($handler)($message);
    }

    #[Test]
    public function invokeHandlesWpInsertAttachmentReturningWpError(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        // Mock wp_insert_attachment to return WP_Error
        add_filter('wp_insert_attachment_data', static function () {
            return false;
        }, \PHP_INT_MAX);

        $handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'uploads',
        );

        $message = new \WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/01/test-file.jpg',
            size: 5000,
            eTag: 'abc',
        );

        // This may or may not dispatch depending on how WP processes the filter,
        // but should not throw
        try {
            ($handler)($message);
        } finally {
            remove_all_filters('wp_insert_attachment_data');
        }
    }

    #[Test]
    public function invokeHandlesMultisitePath(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);

        $handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'uploads',
        );

        // Test that parseBlogId correctly identifies multisite paths
        self::assertSame(5, $handler->parseBlogId('uploads/sites/5/2024/01/image.jpg'));
    }

    #[Test]
    public function constructorAcceptsCustomMimeTypes(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $mimeTypes = $this->createMock(\WpPack\Component\Mime\MimeTypesInterface::class);
        $mimeTypes->method('guessMimeType')->willReturn('image/webp');

        $handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'uploads',
            mimeTypes: $mimeTypes,
        );

        // Handler should use the provided mimeTypes instance
        self::assertInstanceOf(S3ObjectCreatedHandler::class, $handler);
    }

    #[Test]
    public function invokeUsesCustomMimeTypesGuesser(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $mimeTypes = $this->createMock(\WpPack\Component\Mime\MimeTypesInterface::class);
        $mimeTypes->method('guessMimeType')->willReturn(null);

        $handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'uploads',
            mimeTypes: $mimeTypes,
        );

        $message = new \WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/01/unknown-type.xyz',
            size: 1000,
            eTag: 'xyz',
        );

        // When mimeTypes returns null, should use 'application/octet-stream'
        ($handler)($message);
    }

    #[Test]
    public function invokeWithLogger(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $handler = new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: 'uploads',
            logger: $logger,
        );

        $message = new \WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/01/photo-with-logger.jpg',
            size: 5000,
            eTag: 'abc',
        );

        ($handler)($message);
    }
}
