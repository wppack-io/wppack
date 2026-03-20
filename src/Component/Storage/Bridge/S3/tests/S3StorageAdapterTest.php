<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\S3\Tests;

use AsyncAws\Core\Stream\ResultStream;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\S3\Result\CopyObjectOutput;
use AsyncAws\S3\Result\DeleteObjectOutput;
use AsyncAws\S3\Result\DeleteObjectsOutput;
use AsyncAws\S3\Result\GetObjectOutput;
use AsyncAws\S3\Result\HeadObjectOutput;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Bridge\S3\S3StorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use AsyncAws\S3\Exception\NoSuchKeyException;
use AsyncAws\S3\Result\ListObjectsV2Output;
use AsyncAws\S3\ValueObject\AwsObject;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(S3StorageAdapter::class)]
final class S3StorageAdapterTest extends TestCase
{
    #[Test]
    public function getName(): void
    {
        $adapter = new S3StorageAdapter(
            $this->createMock(S3Client::class),
            'my-bucket',
        );

        self::assertSame('s3', $adapter->getName());
    }

    #[Test]
    public function writeCallsPutObject(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('putObject')
            ->willReturn(ResultMockFactory::create(PutObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->write('file.txt', 'contents');
    }

    #[Test]
    public function writeWithPrefix(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('putObject')
            ->with($this->callback(function ($input) {
                return $input->getKey() === 'uploads/file.txt';
            }))
            ->willReturn(ResultMockFactory::create(PutObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket', 'uploads');
        $adapter->write('file.txt', 'contents');
    }

    #[Test]
    public function writeWithContentType(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('putObject')
            ->with($this->callback(function ($input) {
                return $input->getContentType() === 'text/plain';
            }))
            ->willReturn(ResultMockFactory::create(PutObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->write('file.txt', 'contents', ['Content-Type' => 'text/plain']);
    }

    #[Test]
    public function readReturnsContents(): void
    {
        $resultStream = $this->createMock(ResultStream::class);
        $resultStream->method('getContentAsString')->willReturn('file contents');

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('getObject')
            ->willReturn(ResultMockFactory::create(GetObjectOutput::class, [
                'Body' => $resultStream,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        self::assertSame('file contents', $adapter->read('file.txt'));
    }

    #[Test]
    public function readStreamReturnsResource(): void
    {
        $resultStream = $this->createMock(ResultStream::class);
        $resultStream->method('getChunks')->willReturn(['stream ', 'contents']);

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('getObject')
            ->willReturn(ResultMockFactory::create(GetObjectOutput::class, [
                'Body' => $resultStream,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $result = $adapter->readStream('file.txt');

        self::assertIsResource($result);
        self::assertSame('stream contents', stream_get_contents($result));
    }

    #[Test]
    public function readThrowsObjectNotFoundExceptionOn404(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('getObject')
            ->willThrowException($this->createNoSuchKeyException());

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        $this->expectException(ObjectNotFoundException::class);
        $adapter->read('nonexistent.txt');
    }

    #[Test]
    public function deleteCallsDeleteObject(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('deleteObject')
            ->willReturn(ResultMockFactory::create(DeleteObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->delete('file.txt');
    }

    #[Test]
    public function deleteMultipleCallsDeleteObjects(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('deleteObjects')
            ->willReturn(ResultMockFactory::create(DeleteObjectsOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->deleteMultiple(['a.txt', 'b.txt']);
    }

    #[Test]
    public function deleteMultipleEmptyIsNoop(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->never())->method('deleteObjects');

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->deleteMultiple([]);
    }

    #[Test]
    public function existsReturnsTrueWhenObjectExists(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('headObject')
            ->willReturn(ResultMockFactory::create(HeadObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        self::assertTrue($adapter->fileExists('file.txt'));
    }

    #[Test]
    public function existsReturnsFalseWhenNotFound(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('headObject')
            ->willThrowException($this->createNoSuchKeyException());

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        self::assertFalse($adapter->fileExists('nonexistent.txt'));
    }

    #[Test]
    public function copyCallsCopyObject(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('copyObject')
            ->with($this->callback(function ($input) {
                return $input->getCopySource() === 'my-bucket/source.txt'
                    && $input->getKey() === 'dest.txt';
            }))
            ->willReturn(ResultMockFactory::create(CopyObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->copy('source.txt', 'dest.txt');
    }

    #[Test]
    public function metadataReturnsObjectMetadata(): void
    {
        $now = new \DateTimeImmutable();
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('headObject')
            ->willReturn(ResultMockFactory::create(HeadObjectOutput::class, [
                'ContentLength' => 1024,
                'ContentType' => 'application/pdf',
                'LastModified' => $now,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $metadata = $adapter->metadata('doc.pdf');

        self::assertSame('doc.pdf', $metadata->path);
        self::assertSame(1024, $metadata->size);
        self::assertSame('application/pdf', $metadata->mimeType);
        self::assertNotNull($metadata->lastModified);
    }

    #[Test]
    public function metadataThrowsObjectNotFoundExceptionOn404(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('headObject')
            ->willThrowException($this->createNoSuchKeyException());

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        $this->expectException(ObjectNotFoundException::class);
        $adapter->metadata('nonexistent.txt');
    }

    #[Test]
    public function publicUrlReturnsS3DirectUrl(): void
    {
        $adapter = new S3StorageAdapter(
            $this->createMock(S3Client::class),
            'my-bucket',
        );

        self::assertSame(
            'https://my-bucket.s3.amazonaws.com/path/to/file.txt',
            $adapter->publicUrl('path/to/file.txt'),
        );
    }

    #[Test]
    public function publicUrlReturnsCustomPublicUrl(): void
    {
        $adapter = new S3StorageAdapter(
            $this->createMock(S3Client::class),
            'my-bucket',
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
        $adapter = new S3StorageAdapter(
            $this->createMock(S3Client::class),
            'my-bucket',
            prefix: 'uploads',
        );

        self::assertSame(
            'https://my-bucket.s3.amazonaws.com/uploads/file.txt',
            $adapter->publicUrl('file.txt'),
        );
    }

    #[Test]
    public function moveUsesCopyThenDelete(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('copyObject')
            ->willReturn(ResultMockFactory::create(CopyObjectOutput::class));
        $s3Client->expects($this->once())
            ->method('deleteObject')
            ->willReturn(ResultMockFactory::create(DeleteObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->move('source.txt', 'dest.txt');
    }

    #[Test]
    public function temporaryUrlReturnsPresignedUrl(): void
    {
        $s3Client = new S3Client([
            'region' => 'us-east-1',
            'accessKeyId' => 'AKIAIOSFODNN7EXAMPLE',
            'accessKeySecret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        ]);

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $url = $adapter->temporaryUrl('file.txt', new \DateTimeImmutable('+1 hour'));

        self::assertStringContainsString('my-bucket', $url);
        self::assertStringContainsString('file.txt', $url);
        self::assertStringContainsString('X-Amz-Signature', $url);
    }

    #[Test]
    public function listContentsRecursiveYieldsObjects(): void
    {
        $now = new \DateTimeImmutable();

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('listObjectsV2')
            ->willReturn(ResultMockFactory::create(ListObjectsV2Output::class, [
                'Contents' => [
                    new AwsObject(['Key' => 'file1.txt', 'Size' => 100, 'LastModified' => $now]),
                    new AwsObject(['Key' => 'dir/file2.txt', 'Size' => 200, 'LastModified' => $now]),
                ],
                'CommonPrefixes' => [],
                'IsTruncated' => false,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $items = iterator_to_array($adapter->listContents('', deep: true));

        self::assertCount(2, $items);
        self::assertSame('file1.txt', $items[0]->path);
        self::assertSame(100, $items[0]->size);
        self::assertSame('dir/file2.txt', $items[1]->path);
        self::assertSame(200, $items[1]->size);
    }

    #[Test]
    public function listContentsNonRecursivePassesDelimiter(): void
    {
        $now = new \DateTimeImmutable();

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('listObjectsV2')
            ->with($this->callback(function ($input) {
                return $input->getDelimiter() === '/';
            }))
            ->willReturn(ResultMockFactory::create(ListObjectsV2Output::class, [
                'Contents' => [
                    new AwsObject(['Key' => 'file1.txt', 'Size' => 100, 'LastModified' => $now]),
                ],
                'CommonPrefixes' => [],
                'IsTruncated' => false,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $items = iterator_to_array($adapter->listContents('', deep: false));

        self::assertCount(1, $items);
        self::assertSame('file1.txt', $items[0]->path);
    }

    #[Test]
    public function writeStreamCallsPutObject(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'stream contents');
        rewind($resource);

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('putObject')
            ->willReturn(ResultMockFactory::create(PutObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->writeStream('file.txt', $resource);

        fclose($resource);
    }

    #[Test]
    public function writeStreamWithContentType(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'stream contents');
        rewind($resource);

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('putObject')
            ->with($this->callback(function ($input) {
                return $input->getContentType() === 'image/png';
            }))
            ->willReturn(ResultMockFactory::create(PutObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->writeStream('file.txt', $resource, ['Content-Type' => 'image/png']);

        fclose($resource);
    }

    #[Test]
    public function writeStreamWithPrefix(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'data');
        rewind($resource);

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('putObject')
            ->with($this->callback(function ($input) {
                return $input->getKey() === 'uploads/file.txt';
            }))
            ->willReturn(ResultMockFactory::create(PutObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket', 'uploads');
        $adapter->writeStream('file.txt', $resource);

        fclose($resource);
    }

    #[Test]
    public function writeWithCustomMetadata(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('putObject')
            ->with($this->callback(function ($input) {
                return $input->getContentType() === 'text/plain'
                    && $input->getMetadata()['x-custom'] === 'value';
            }))
            ->willReturn(ResultMockFactory::create(PutObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->write('file.txt', 'contents', [
            'Content-Type' => 'text/plain',
            'x-custom' => 'value',
        ]);
    }

    #[Test]
    public function writeStreamWithCustomMetadata(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'data');
        rewind($resource);

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('putObject')
            ->with($this->callback(function ($input) {
                return $input->getMetadata()['x-custom'] === 'value';
            }))
            ->willReturn(ResultMockFactory::create(PutObjectOutput::class));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $adapter->writeStream('file.txt', $resource, ['x-custom' => 'value']);

        fclose($resource);
    }

    #[Test]
    public function readStreamThrowsObjectNotFoundExceptionOn404(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('getObject')
            ->willThrowException($this->createNoSuchKeyException());

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        $this->expectException(ObjectNotFoundException::class);
        $adapter->readStream('nonexistent.txt');
    }

    #[Test]
    public function readStreamWithPrefix(): void
    {
        $resultStream = $this->createMock(ResultStream::class);
        $resultStream->method('getChunks')->willReturn(['stream contents']);

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->expects($this->once())
            ->method('getObject')
            ->with($this->callback(function ($input) {
                return $input->getKey() === 'uploads/file.txt';
            }))
            ->willReturn(ResultMockFactory::create(GetObjectOutput::class, [
                'Body' => $resultStream,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket', 'uploads');
        $result = $adapter->readStream('file.txt');

        self::assertIsResource($result);
        self::assertSame('stream contents', stream_get_contents($result));
    }

    #[Test]
    public function readThrowsUnexpectedException(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('getObject')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection lost');
        $adapter->read('file.txt');
    }

    #[Test]
    public function readStreamThrowsUnexpectedException(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('getObject')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection lost');
        $adapter->readStream('file.txt');
    }

    #[Test]
    public function existsThrowsUnexpectedException(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('headObject')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        $this->expectException(\RuntimeException::class);
        $adapter->fileExists('file.txt');
    }

    #[Test]
    public function metadataThrowsUnexpectedException(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('headObject')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        $this->expectException(\RuntimeException::class);
        $adapter->metadata('file.txt');
    }

    #[Test]
    public function metadataWithNullLastModified(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('headObject')
            ->willReturn(ResultMockFactory::create(HeadObjectOutput::class, [
                'ContentLength' => 512,
                'ContentType' => 'text/plain',
                'LastModified' => null,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $metadata = $adapter->metadata('file.txt');

        self::assertSame('file.txt', $metadata->path);
        self::assertSame(512, $metadata->size);
        self::assertNull($metadata->lastModified);
    }

    #[Test]
    public function listContentsSkipsObjectsWithNullKey(): void
    {
        $now = new \DateTimeImmutable();

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('listObjectsV2')
            ->willReturn(ResultMockFactory::create(ListObjectsV2Output::class, [
                'Contents' => [
                    new AwsObject(['Key' => null, 'Size' => 0]),
                    new AwsObject(['Key' => 'file.txt', 'Size' => 100, 'LastModified' => $now]),
                ],
                'CommonPrefixes' => [],
                'IsTruncated' => false,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $items = iterator_to_array($adapter->listContents('', deep: true));

        self::assertCount(1, $items);
        self::assertSame('file.txt', $items[0]->path);
    }

    #[Test]
    public function listContentsWithNullLastModified(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('listObjectsV2')
            ->willReturn(ResultMockFactory::create(ListObjectsV2Output::class, [
                'Contents' => [
                    new AwsObject(['Key' => 'file.txt', 'Size' => 100, 'LastModified' => null]),
                ],
                'CommonPrefixes' => [],
                'IsTruncated' => false,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');
        $items = iterator_to_array($adapter->listContents('', deep: true));

        self::assertCount(1, $items);
        self::assertNull($items[0]->lastModified);
    }

    #[Test]
    public function listContentsWithPrefixStripsPrefix(): void
    {
        $now = new \DateTimeImmutable();

        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('listObjectsV2')
            ->willReturn(ResultMockFactory::create(ListObjectsV2Output::class, [
                'Contents' => [
                    new AwsObject(['Key' => 'uploads/file.txt', 'Size' => 100, 'LastModified' => $now]),
                ],
                'CommonPrefixes' => [],
                'IsTruncated' => false,
            ]));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket', 'uploads');
        $items = iterator_to_array($adapter->listContents('', deep: true));

        self::assertCount(1, $items);
        self::assertSame('file.txt', $items[0]->path);
    }

    #[Test]
    public function publicUrlWithCustomPublicUrlAndPrefix(): void
    {
        $adapter = new S3StorageAdapter(
            $this->createMock(S3Client::class),
            'my-bucket',
            prefix: 'uploads',
            publicUrl: 'https://cdn.example.com',
        );

        self::assertSame(
            'https://cdn.example.com/uploads/file.txt',
            $adapter->publicUrl('file.txt'),
        );
    }

    private function createNoSuchKeyException(): NoSuchKeyException
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getInfo')
            ->willReturnCallback(fn(string $type) => match ($type) {
                'http_code' => 404,
                'url' => 'https://my-bucket.s3.amazonaws.com/nonexistent.txt',
                default => null,
            });
        $response->method('getHeaders')
            ->willReturn([]);
        $response->method('getContent')
            ->willReturn('');

        return new NoSuchKeyException($response);
    }
}
