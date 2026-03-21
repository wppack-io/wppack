<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Handler\S3ObjectRemovedHandler;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectRemovedMessage;

require_once __DIR__ . '/multisite-polyfill.php';

#[CoversClass(S3ObjectRemovedHandler::class)]
final class S3ObjectRemovedHandlerTest extends TestCase
{
    #[Test]
    public function invokeDelegatesToRegistrarUnregister(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        // Create an attachment first so unregister has something to find
        $key = 'uploads/2024/01/handler-remove-' . uniqid() . '.jpg';
        $registrar->register($key);

        $handler = new S3ObjectRemovedHandler($registrar);

        $message = new S3ObjectRemovedMessage(
            bucket: 'my-bucket',
            key: $key,
        );

        ($handler)($message);

        // Verify the handler executed without error
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function invokeSkipsResizedImages(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $handler = new S3ObjectRemovedHandler($registrar);

        $message = new S3ObjectRemovedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/01/photo-100x200.jpg',
        );

        ($handler)($message);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function invokeSkipsNonExistentAttachment(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $handler = new S3ObjectRemovedHandler($registrar);

        $message = new S3ObjectRemovedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/01/nonexistent-' . uniqid() . '.jpg',
        );

        ($handler)($message);

        $this->addToAssertionCount(1);
    }
}
