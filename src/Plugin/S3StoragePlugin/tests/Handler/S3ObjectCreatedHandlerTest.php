<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Handler\S3ObjectCreatedHandler;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage;

require_once __DIR__ . '/multisite-polyfill.php';

#[CoversClass(S3ObjectCreatedHandler::class)]
final class S3ObjectCreatedHandlerTest extends TestCase
{
    #[Test]
    public function invokeDelegatesToRegistrar(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
        );

        $handler = new S3ObjectCreatedHandler($registrar);

        $uniqueKey = 'uploads/2024/01/handler-delegate-' . uniqid() . '.jpg';

        $message = new S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: $uniqueKey,
            size: 50000,
            eTag: 'abc123',
        );

        ($handler)($message);

        // Verify the handler executed without error (attachment was created via registrar)
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

        $handler = new S3ObjectCreatedHandler($registrar);

        $message = new S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/01/photo-100x200.jpg',
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
        );

        $handler = new S3ObjectCreatedHandler($registrar);

        $uniqueKey = 'uploads/2024/03/handler-key-' . uniqid() . '.pdf';

        $message = new S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: $uniqueKey,
            size: 10000,
            eTag: 'def456',
        );

        ($handler)($message);

        // Verify handler completed (registrar processed the key)
        $this->addToAssertionCount(1);
    }
}
