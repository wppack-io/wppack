<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Media\AttachmentManager;
use WpPack\Component\PostType\PostRepository;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Site\BlogSwitcher;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WpPack\Plugin\S3StoragePlugin\Handler\S3ObjectCreatedHandler;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage;

#[CoversClass(S3ObjectCreatedHandler::class)]
final class S3ObjectCreatedHandlerTest extends TestCase
{
    private const BUCKET = 'my-bucket';

    private function createConfig(string $bucket = self::BUCKET): S3StorageConfiguration
    {
        return new S3StorageConfiguration(bucket: $bucket, region: 'us-east-1');
    }

    #[Test]
    public function invokeDelegatesToRegistrar(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $handler = new S3ObjectCreatedHandler($registrar, $this->createConfig());

        $uniqueKey = 'uploads/2024/01/handler-delegate-' . uniqid() . '.jpg';

        $message = new S3ObjectCreatedMessage(
            bucket: self::BUCKET,
            key: $uniqueKey,
            size: 50000,
            eTag: 'abc123',
        );

        ($handler)($message);

        $relativePath = substr($uniqueKey, \strlen('uploads/'));
        $existing = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'meta_key' => '_wp_attached_file',
            'meta_value' => $relativePath,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        self::assertNotEmpty($existing, 'Attachment should have been created by the handler.');
    }

    #[Test]
    public function invokeSkipsResizedImages(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $handler = new S3ObjectCreatedHandler($registrar, $this->createConfig());

        $message = new S3ObjectCreatedMessage(
            bucket: self::BUCKET,
            key: 'uploads/2024/01/photo-100x200.jpg',
            size: 5000,
            eTag: 'abc123',
        );

        ($handler)($message);
    }

    #[Test]
    public function invokeIgnoresEventFromDifferentBucket(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $handler = new S3ObjectCreatedHandler($registrar, $this->createConfig('expected-bucket'));

        $message = new S3ObjectCreatedMessage(
            bucket: 'other-bucket',
            key: 'uploads/2024/01/photo.jpg',
            size: 5000,
            eTag: 'abc123',
        );

        ($handler)($message);
    }

    #[Test]
    public function invokePassesMessageKeyToRegistrar(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $handler = new S3ObjectCreatedHandler($registrar, $this->createConfig());

        $uniqueKey = 'uploads/2024/03/handler-key-' . uniqid() . '.pdf';

        $message = new S3ObjectCreatedMessage(
            bucket: self::BUCKET,
            key: $uniqueKey,
            size: 10000,
            eTag: 'def456',
        );

        ($handler)($message);

        $relativePath = substr($uniqueKey, \strlen('uploads/'));
        $existing = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'meta_key' => '_wp_attached_file',
            'meta_value' => $relativePath,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        self::assertNotEmpty($existing, 'Attachment should have been created by the handler.');
    }
}
