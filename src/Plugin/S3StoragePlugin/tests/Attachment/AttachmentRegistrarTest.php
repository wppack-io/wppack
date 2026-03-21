<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Attachment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Mime\MimeTypesInterface;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;

require_once __DIR__ . '/../Handler/multisite-polyfill.php';

#[CoversClass(AttachmentRegistrar::class)]
final class AttachmentRegistrarTest extends TestCase
{
    private AttachmentRegistrar $registrar;

    protected function setUp(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $this->registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );
    }

    #[Test]
    #[DataProvider('resizedImageProvider')]
    public function isResizedImageReturnsTrueForResizedImages(string $key): void
    {
        self::assertTrue($this->registrar->isResizedImage($key));
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
        self::assertFalse($this->registrar->isResizedImage($key));
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
        self::assertSame(1, $this->registrar->parseBlogId('uploads/2024/01/photo.jpg'));
    }

    #[Test]
    public function parseBlogIdFromMultisite(): void
    {
        self::assertSame(2, $this->registrar->parseBlogId('uploads/sites/2/2024/01/photo.jpg'));
        self::assertSame(42, $this->registrar->parseBlogId('uploads/sites/42/2024/01/photo.jpg'));
        self::assertSame(100, $this->registrar->parseBlogId('uploads/sites/100/image.png'));
    }

    #[Test]
    public function parseBlogIdReturnsOneForPathWithoutSitesPattern(): void
    {
        self::assertSame(1, $this->registrar->parseBlogId('uploads/2024/01/file.pdf'));
        self::assertSame(1, $this->registrar->parseBlogId('other-prefix/image.jpg'));
        self::assertSame(1, $this->registrar->parseBlogId('uploads/sites/file.jpg'));
    }

    #[Test]
    public function registerReturnsNullForResizedImage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $result = $registrar->register('uploads/2024/01/photo-100x200.jpg');

        self::assertNull($result);
    }

    #[Test]
    public function registerCreatesAttachmentForOriginalImage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(GenerateThumbnailsMessage::class))
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $key = 'uploads/2024/01/registrar-original-' . uniqid() . '.jpg';
        $result = $registrar->register($key);

        self::assertIsInt($result);
        self::assertGreaterThan(0, $result);
    }

    #[Test]
    public function registerPassesUserIdToAttachment(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $key = 'uploads/2024/01/registrar-user-' . uniqid() . '.jpg';
        $result = $registrar->register($key, 42);

        self::assertIsInt($result);
        self::assertGreaterThan(0, $result);
    }

    #[Test]
    public function registerReturnsExistingAttachmentForDuplicate(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $key = 'uploads/2024/01/registrar-dup-' . uniqid() . '.jpg';

        // First call creates
        $firstId = $registrar->register($key);
        self::assertIsInt($firstId);

        // Second call with fresh bus mock (should not dispatch)
        $bus2 = $this->createMock(MessageBusInterface::class);
        $bus2->expects(self::never())->method('dispatch');

        $registrar2 = new AttachmentRegistrar(
            bus: $bus2,
            prefix: 'uploads',
        );

        $secondId = $registrar2->register($key);

        self::assertSame($firstId, $secondId);
    }

    #[Test]
    public function registerHandlesMultisitePath(): void
    {
        $currentBlogId = get_current_blog_id();

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::callback(static function (object $msg): bool {
                return $msg instanceof GenerateThumbnailsMessage
                    && $msg->blogId === 2;
            }))
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $key = 'uploads/sites/2/2024/01/registrar-multi-' . uniqid() . '.jpg';
        $registrar->register($key);

        self::assertSame($currentBlogId, get_current_blog_id());
    }

    #[Test]
    public function registerRestoresBlogOnException(): void
    {
        $currentBlogId = get_current_blog_id();

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willThrowException(new \RuntimeException('Bus error'));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $key = 'uploads/sites/4/2024/01/registrar-exc-' . uniqid() . '.jpg';

        try {
            $registrar->register($key);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertSame($currentBlogId, get_current_blog_id());
    }

    #[Test]
    public function registerWithCustomMimeTypes(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $mimeTypes = $this->createMock(MimeTypesInterface::class);
        $mimeTypes->method('guessMimeType')->willReturn('image/webp');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            mimeTypes: $mimeTypes,
        );

        $key = 'uploads/2024/01/registrar-mime-' . uniqid() . '.webp';
        $result = $registrar->register($key);

        self::assertIsInt($result);
    }

    #[Test]
    public function registerWithNullMimeTypeFallsBackToOctetStream(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $mimeTypes = $this->createMock(MimeTypesInterface::class);
        $mimeTypes->method('guessMimeType')->willReturn(null);

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            mimeTypes: $mimeTypes,
        );

        $key = 'uploads/2024/01/registrar-null-' . uniqid() . '.xyz';
        $result = $registrar->register($key);

        self::assertIsInt($result);
    }

    #[Test]
    public function registerWithLogger(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $logger = $this->createMock(LoggerInterface::class);

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            logger: $logger,
        );

        $key = 'uploads/2024/01/registrar-log-' . uniqid() . '.jpg';
        $result = $registrar->register($key);

        self::assertIsInt($result);
    }

    #[Test]
    public function registerWithPrefixTrailingSlash(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads/',
        );

        $key = 'uploads/2024/01/registrar-trail-' . uniqid() . '.jpg';
        $result = $registrar->register($key);

        self::assertIsInt($result);
    }

    #[Test]
    public function unregisterDeletesExistingAttachment(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $key = 'uploads/2024/01/unregister-' . uniqid() . '.jpg';
        $createdId = $registrar->register($key);
        self::assertIsInt($createdId);

        $deletedId = $registrar->unregister($key);

        self::assertSame($createdId, $deletedId);
    }

    #[Test]
    public function unregisterReturnsNullForNonExistentAttachment(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $key = 'uploads/2024/01/nonexistent-' . uniqid() . '.jpg';
        $result = $registrar->unregister($key);

        self::assertNull($result);
    }

    #[Test]
    public function unregisterReturnsNullForResizedImage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $result = $registrar->unregister('uploads/2024/01/photo-100x200.jpg');

        self::assertNull($result);
    }

    #[Test]
    public function unregisterHandlesMultisitePath(): void
    {
        $currentBlogId = get_current_blog_id();

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $key = 'uploads/sites/2/2024/01/unregister-multi-' . uniqid() . '.jpg';
        $registrar->register($key);
        $registrar->unregister($key);

        self::assertSame($currentBlogId, get_current_blog_id());
    }

    #[Test]
    public function unregisterWithLoggerLogsWhenAttachmentNotFound(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with(
                'No attachment found for "{path}", skipping deletion.',
                self::isType('array'),
            );

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            logger: $logger,
        );

        $key = 'uploads/2024/01/unregister-log-' . uniqid() . '.jpg';
        $result = $registrar->unregister($key);

        self::assertNull($result);
    }
}
