<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\Azure\Tests;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\ContainerClient;
use AzureOss\Storage\Blob\Models\BlobProperties;
use AzureOss\Storage\Blob\Models\DownloadStreamingResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use WpPack\Component\Storage\Bridge\Azure\AzureStorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;

#[CoversClass(AzureStorageAdapter::class)]
final class AzureStorageAdapterTest extends TestCase
{
    #[Test]
    public function getName(): void
    {
        $adapter = new AzureStorageAdapter(
            $this->createMock(BlobServiceClient::class),
            'my-container',
        );

        self::assertSame('azure', $adapter->getName());
    }

    #[Test]
    public function writeCallsUpload(): void
    {
        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->expects($this->once())
            ->method('upload')
            ->with('contents', []);

        $adapter = $this->createAdapter($blobClient);
        $adapter->write('file.txt', 'contents');
    }

    #[Test]
    public function writeWithPrefix(): void
    {
        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->expects($this->once())
            ->method('upload');

        $containerClient = $this->createMock(ContainerClient::class);
        $containerClient->method('getBlobClient')
            ->with('uploads/file.txt')
            ->willReturn($blobClient);

        $serviceClient = $this->createMock(BlobServiceClient::class);
        $serviceClient->method('getContainerClient')->willReturn($containerClient);

        $adapter = new AzureStorageAdapter($serviceClient, 'my-container', 'uploads');
        $adapter->write('file.txt', 'contents');
    }

    #[Test]
    public function writeWithContentType(): void
    {
        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->expects($this->once())
            ->method('upload')
            ->with('contents', ['contentType' => 'text/plain']);

        $adapter = $this->createAdapter($blobClient);
        $adapter->write('file.txt', 'contents', ['Content-Type' => 'text/plain']);
    }

    #[Test]
    public function readReturnsContents(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('file contents');

        $downloadResult = $this->createMock(DownloadStreamingResult::class);
        $downloadResult->method('getBody')->willReturn($stream);

        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->method('downloadStreaming')->willReturn($downloadResult);

        $adapter = $this->createAdapter($blobClient);

        self::assertSame('file contents', $adapter->read('file.txt'));
    }

    #[Test]
    public function readStreamReturnsResource(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, true);
        $stream->method('read')->with(8192)->willReturn('stream contents');

        $downloadResult = $this->createMock(DownloadStreamingResult::class);
        $downloadResult->method('getBody')->willReturn($stream);

        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->method('downloadStreaming')->willReturn($downloadResult);

        $adapter = $this->createAdapter($blobClient);
        $result = $adapter->readStream('file.txt');

        self::assertIsResource($result);
        self::assertSame('stream contents', stream_get_contents($result));
    }

    #[Test]
    public function readThrowsObjectNotFoundExceptionOn404(): void
    {
        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->method('downloadStreaming')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

        $adapter = $this->createAdapter($blobClient);

        $this->expectException(ObjectNotFoundException::class);
        $adapter->read('nonexistent.txt');
    }

    #[Test]
    public function deleteCallsDelete(): void
    {
        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->expects($this->once())
            ->method('delete');

        $adapter = $this->createAdapter($blobClient);
        $adapter->delete('file.txt');
    }

    #[Test]
    public function existsReturnsTrueWhenBlobExists(): void
    {
        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->method('getProperties')
            ->willReturn($this->createMock(BlobProperties::class));

        $adapter = $this->createAdapter($blobClient);

        self::assertTrue($adapter->exists('file.txt'));
    }

    #[Test]
    public function existsReturnsFalseWhenNotFound(): void
    {
        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->method('getProperties')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

        $adapter = $this->createAdapter($blobClient);

        self::assertFalse($adapter->exists('nonexistent.txt'));
    }

    #[Test]
    public function copyCallsCopyFromUrl(): void
    {
        $sourceUri = 'https://account.blob.core.windows.net/my-container/source.txt';

        $sourceClient = $this->createMock(BlobClient::class);
        $sourceClient->uri = $sourceUri;

        $destClient = $this->createMock(BlobClient::class);
        $destClient->expects($this->once())
            ->method('copyFromUrl')
            ->with($sourceUri);

        $containerClient = $this->createMock(ContainerClient::class);
        $containerClient->method('getBlobClient')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'source.txt' => $sourceClient,
                'dest.txt' => $destClient,
            });

        $serviceClient = $this->createMock(BlobServiceClient::class);
        $serviceClient->method('getContainerClient')->willReturn($containerClient);

        $adapter = new AzureStorageAdapter($serviceClient, 'my-container');
        $adapter->copy('source.txt', 'dest.txt');
    }

    #[Test]
    public function metadataReturnsBlobProperties(): void
    {
        $now = new \DateTimeImmutable();

        $properties = $this->createMock(BlobProperties::class);
        $properties->contentLength = 1024;
        $properties->contentType = 'application/pdf';
        $properties->lastModified = $now;

        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->method('getProperties')->willReturn($properties);

        $adapter = $this->createAdapter($blobClient);
        $metadata = $adapter->metadata('doc.pdf');

        self::assertSame('doc.pdf', $metadata->key);
        self::assertSame(1024, $metadata->size);
        self::assertSame('application/pdf', $metadata->mimeType);
        self::assertNotNull($metadata->lastModified);
    }

    #[Test]
    public function metadataThrowsObjectNotFoundExceptionOn404(): void
    {
        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->method('getProperties')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

        $adapter = $this->createAdapter($blobClient);

        $this->expectException(ObjectNotFoundException::class);
        $adapter->metadata('nonexistent.txt');
    }

    #[Test]
    public function publicUrlReturnsBlobDirectUrl(): void
    {
        $blobClient = $this->createMock(BlobClient::class);
        $blobClient->uri = 'https://account.blob.core.windows.net/my-container/path/to/file.txt';

        $adapter = $this->createAdapter($blobClient);

        self::assertSame(
            'https://account.blob.core.windows.net/my-container/path/to/file.txt',
            $adapter->publicUrl('path/to/file.txt'),
        );
    }

    #[Test]
    public function publicUrlReturnsCustomPublicUrl(): void
    {
        $blobClient = $this->createMock(BlobClient::class);

        $containerClient = $this->createMock(ContainerClient::class);
        $containerClient->method('getBlobClient')->willReturn($blobClient);

        $serviceClient = $this->createMock(BlobServiceClient::class);
        $serviceClient->method('getContainerClient')->willReturn($containerClient);

        $adapter = new AzureStorageAdapter(
            $serviceClient,
            'my-container',
            publicUrl: 'https://cdn.example.com',
        );

        self::assertSame(
            'https://cdn.example.com/path/to/file.txt',
            $adapter->publicUrl('path/to/file.txt'),
        );
    }

    #[Test]
    public function publicUrlWithPrefix(): void
    {
        $blobClient = $this->createMock(BlobClient::class);

        $containerClient = $this->createMock(ContainerClient::class);
        $containerClient->method('getBlobClient')->willReturn($blobClient);

        $serviceClient = $this->createMock(BlobServiceClient::class);
        $serviceClient->method('getContainerClient')->willReturn($containerClient);

        $adapter = new AzureStorageAdapter(
            $serviceClient,
            'my-container',
            prefix: 'uploads',
            publicUrl: 'https://cdn.example.com',
        );

        self::assertSame(
            'https://cdn.example.com/uploads/file.txt',
            $adapter->publicUrl('file.txt'),
        );
    }

    #[Test]
    public function moveUsesCopyThenDelete(): void
    {
        $sourceUri = 'https://account.blob.core.windows.net/my-container/source.txt';

        $sourceClient = $this->createMock(BlobClient::class);
        $sourceClient->uri = $sourceUri;
        $sourceClient->expects($this->once())->method('delete');

        $destClient = $this->createMock(BlobClient::class);
        $destClient->expects($this->once())->method('copyFromUrl');

        $containerClient = $this->createMock(ContainerClient::class);
        $containerClient->method('getBlobClient')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'source.txt' => $sourceClient,
                'dest.txt' => $destClient,
            });

        $serviceClient = $this->createMock(BlobServiceClient::class);
        $serviceClient->method('getContainerClient')->willReturn($containerClient);

        $adapter = new AzureStorageAdapter($serviceClient, 'my-container');
        $adapter->move('source.txt', 'dest.txt');
    }

    private function createAdapter(BlobClient $blobClient): AzureStorageAdapter
    {
        $containerClient = $this->createMock(ContainerClient::class);
        $containerClient->method('getBlobClient')->willReturn($blobClient);

        $serviceClient = $this->createMock(BlobServiceClient::class);
        $serviceClient->method('getContainerClient')->willReturn($containerClient);

        return new AzureStorageAdapter($serviceClient, 'my-container');
    }
}
