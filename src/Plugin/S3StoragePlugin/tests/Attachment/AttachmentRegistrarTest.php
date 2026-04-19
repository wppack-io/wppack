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

namespace WPPack\Plugin\S3StoragePlugin\Tests\Attachment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WPPack\Component\Media\AttachmentManager;
use WPPack\Component\PostType\PostRepository;
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\MessageBusInterface;
use WPPack\Component\Mime\MimeTypesInterface;
use WPPack\Component\Site\BlogSwitcher;
use WPPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WPPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;

#[CoversClass(AttachmentRegistrar::class)]
final class AttachmentRegistrarTest extends TestCase
{
    private AttachmentRegistrar $registrar;

    protected function setUp(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $this->registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
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
        yield 'width x height' => ['wp-content/uploads/2024/01/photo-100x200.jpg'];
        yield 'large dimensions' => ['wp-content/uploads/2024/01/image-1920x1080.png'];
        yield 'small dimensions' => ['wp-content/uploads/2024/01/thumb-1x1.gif'];
        yield 'scaled' => ['wp-content/uploads/2024/01/photo-scaled.jpg'];
        yield 'rotated' => ['wp-content/uploads/2024/01/image-rotated.png'];
        yield 'edited timestamp' => ['wp-content/uploads/2024/01/photo-e1234567890.jpg'];
        yield 'edited long timestamp' => ['wp-content/uploads/2024/01/photo-e12345678901234.webp'];
        yield 'nested path with dimensions' => ['wp-content/uploads/sites/2/2024/01/photo-300x300.jpg'];
        yield 'nested path with scaled' => ['wp-content/uploads/sites/3/2024/01/photo-scaled.png'];
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
        yield 'original image' => ['wp-content/uploads/2024/01/photo.jpg'];
        yield 'original png' => ['wp-content/uploads/2024/01/image.png'];
        yield 'pdf file' => ['wp-content/uploads/2024/01/document.pdf'];
        yield 'video file' => ['wp-content/uploads/2024/01/video.mp4'];
        yield 'dimensions in name' => ['wp-content/uploads/2024/01/100x200.jpg'];
        yield 'name with number' => ['wp-content/uploads/2024/01/photo-1.jpg'];
        yield 'name with text suffix' => ['wp-content/uploads/2024/01/photo-edited.jpg'];
        yield 'short e timestamp' => ['wp-content/uploads/2024/01/photo-e12345.jpg'];
        yield 'no extension' => ['wp-content/uploads/2024/01/README'];
        yield 'multiple dots' => ['wp-content/uploads/2024/01/file.backup.jpg'];
    }

    #[Test]
    public function parseBlogIdReturnsMainSiteIdForMainSite(): void
    {
        self::assertSame(get_main_site_id(), $this->registrar->parseBlogId('wp-content/uploads/2024/01/photo.jpg'));
    }

    #[Test]
    public function parseBlogIdFromMultisite(): void
    {
        self::assertSame(2, $this->registrar->parseBlogId('wp-content/uploads/sites/2/2024/01/photo.jpg'));
        self::assertSame(42, $this->registrar->parseBlogId('wp-content/uploads/sites/42/2024/01/photo.jpg'));
        self::assertSame(100, $this->registrar->parseBlogId('wp-content/uploads/sites/100/image.png'));
    }

    #[Test]
    public function parseBlogIdReturnsMainSiteIdForPathWithoutSitesPattern(): void
    {
        self::assertSame(get_main_site_id(), $this->registrar->parseBlogId('wp-content/uploads/2024/01/file.pdf'));
        self::assertSame(get_main_site_id(), $this->registrar->parseBlogId('other-prefix/image.jpg'));
        self::assertSame(get_main_site_id(), $this->registrar->parseBlogId('wp-content/uploads/sites/file.jpg'));
    }

    #[Test]
    public function registerReturnsNullForResizedImage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $result = $registrar->register('wp-content/uploads/2024/01/photo-100x200.jpg');

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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $key = 'wp-content/uploads/2024/01/registrar-original-' . uniqid() . '.jpg';
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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $key = 'wp-content/uploads/2024/01/registrar-user-' . uniqid() . '.jpg';
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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $key = 'wp-content/uploads/2024/01/registrar-dup-' . uniqid() . '.jpg';

        // First call creates
        $firstId = $registrar->register($key);
        self::assertIsInt($firstId);

        // Second call with fresh bus mock (should not dispatch)
        $bus2 = $this->createMock(MessageBusInterface::class);
        $bus2->expects(self::never())->method('dispatch');

        $registrar2 = new AttachmentRegistrar(
            bus: $bus2,
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $key = 'wp-content/uploads/sites/2/2024/01/registrar-multi-' . uniqid() . '.jpg';
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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $key = 'wp-content/uploads/sites/4/2024/01/registrar-exc-' . uniqid() . '.jpg';

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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
            mimeTypes: $mimeTypes,
        );

        $key = 'wp-content/uploads/2024/01/registrar-mime-' . uniqid() . '.webp';
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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
            mimeTypes: $mimeTypes,
        );

        $key = 'wp-content/uploads/2024/01/registrar-null-' . uniqid() . '.xyz';
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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
            logger: $logger,
        );

        $key = 'wp-content/uploads/2024/01/registrar-log-' . uniqid() . '.jpg';
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
            prefix: 'wp-content/uploads/',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $key = 'wp-content/uploads/2024/01/registrar-trail-' . uniqid() . '.jpg';
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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $key = 'wp-content/uploads/2024/01/unregister-' . uniqid() . '.jpg';
        $createdId = $registrar->register($key);
        self::assertIsInt($createdId);

        $deletedId = $registrar->unregister($key);

        self::assertSame($createdId, $deletedId);
        self::assertNull(get_post($deletedId), 'Attachment should have been deleted from the database.');
    }

    #[Test]
    public function unregisterReturnsNullForNonExistentAttachment(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $key = 'wp-content/uploads/2024/01/nonexistent-' . uniqid() . '.jpg';
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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $result = $registrar->unregister('wp-content/uploads/2024/01/photo-100x200.jpg');

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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $key = 'wp-content/uploads/sites/2/2024/01/unregister-multi-' . uniqid() . '.jpg';
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
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
            logger: $logger,
        );

        $key = 'wp-content/uploads/2024/01/unregister-log-' . uniqid() . '.jpg';
        $result = $registrar->unregister($key);

        self::assertNull($result);
    }
}
