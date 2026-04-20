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

namespace WPPack\Plugin\S3StoragePlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Media\AttachmentManager;
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\MessageBusInterface;
use WPPack\Component\PostType\PostRepository;
use WPPack\Component\Site\BlogSwitcher;
use WPPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WPPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WPPack\Plugin\S3StoragePlugin\Handler\S3ObjectRemovedHandler;
use WPPack\Plugin\S3StoragePlugin\Message\S3ObjectRemovedMessage;

#[CoversClass(S3ObjectRemovedHandler::class)]
final class S3ObjectRemovedHandlerTest extends TestCase
{
    private const BUCKET = 'my-bucket';

    private function createConfig(string $bucket = self::BUCKET): S3StorageConfiguration
    {
        return new S3StorageConfiguration(
            dsn: 's3://' . $bucket . '?region=us-east-1',
            bucket: $bucket,
            region: 'us-east-1',
        );
    }

    #[Test]
    public function invokeDelegatesToRegistrarUnregister(): void
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

        // Create an attachment first so unregister has something to find
        $key = 'wp-content/uploads/2024/01/handler-remove-' . uniqid() . '.jpg';
        $createdId = $registrar->register($key);
        self::assertIsInt($createdId);

        $handler = new S3ObjectRemovedHandler($registrar, $this->createConfig());

        $message = new S3ObjectRemovedMessage(
            bucket: self::BUCKET,
            key: $key,
        );

        ($handler)($message);

        self::assertNull(get_post($createdId), 'Attachment should have been deleted by the handler.');
    }

    #[Test]
    public function invokeSkipsResizedImages(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $handler = new S3ObjectRemovedHandler($registrar, $this->createConfig());

        $message = new S3ObjectRemovedMessage(
            bucket: self::BUCKET,
            key: 'wp-content/uploads/2024/01/photo-100x200.jpg',
        );

        ($handler)($message);
    }

    #[Test]
    public function invokeIgnoresEventFromDifferentBucket(): void
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

        // Create an attachment
        $key = 'wp-content/uploads/2024/01/handler-bucket-' . uniqid() . '.jpg';
        $createdId = $registrar->register($key);
        self::assertIsInt($createdId);

        $handler = new S3ObjectRemovedHandler($registrar, $this->createConfig('expected-bucket'));

        $message = new S3ObjectRemovedMessage(
            bucket: 'other-bucket',
            key: $key,
        );

        ($handler)($message);

        // Attachment should still exist since bucket didn't match
        self::assertNotNull(get_post($createdId), 'Attachment should NOT have been deleted for mismatched bucket.');
    }

    #[Test]
    public function invokeSkipsNonExistentAttachment(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'wp-content/uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $handler = new S3ObjectRemovedHandler($registrar, $this->createConfig());

        $message = new S3ObjectRemovedMessage(
            bucket: self::BUCKET,
            key: 'wp-content/uploads/2024/01/nonexistent-' . uniqid() . '.jpg',
        );

        ($handler)($message);
    }
}
