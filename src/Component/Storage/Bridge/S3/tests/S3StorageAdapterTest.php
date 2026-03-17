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
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

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

        self::assertTrue($adapter->exists('file.txt'));
    }

    #[Test]
    public function existsReturnsFalseWhenNotFound(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('headObject')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

        $adapter = new S3StorageAdapter($s3Client, 'my-bucket');

        self::assertFalse($adapter->exists('nonexistent.txt'));
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

        self::assertSame('doc.pdf', $metadata->key);
        self::assertSame(1024, $metadata->size);
        self::assertSame('application/pdf', $metadata->mimeType);
        self::assertNotNull($metadata->lastModified);
    }

    #[Test]
    public function metadataThrowsObjectNotFoundExceptionOn404(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $s3Client->method('headObject')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

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
}
