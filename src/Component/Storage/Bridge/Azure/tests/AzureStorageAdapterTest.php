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

namespace WPPack\Component\Storage\Bridge\Azure\Tests;

use AzureOss\Storage\Blob\Models\BlobProperties;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use WPPack\Component\Storage\Bridge\Azure\AzureBlobClientInterface;
use WPPack\Component\Storage\Bridge\Azure\AzureStorageAdapter;
use WPPack\Component\Storage\Exception\ObjectNotFoundException;
use AzureOss\Storage\Blob\Exceptions\BlobNotFoundException;
use AzureOss\Storage\Blob\Models\Blob;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;

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
            ->willThrowException(new BlobNotFoundException('Blob not found'));

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

        self::assertTrue($adapter->fileExists('file.txt'));
    }

    #[Test]
    public function existsReturnsFalseWhenNotFound(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->willThrowException(new BlobNotFoundException('Blob not found'));

        $adapter = new AzureStorageAdapter($client);

        self::assertFalse($adapter->fileExists('nonexistent.txt'));
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

        self::assertSame('doc.pdf', $metadata->path);
        self::assertSame(1024, $metadata->size);
        self::assertSame('application/pdf', $metadata->mimeType);
        self::assertNotNull($metadata->lastModified);
    }

    #[Test]
    public function metadataThrowsObjectNotFoundExceptionOn404(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->willThrowException(new BlobNotFoundException('Blob not found'));

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

    #[Test]
    public function writeStreamCallsUpload(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'stream contents');
        rewind($resource);

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('upload')
            ->with('file.txt', $resource, null);

        $adapter = new AzureStorageAdapter($client);
        $adapter->writeStream('file.txt', $resource);

        fclose($resource);
    }

    #[Test]
    public function writeStreamWithContentType(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'stream contents');
        rewind($resource);

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('upload')
            ->with(
                'file.txt',
                $resource,
                $this->callback(fn(?UploadBlobOptions $options) => $options !== null && $options->contentType === 'image/png'),
            );

        $adapter = new AzureStorageAdapter($client);
        $adapter->writeStream('file.txt', $resource, ['Content-Type' => 'image/png']);

        fclose($resource);
    }

    #[Test]
    public function readStreamWithPrefix(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, true);
        $stream->method('read')->with(8192)->willReturn('stream contents');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('downloadStreamingContent')
            ->with('uploads/file.txt')
            ->willReturn($stream);

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $result = $adapter->readStream('file.txt');

        self::assertIsResource($result);
        self::assertSame('stream contents', stream_get_contents($result));
    }

    #[Test]
    public function readStreamThrowsObjectNotFoundExceptionOn404(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('downloadStreamingContent')
            ->willThrowException(new BlobNotFoundException('Blob not found'));

        $adapter = new AzureStorageAdapter($client);

        $this->expectException(ObjectNotFoundException::class);
        $adapter->readStream('nonexistent.txt');
    }

    #[Test]
    public function deleteMultipleCallsDeleteForEachKey(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('delete');

        $adapter = new AzureStorageAdapter($client);
        $adapter->deleteMultiple(['a.txt', 'b.txt']);
    }

    #[Test]
    public function deleteMultipleEmptyIsNoop(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->never())->method('delete');

        $adapter = new AzureStorageAdapter($client);
        $adapter->deleteMultiple([]);
    }

    #[Test]
    public function copyWithPrefix(): void
    {
        $sourceUri = new Uri('https://account.blob.core.windows.net/my-container/uploads/source.txt');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getBlobUri')
            ->with('uploads/source.txt')
            ->willReturn($sourceUri);
        $client->expects($this->once())
            ->method('syncCopyFromUri')
            ->with('uploads/dest.txt', $sourceUri);

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $adapter->copy('source.txt', 'dest.txt');
    }

    #[Test]
    public function existsWithPrefix(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->with('uploads/file.txt')
            ->willReturn($this->createBlobProperties());

        $adapter = new AzureStorageAdapter($client, 'uploads');

        self::assertTrue($adapter->fileExists('file.txt'));
    }

    #[Test]
    public function temporaryUrlReturnsUrl(): void
    {
        $sasUri = new Uri('https://account.blob.core.windows.net/my-container/file.txt?sv=2024&sig=abc');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('generateSasUri')
            ->willReturn($sasUri);

        $adapter = new AzureStorageAdapter($client);
        $url = $adapter->temporaryUrl('file.txt', new \DateTimeImmutable('+1 hour'));

        self::assertSame('https://account.blob.core.windows.net/my-container/file.txt?sv=2024&sig=abc', $url);
    }

    #[Test]
    public function temporaryUrlWithPrefix(): void
    {
        $sasUri = new Uri('https://account.blob.core.windows.net/my-container/uploads/file.txt?sv=2024&sig=abc');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('generateSasUri')
            ->with('uploads/file.txt', $this->isInstanceOf(BlobSasBuilder::class))
            ->willReturn($sasUri);

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $url = $adapter->temporaryUrl('file.txt', new \DateTimeImmutable('+1 hour'));

        self::assertSame('https://account.blob.core.windows.net/my-container/uploads/file.txt?sv=2024&sig=abc', $url);
    }

    #[Test]
    public function temporaryUploadUrlReturnsUrl(): void
    {
        $sasUri = new Uri('https://account.blob.core.windows.net/my-container/file.txt?sv=2024&sig=upload');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('generateSasUri')
            ->willReturn($sasUri);

        $adapter = new AzureStorageAdapter($client);
        $url = $adapter->temporaryUploadUrl('file.txt', new \DateTimeImmutable('+1 hour'));

        self::assertSame('https://account.blob.core.windows.net/my-container/file.txt?sv=2024&sig=upload', $url);
    }

    #[Test]
    public function temporaryUploadUrlWithPrefix(): void
    {
        $sasUri = new Uri('https://account.blob.core.windows.net/my-container/uploads/file.txt?sv=2024&sig=upload');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('generateSasUri')
            ->with('uploads/file.txt', $this->isInstanceOf(BlobSasBuilder::class))
            ->willReturn($sasUri);

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $url = $adapter->temporaryUploadUrl('file.txt', new \DateTimeImmutable('+1 hour'));

        self::assertSame('https://account.blob.core.windows.net/my-container/uploads/file.txt?sv=2024&sig=upload', $url);
    }

    #[Test]
    public function listContentsRecursiveYieldsObjects(): void
    {
        $now = new \DateTimeImmutable();

        $blob1 = new Blob('file1.txt', $this->createBlobProperties(100, 'text/plain', $now));
        $blob2 = new Blob('dir/file2.txt', $this->createBlobProperties(200, 'application/pdf', $now));

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('listBlobsByHierarchy')
            ->with(null, '')
            ->willReturn([$blob1, $blob2]);

        $adapter = new AzureStorageAdapter($client);
        $items = iterator_to_array($adapter->listContents('', deep: true));

        self::assertCount(2, $items);
        self::assertSame('file1.txt', $items[0]->path);
        self::assertSame(100, $items[0]->size);
        self::assertSame('text/plain', $items[0]->mimeType);
        self::assertSame('dir/file2.txt', $items[1]->path);
        self::assertSame(200, $items[1]->size);
        self::assertSame('application/pdf', $items[1]->mimeType);
    }

    #[Test]
    public function listContentsNonRecursivePassesDelimiter(): void
    {
        $now = new \DateTimeImmutable();

        $blob1 = new Blob('file1.txt', $this->createBlobProperties(100, 'text/plain', $now));

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('listBlobsByHierarchy')
            ->with(null, '/')
            ->willReturn([$blob1]);

        $adapter = new AzureStorageAdapter($client);
        $items = iterator_to_array($adapter->listContents('', deep: false));

        self::assertCount(1, $items);
        self::assertSame('file1.txt', $items[0]->path);
    }

    #[Test]
    public function writeWithHttpHeaders(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('upload')
            ->with(
                'file.txt',
                'contents',
                $this->callback(fn(?UploadBlobOptions $options) => $options !== null
                    && $options->contentType === 'text/plain'
                    && $options->httpHeaders !== null
                    && $options->httpHeaders->cacheControl === 'max-age=3600'),
            );

        $adapter = new AzureStorageAdapter($client);
        $adapter->write('file.txt', 'contents', [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'max-age=3600',
        ]);
    }

    #[Test]
    public function writeWithOnlyHttpHeadersNoContentType(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('upload')
            ->with(
                'file.txt',
                'contents',
                $this->callback(fn(?UploadBlobOptions $options) => $options !== null
                    && $options->contentType === null
                    && $options->httpHeaders !== null
                    && $options->httpHeaders->contentDisposition === 'attachment'),
            );

        $adapter = new AzureStorageAdapter($client);
        $adapter->write('file.txt', 'contents', [
            'Content-Disposition' => 'attachment',
        ]);
    }

    #[Test]
    public function readThrowsUnexpectedException(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('downloadStreamingContent')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $adapter = new AzureStorageAdapter($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection lost');
        $adapter->read('file.txt');
    }

    #[Test]
    public function existsThrowsUnexpectedException(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $adapter = new AzureStorageAdapter($client);

        $this->expectException(\RuntimeException::class);
        $adapter->fileExists('file.txt');
    }

    #[Test]
    public function metadataThrowsUnexpectedException(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $adapter = new AzureStorageAdapter($client);

        $this->expectException(\RuntimeException::class);
        $adapter->metadata('file.txt');
    }

    #[Test]
    public function listContentsWithPrefix(): void
    {
        $now = new \DateTimeImmutable();

        $blob1 = new Blob('uploads/file1.txt', $this->createBlobProperties(100, 'text/plain', $now));

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('listBlobsByHierarchy')
            ->willReturn([$blob1]);

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $items = iterator_to_array($adapter->listContents('', deep: true));

        self::assertCount(1, $items);
        self::assertSame('file1.txt', $items[0]->path);
    }

    #[Test]
    public function readStreamThrowsUnexpectedException(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('downloadStreamingContent')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $adapter = new AzureStorageAdapter($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection lost');
        $adapter->readStream('file.txt');
    }

    #[Test]
    public function listContentsYieldsBlobPrefixAsDirectory(): void
    {
        $now = new \DateTimeImmutable();

        $blob = new Blob('file1.txt', $this->createBlobProperties(100, 'text/plain', $now));
        // BlobPrefix entries are yielded as directory ObjectMetadata
        $prefix = new \AzureOss\Storage\Blob\Models\BlobPrefix('subdir/');

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('listBlobsByHierarchy')
            ->willReturn([$blob, $prefix]);

        $adapter = new AzureStorageAdapter($client);
        $items = iterator_to_array($adapter->listContents('', deep: false));

        self::assertCount(2, $items);
        self::assertSame('file1.txt', $items[0]->path);
        self::assertFalse($items[0]->isDirectory);
        self::assertSame('subdir/', $items[1]->path);
        self::assertTrue($items[1]->isDirectory);
    }

    #[Test]
    public function stripPathReturnsBlobPathWhenPrefixDoesNotMatch(): void
    {
        $now = new \DateTimeImmutable();

        // Blob name does NOT start with the configured prefix
        $blob = new Blob('other/file.txt', $this->createBlobProperties(50, 'text/plain', $now));

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('listBlobsByHierarchy')
            ->willReturn([$blob]);

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $items = iterator_to_array($adapter->listContents('', deep: true));

        self::assertCount(1, $items);
        // Since blob name doesn't start with "uploads/", it's returned as-is
        self::assertSame('other/file.txt', $items[0]->path);
    }

    #[Test]
    public function writeWithContentEncodingHeader(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('upload')
            ->with(
                'file.txt',
                'contents',
                $this->callback(fn(?UploadBlobOptions $options) => $options !== null
                    && $options->httpHeaders !== null
                    && $options->httpHeaders->contentEncoding === 'gzip'),
            );

        $adapter = new AzureStorageAdapter($client);
        $adapter->write('file.txt', 'contents', [
            'Content-Encoding' => 'gzip',
        ]);
    }

    #[Test]
    public function writeWithContentLanguageHeader(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('upload')
            ->with(
                'file.txt',
                'contents',
                $this->callback(fn(?UploadBlobOptions $options) => $options !== null
                    && $options->httpHeaders !== null
                    && $options->httpHeaders->contentLanguage === 'en-US'),
            );

        $adapter = new AzureStorageAdapter($client);
        $adapter->write('file.txt', 'contents', [
            'Content-Language' => 'en-US',
        ]);
    }

    #[Test]
    public function publicUrlWithPrefixButNoBlobUri(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getBlobUri')
            ->with('uploads/file.txt')
            ->willReturn(new Uri('https://account.blob.core.windows.net/container/uploads/file.txt'));

        $adapter = new AzureStorageAdapter($client, 'uploads');

        self::assertSame(
            'https://account.blob.core.windows.net/container/uploads/file.txt',
            $adapter->publicUrl('file.txt'),
        );
    }

    #[Test]
    public function deleteWithPrefix(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->expects($this->once())
            ->method('delete')
            ->with('uploads/file.txt');

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $adapter->delete('file.txt');
    }

    #[Test]
    public function metadataWithPrefix(): void
    {
        $now = new \DateTimeImmutable();

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('getProperties')
            ->with('uploads/doc.pdf')
            ->willReturn($this->createBlobProperties(2048, 'application/pdf', $now));

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $metadata = $adapter->metadata('doc.pdf');

        self::assertSame('doc.pdf', $metadata->path);
        self::assertSame(2048, $metadata->size);
    }

    #[Test]
    public function listContentsWithPrefixAndSubPrefix(): void
    {
        $now = new \DateTimeImmutable();

        $blob = new Blob('uploads/images/photo.jpg', $this->createBlobProperties(5000, 'image/jpeg', $now));

        $client = $this->createMock(AzureBlobClientInterface::class);
        $client->method('listBlobsByHierarchy')
            ->with('uploads/images/', '')
            ->willReturn([$blob]);

        $adapter = new AzureStorageAdapter($client, 'uploads');
        $items = iterator_to_array($adapter->listContents('images/', deep: true));

        self::assertCount(1, $items);
        self::assertSame('images/photo.jpg', $items[0]->path);
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
