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

namespace WpPack\Component\Media\Tests\Storage\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Media\Storage\PrivateAttachmentChecker;
use WpPack\Component\Media\Storage\SignedUrlCache;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\Subscriber\PrivateAttachmentSubscriber;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Transient\TransientManager;

#[CoversClass(PrivateAttachmentSubscriber::class)]
final class PrivateAttachmentSubscriberTest extends TestCase
{
    private StorageConfiguration $config;
    private PrivateAttachmentChecker $checker;
    private SignedUrlCache $cache;
    private PrivateAttachmentSubscriber $subscriber;
    private int $attachmentId;

    protected function setUp(): void
    {
        $this->config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
        );
        $this->checker = new PrivateAttachmentChecker();

        // Create an adapter that supports temporaryUrl
        $adapter = $this->createSignableAdapter();

        $this->cache = new SignedUrlCache(new TransientManager());
        $this->subscriber = new PrivateAttachmentSubscriber(
            $this->config,
            $adapter,
            $this->checker,
            $this->cache,
        );

        // Create a test attachment
        $this->attachmentId = wp_insert_attachment([
            'post_title' => 'Test Image',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ]);
        update_post_meta($this->attachmentId, '_wp_attached_file', '2024/01/photo.jpg');
    }

    protected function tearDown(): void
    {
        wp_delete_attachment($this->attachmentId, true);
    }

    #[Test]
    public function privateAttachmentUrlIsConvertedToSignedUrl(): void
    {
        $this->checker->setPrivate($this->attachmentId, true);

        $event = new WordPressEvent('wp_get_attachment_url', [
            'https://cdn.example.com/uploads/2024/01/photo.jpg',
            $this->attachmentId,
        ]);
        $this->subscriber->filterAttachmentUrl($event);

        self::assertStringContainsString('X-Amz-Signature', (string) $event->filterValue);
    }

    #[Test]
    public function nonPrivateAttachmentUrlIsNotChanged(): void
    {
        $this->checker->setPrivate($this->attachmentId, false);

        $originalUrl = 'https://cdn.example.com/uploads/2024/01/photo.jpg';
        $event = new WordPressEvent('wp_get_attachment_url', [
            $originalUrl,
            $this->attachmentId,
        ]);
        $this->subscriber->filterAttachmentUrl($event);

        self::assertSame($originalUrl, $event->filterValue);
    }

    #[Test]
    public function filterAttachmentImageSrcConvertsUrlForPrivateAttachment(): void
    {
        $this->checker->setPrivate($this->attachmentId, true);

        $image = ['https://cdn.example.com/uploads/2024/01/photo-150x150.jpg', 150, 150, true];
        $event = new WordPressEvent('wp_get_attachment_image_src', [
            $image,
            $this->attachmentId,
            'thumbnail',
            false,
        ]);
        $this->subscriber->filterAttachmentImageSrc($event);

        /** @var array{0: string} $result */
        $result = $event->filterValue;
        self::assertStringContainsString('X-Amz-Signature', $result[0]);
    }

    #[Test]
    public function filterImageSrcsetConvertsSourceUrlsForPrivateAttachment(): void
    {
        $this->checker->setPrivate($this->attachmentId, true);

        $sources = [
            300 => ['url' => 'https://cdn.example.com/uploads/2024/01/photo-300x200.jpg', 'descriptor' => 'w', 'value' => 300],
            600 => ['url' => 'https://cdn.example.com/uploads/2024/01/photo-600x400.jpg', 'descriptor' => 'w', 'value' => 600],
        ];

        $event = new WordPressEvent('wp_calculate_image_srcset', [
            $sources,
            [300, 200],
            'https://cdn.example.com/uploads/2024/01/photo-300x200.jpg',
            ['sizes' => ['medium' => ['file' => 'photo-300x200.jpg']]],
            $this->attachmentId,
        ]);
        $this->subscriber->filterImageSrcset($event);

        /** @var array<int, array{url: string}> $result */
        $result = $event->filterValue;
        self::assertStringContainsString('X-Amz-Signature', $result[300]['url']);
        self::assertStringContainsString('X-Amz-Signature', $result[600]['url']);
    }

    #[Test]
    public function filterAttachmentUrlSkipsWhenAttachedFileIsEmpty(): void
    {
        $this->checker->setPrivate($this->attachmentId, true);
        update_post_meta($this->attachmentId, '_wp_attached_file', '');

        $originalUrl = 'https://cdn.example.com/uploads/2024/01/photo.jpg';
        $event = new WordPressEvent('wp_get_attachment_url', [
            $originalUrl,
            $this->attachmentId,
        ]);
        $this->subscriber->filterAttachmentUrl($event);

        self::assertSame($originalUrl, $event->filterValue);
    }

    #[Test]
    public function signedUrlIsCached(): void
    {
        $this->checker->setPrivate($this->attachmentId, true);

        // First call — generates signed URL
        $event1 = new WordPressEvent('wp_get_attachment_url', [
            'https://cdn.example.com/uploads/2024/01/photo.jpg',
            $this->attachmentId,
        ]);
        $this->subscriber->filterAttachmentUrl($event1);

        $signedUrl1 = $event1->filterValue;
        self::assertStringContainsString('X-Amz-Signature', (string) $signedUrl1);

        // Second call — should return cached URL (same value)
        $event2 = new WordPressEvent('wp_get_attachment_url', [
            'https://cdn.example.com/uploads/2024/01/photo.jpg',
            $this->attachmentId,
        ]);
        $this->subscriber->filterAttachmentUrl($event2);

        self::assertSame($signedUrl1, $event2->filterValue);
    }

    /**
     * Create a storage adapter that supports temporaryUrl for testing.
     */
    private function createSignableAdapter(): StorageAdapterInterface
    {
        return new class implements StorageAdapterInterface {
            /** @var array<string, string> */
            private array $objects = [];

            public function getName(): string
            {
                return 'test-signable';
            }

            public function write(string $path, string $contents, array $metadata = []): void
            {
                $this->objects[$path] = $contents;
            }

            public function writeStream(string $path, mixed $resource, array $metadata = []): void
            {
                $this->objects[$path] = (string) stream_get_contents($resource);
            }

            public function read(string $path): string
            {
                return $this->objects[$path] ?? '';
            }

            public function readStream(string $path): mixed
            {
                $stream = fopen('php://memory', 'r+');
                \assert($stream !== false);
                fwrite($stream, $this->read($path));
                rewind($stream);

                return $stream;
            }

            public function delete(string $path): void
            {
                unset($this->objects[$path]);
            }

            public function deleteMultiple(array $paths): void
            {
                foreach ($paths as $path) {
                    unset($this->objects[$path]);
                }
            }

            public function fileExists(string $path): bool
            {
                return isset($this->objects[$path]);
            }

            public function createDirectory(string $path): void {}

            public function deleteDirectory(string $path): void {}

            public function directoryExists(string $path): bool
            {
                return false;
            }

            public function copy(string $source, string $destination): void
            {
                $this->objects[$destination] = $this->objects[$source] ?? '';
            }

            public function move(string $source, string $destination): void
            {
                $this->copy($source, $destination);
                $this->delete($source);
            }

            public function metadata(string $path): \WpPack\Component\Storage\ObjectMetadata
            {
                return new \WpPack\Component\Storage\ObjectMetadata(path: $path, size: 0);
            }

            public function publicUrl(string $path): string
            {
                return 'https://cdn.example.com/' . $path;
            }

            public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
            {
                return 'https://my-bucket.s3.amazonaws.com/' . $path
                    . '?X-Amz-Signature=abc123'
                    . '&X-Amz-Expires=' . ($expiration->getTimestamp() - time());
            }

            public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
            {
                return $this->temporaryUrl($path, $expiration);
            }

            public function setVisibility(string $path, \WpPack\Component\Storage\Visibility $visibility): void {}

            public function listContents(string $path = '', bool $deep = false): iterable
            {
                return [];
            }
        };
    }
}
