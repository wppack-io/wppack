<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Attachment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Media\AttachmentManager;
use WpPack\Component\PostType\PostRepository;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Site\BlogSwitcher;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Attachment\RegisterAttachmentController;

#[CoversClass(RegisterAttachmentController::class)]
final class RegisterAttachmentControllerTest extends TestCase
{
    private function createController(
        ?AttachmentRegistrar $registrar = null,
        ?StorageAdapterInterface $adapter = null,
    ): RegisterAttachmentController {
        $attachment = new AttachmentManager(new PostRepository());
        $registrar ??= new AttachmentRegistrar(
            bus: $this->createMock(MessageBusInterface::class),
            prefix: 'uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: $attachment,
        );
        $adapter ??= $this->createMock(StorageAdapterInterface::class);

        return new RegisterAttachmentController($registrar, $adapter, $attachment);
    }

    #[Test]
    public function returnsErrorWhenKeyIsMissing(): void
    {
        $controller = $this->createController();

        $request = new Request(
            content: '{}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function returnsErrorWhenKeyIsEmpty(): void
    {
        $controller = $this->createController();

        $request = new Request(
            content: '{"key":""}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function returnsNotFoundWhenFileDoesNotExist(): void
    {
        $adapter = $this->createMock(StorageAdapterInterface::class);
        $adapter->method('fileExists')->willReturn(false);

        $controller = $this->createController(adapter: $adapter);

        $request = new Request(
            content: '{"key":"uploads/2024/01/nonexistent.jpg"}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(404, $response->statusCode);
    }

    #[Test]
    public function returnsCreatedForSuccessfulRegistration(): void
    {
        $adapter = $this->createMock(StorageAdapterInterface::class);
        $adapter->method('fileExists')->willReturn(true);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(\WpPack\Component\Messenger\Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $controller = $this->createController(registrar: $registrar, adapter: $adapter);

        $key = 'uploads/2024/01/ctrl-test-' . uniqid() . '.jpg';
        $request = new Request(
            content: json_encode(['key' => $key]),
        );

        $response = $controller->__invoke($request);

        self::assertSame(201, $response->statusCode);
    }

    #[Test]
    public function returnsErrorForResizedImage(): void
    {
        $adapter = $this->createMock(StorageAdapterInterface::class);
        $adapter->expects(self::never())->method('fileExists');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $controller = $this->createController(registrar: $registrar, adapter: $adapter);

        $request = new Request(
            content: '{"key":"uploads/2024/01/photo-100x200.jpg"}',
        );

        $response = $controller->__invoke($request);

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function worksWithPostData(): void
    {
        $adapter = $this->createMock(StorageAdapterInterface::class);
        $adapter->method('fileExists')->willReturn(true);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(\WpPack\Component\Messenger\Envelope::wrap(new \stdClass()));

        $registrar = new AttachmentRegistrar(
            bus: $bus,
            prefix: 'uploads',
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );

        $controller = $this->createController(registrar: $registrar, adapter: $adapter);

        $key = 'uploads/2024/01/ctrl-post-' . uniqid() . '.jpg';
        $request = new Request(
            post: ['key' => $key],
        );

        $response = $controller->__invoke($request);

        self::assertSame(201, $response->statusCode);
    }
}
