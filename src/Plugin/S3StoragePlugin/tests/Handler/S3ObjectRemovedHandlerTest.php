<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WpPack\Plugin\S3StoragePlugin\Handler\S3ObjectRemovedHandler;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectRemovedMessage;

require_once __DIR__ . '/multisite-polyfill.php';

#[CoversClass(S3ObjectRemovedHandler::class)]
final class S3ObjectRemovedHandlerTest extends TestCase
{
    private const BUCKET = 'my-bucket';

    private function createConfig(string $bucket = self::BUCKET): S3StorageConfiguration
    {
        return new S3StorageConfiguration(bucket: $bucket, region: 'us-east-1');
    }

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
            prefix: 'uploads',
        );

        $handler = new S3ObjectRemovedHandler($registrar, $this->createConfig());

        $message = new S3ObjectRemovedMessage(
            bucket: self::BUCKET,
            key: 'uploads/2024/01/photo-100x200.jpg',
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
            prefix: 'uploads',
        );

        // Create an attachment
        $key = 'uploads/2024/01/handler-bucket-' . uniqid() . '.jpg';
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
            prefix: 'uploads',
        );

        $handler = new S3ObjectRemovedHandler($registrar, $this->createConfig());

        $message = new S3ObjectRemovedMessage(
            bucket: self::BUCKET,
            key: 'uploads/2024/01/nonexistent-' . uniqid() . '.jpg',
        );

        ($handler)($message);
    }
}
