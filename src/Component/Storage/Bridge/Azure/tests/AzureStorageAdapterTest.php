<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\Azure\Tests;

use AzureOss\Storage\Blob\Models\BlobProperties;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use WpPack\Component\Storage\Bridge\Azure\AzureBlobClientInterface;
use WpPack\Component\Storage\Bridge\Azure\AzureStorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;

#[CoversClass(AzureStorageAdapter::class)]
final class AzureStorageAdapterTest extends TestCase
{
    #[Test]
    public function getName(): void
    {
        $adapter = new AzureStorageAdapter(
            $this->createMock(AzureBlobClientInterface::class),
        );

        self::assertSame('azure', $adapter->getName());
    }

    #[Test]
    public function writeCallsUpload(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('upload')
            ->with('file.txt', 'contents', null);

        $adapter = new AzureStorageAdapter($client);
        $adapter->write('file.txt', 'contents');
    }

    #[Test]
    public function writeWithPrefix(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('upload')
            ->with('uploads/file.txt', 'contents', null);

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $adapter->write('file.txt', 'contents');
    }

    #[Test]
    public function writeWithContentType(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('upload')
            ->with(
                'file.txt',
                'contents',
                $this->callback(fn(?UploadBlobOptions $options) => $options !== null && $options->contentType === 'text/plain'),
            );

        $adapter = new AzureStorageAdapter($client);
        $adapter->write('file.txt', 'contents', ['Content-Type' => 'text/plain']);
    }

    #[Test]
    public function readReturnsContents(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('file contents');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('downloadStreamingContent')
            ->with('file.txt')
            ->willReturn($stream);

        $adapter = new AzureStorageAdapter($client);

        self::assertSame('file contents', $adapter->read('file.txt'));
    }

    #[Test]
    public function readStreamReturnsResource(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, true);
        $stream->method('read')->with(8192)->willReturn('stream contents');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('downloadStreamingContent')
            ->with('file.txt')
            ->willReturn($stream);

        $adapter = new AzureStorageAdapter($client);
        $result = $adapter->readStream('file.txt');

        self::assertIsResource($result);
        self::assertSame('stream contents', stream_get_contents($result));
    }

    #[Test]
    public function readThrowsObjectNotFoundExceptionOn404(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('downloadStreamingContent')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

        $adapter = new AzureStorageAdapter($client);

        $this->expectException(ObjectNotFoundException::class);
        $adapter->read('nonexistent.txt');
    }

    #[Test]
    public function deleteCallsDelete(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('delete')
            ->with('file.txt');

        $adapter = new AzureStorageAdapter($client);
        $adapter->delete('file.txt');
    }

    #[Test]
    public function existsReturnsTrueWhenBlobExists(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->willReturn($this->createBlobProperties());

        $adapter = new AzureStorageAdapter($client);

        self::assertTrue($adapter->exists('file.txt'));
    }

    #[Test]
    public function existsReturnsFalseWhenNotFound(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

        $adapter = new AzureStorageAdapter($client);

        self::assertFalse($adapter->exists('nonexistent.txt'));
    }

    #[Test]
    public function copySyncsCopyFromUri(): void
    {
        $sourceUri = new Uri('https://account.blob.core.windows.net/my-container/source.txt');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getBlobUri')
            ->with('source.txt')
            ->willReturn($sourceUri);
        $client->expects($this->once())
            ->method('syncCopyFromUri')
            ->with('dest.txt', $sourceUri);

        $adapter = new AzureStorageAdapter($client);
        $adapter->copy('source.txt', 'dest.txt');
    }

    #[Test]
    public function metadataReturnsBlobProperties(): void
    {
        $now = new \DateTimeImmutable();

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->willReturn($this->createBlobProperties(1024, 'application/pdf', $now));

        $adapter = new AzureStorageAdapter($client);
        $metadata = $adapter->metadata('doc.pdf');

        self::assertSame('doc.pdf', $metadata->key);
        self::assertSame(1024, $metadata->size);
        self::assertSame('application/pdf', $metadata->mimeType);
        self::assertNotNull($metadata->lastModified);
    }

    #[Test]
    public function metadataThrowsObjectNotFoundExceptionOn404(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

        $adapter = new AzureStorageAdapter($client);

        $this->expectException(ObjectNotFoundException::class);
        $adapter->metadata('nonexistent.txt');
    }

    #[Test]
    public function publicUrlReturnsBlobDirectUrl(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getBlobUri')
            ->with('path/to/file.txt')
            ->willReturn(new Uri('https://account.blob.core.windows.net/my-container/path/to/file.txt'));

        $adapter = new AzureStorageAdapter($client);

        self::assertSame(
            'https://account.blob.core.windows.net/my-container/path/to/file.txt',
            $adapter->publicUrl('path/to/file.txt'),
        );
    }

    #[Test]
    public function publicUrlReturnsCustomPublicUrl(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);

        $adapter = new AzureStorageAdapter(
            $client,
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
        $client = $this->createMock(AzureBlobClientInterface::class);

        $adapter = new AzureStorageAdapter(
            $client,
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
        $sourceUri = new Uri('https://account.blob.core.windows.net/my-container/source.txt');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getBlobUri')
            ->with('source.txt')
            ->willReturn($sourceUri);
        $client->expects($this->once())
            ->method('syncCopyFromUri')
            ->with('dest.txt', $sourceUri);
        $client->expects($this->once())
            ->method('delete')
            ->with('source.txt');

        $adapter = new AzureStorageAdapter($client);
        $adapter->move('source.txt', 'dest.txt');
    }

    /**
     * @phpstan-ignore method.deprecated
     */
    private function createBlobProperties(
        int $contentLength = 0,
        string $contentType = 'application/octet-stream',
        ?\DateTimeInterface $lastModified = null,
    ): BlobProperties {
        return new BlobProperties(
            lastModified: $lastModified ?? new \DateTimeImmutable(),
            contentLength: $contentLength,
            contentType: $contentType,
            contentMD5: null,
            metadata: [],
        );
    }
}
