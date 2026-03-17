<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\Gcs\Tests;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use WpPack\Component\Storage\Bridge\Gcs\GcsStorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;

#[CoversClass(GcsStorageAdapter::class)]
final class GcsStorageAdapterTest extends TestCase
{
    #[Test]
    public function getName(): void
    {
        $adapter = new GcsStorageAdapter(
            $this->createMock(Bucket::class),
        );

        self::assertSame('gcs', $adapter->getName());
    }

    #[Test]
    public function writeCallsUpload(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('upload')
            ->with('contents', ['name' => 'file.txt']);

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->write('file.txt', 'contents');
    }

    #[Test]
    public function writeWithPrefix(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('upload')
            ->with('contents', ['name' => 'uploads/file.txt']);

        $adapter = new GcsStorageAdapter($bucket, 'uploads');
        $adapter->write('file.txt', 'contents');
    }

    #[Test]
    public function writeWithContentType(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('upload')
            ->with('contents', [
                'name' => 'file.txt',
                'metadata' => ['contentType' => 'text/plain'],
            ]);

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->write('file.txt', 'contents', ['Content-Type' => 'text/plain']);
    }

    #[Test]
    public function readReturnsContents(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('downloadAsString')->willReturn('file contents');

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);

        self::assertSame('file contents', $adapter->read('file.txt'));
    }

    #[Test]
    public function readThrowsObjectNotFoundExceptionOn404(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('downloadAsString')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);

        $this->expectException(ObjectNotFoundException::class);
        $adapter->read('nonexistent.txt');
    }

    #[Test]
    public function deleteCallsDelete(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->expects($this->once())->method('delete');

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->delete('file.txt');
    }

    #[Test]
    public function existsReturnsTrueWhenObjectExists(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('exists')->willReturn(true);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);

        self::assertTrue($adapter->exists('file.txt'));
    }

    #[Test]
    public function existsReturnsFalseWhenNotFound(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('exists')->willReturn(false);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);

        self::assertFalse($adapter->exists('nonexistent.txt'));
    }

    #[Test]
    public function copyCallsCopy(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->method('name')->willReturn('my-bucket');

        $object = $this->createMock(StorageObject::class);
        $object->expects($this->once())
            ->method('copy')
            ->with('my-bucket', ['name' => 'dest.txt']);

        $bucket->method('object')
            ->with('source.txt')
            ->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->copy('source.txt', 'dest.txt');
    }

    #[Test]
    public function metadataReturnsObjectInfo(): void
    {
        $now = new \DateTimeImmutable();

        $object = $this->createMock(StorageObject::class);
        $object->method('info')->willReturn([
            'size' => '1024',
            'contentType' => 'application/pdf',
            'updated' => $now->format(\DateTimeInterface::RFC3339),
        ]);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);
        $metadata = $adapter->metadata('doc.pdf');

        self::assertSame('doc.pdf', $metadata->key);
        self::assertSame(1024, $metadata->size);
        self::assertSame('application/pdf', $metadata->mimeType);
        self::assertNotNull($metadata->lastModified);
    }

    #[Test]
    public function metadataThrowsObjectNotFoundExceptionOn404(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('info')
            ->willThrowException(new \RuntimeException('HTTP 404 Not Found'));

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);

        $this->expectException(ObjectNotFoundException::class);
        $adapter->metadata('nonexistent.txt');
    }

    #[Test]
    public function publicUrlReturnsGcsDirectUrl(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->method('name')->willReturn('my-bucket');

        $adapter = new GcsStorageAdapter($bucket);

        self::assertSame(
            'https://storage.googleapis.com/my-bucket/path/to/file.txt',
            $adapter->publicUrl('path/to/file.txt'),
        );
    }

    #[Test]
    public function publicUrlReturnsCustomPublicUrl(): void
    {
        $adapter = new GcsStorageAdapter(
            $this->createMock(Bucket::class),
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
        $bucket = $this->createMock(Bucket::class);
        $bucket->method('name')->willReturn('my-bucket');

        $adapter = new GcsStorageAdapter(
            $bucket,
            prefix: 'uploads',
        );

        self::assertSame(
            'https://storage.googleapis.com/my-bucket/uploads/file.txt',
            $adapter->publicUrl('file.txt'),
        );
    }

    #[Test]
    public function moveUsesCopyThenDelete(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->method('name')->willReturn('my-bucket');

        $sourceObject = $this->createMock(StorageObject::class);
        $sourceObject->expects($this->once())
            ->method('copy')
            ->with('my-bucket', ['name' => 'dest.txt']);
        $sourceObject->expects($this->once())
            ->method('delete');

        $bucket->method('object')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'source.txt' => $sourceObject,
                default => $this->createMock(StorageObject::class),
            });

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->move('source.txt', 'dest.txt');
    }

    #[Test]
    public function readStreamReturnsResource(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('stream contents');

        $object = $this->createMock(StorageObject::class);
        $object->method('downloadAsStream')->willReturn($stream);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);
        $result = $adapter->readStream('file.txt');

        self::assertIsResource($result);
        self::assertSame('stream contents', stream_get_contents($result));
    }
}
