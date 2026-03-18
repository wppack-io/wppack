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
use Google\Cloud\Core\Exception\NotFoundException;

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
            ->willThrowException(new NotFoundException('Not Found'));

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
            ->willThrowException(new NotFoundException('Not Found'));

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
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, true);
        $stream->method('read')->with(8192)->willReturn('stream contents');

        $object = $this->createMock(StorageObject::class);
        $object->method('downloadAsStream')->willReturn($stream);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);
        $result = $adapter->readStream('file.txt');

        self::assertIsResource($result);
        self::assertSame('stream contents', stream_get_contents($result));
    }

    #[Test]
    public function writeStreamCallsUpload(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'stream contents');
        rewind($resource);

        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('upload')
            ->with($resource, ['name' => 'file.txt']);

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->writeStream('file.txt', $resource);

        fclose($resource);
    }

    #[Test]
    public function writeStreamWithContentType(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'stream contents');
        rewind($resource);

        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('upload')
            ->with($resource, [
                'name' => 'file.txt',
                'metadata' => ['contentType' => 'image/png'],
            ]);

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->writeStream('file.txt', $resource, ['Content-Type' => 'image/png']);

        fclose($resource);
    }

    #[Test]
    public function readStreamWithPrefix(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, true);
        $stream->method('read')->with(8192)->willReturn('stream contents');

        $object = $this->createMock(StorageObject::class);
        $object->method('downloadAsStream')->willReturn($stream);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')
            ->with('uploads/file.txt')
            ->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket, 'uploads');
        $result = $adapter->readStream('file.txt');

        self::assertIsResource($result);
        self::assertSame('stream contents', stream_get_contents($result));
    }

    #[Test]
    public function readStreamThrowsObjectNotFoundExceptionOn404(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('downloadAsStream')
            ->willThrowException(new NotFoundException('Not Found'));

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);

        $this->expectException(ObjectNotFoundException::class);
        $adapter->readStream('nonexistent.txt');
    }

    #[Test]
    public function deleteMultipleCallsDeleteForEachKey(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->expects($this->exactly(2))->method('delete');

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->deleteMultiple(['a.txt', 'b.txt']);
    }

    #[Test]
    public function deleteMultipleEmptyIsNoop(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->never())->method('object');

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->deleteMultiple([]);
    }

    #[Test]
    public function copyWithPrefix(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->method('name')->willReturn('my-bucket');

        $object = $this->createMock(StorageObject::class);
        $object->expects($this->once())
            ->method('copy')
            ->with('my-bucket', ['name' => 'uploads/dest.txt']);

        $bucket->method('object')
            ->with('uploads/source.txt')
            ->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket, 'uploads');
        $adapter->copy('source.txt', 'dest.txt');
    }

    #[Test]
    public function existsWithPrefix(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('exists')->willReturn(true);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')
            ->with('uploads/file.txt')
            ->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket, 'uploads');

        self::assertTrue($adapter->exists('file.txt'));
    }

    #[Test]
    public function temporaryUrlReturnsSignedUrl(): void
    {
        $expiration = new \DateTimeImmutable('+1 hour');

        $object = $this->createMock(StorageObject::class);
        $object->method('signedUrl')
            ->with($expiration, ['version' => 'v4'])
            ->willReturn('https://storage.googleapis.com/my-bucket/file.txt?X-Goog-Signature=abc');

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);
        $url = $adapter->temporaryUrl('file.txt', $expiration);

        self::assertSame('https://storage.googleapis.com/my-bucket/file.txt?X-Goog-Signature=abc', $url);
    }

    #[Test]
    public function temporaryUrlWithPrefix(): void
    {
        $expiration = new \DateTimeImmutable('+1 hour');

        $object = $this->createMock(StorageObject::class);
        $object->method('signedUrl')
            ->willReturn('https://storage.googleapis.com/my-bucket/uploads/file.txt?X-Goog-Signature=abc');

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')
            ->with('uploads/file.txt')
            ->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket, 'uploads');
        $url = $adapter->temporaryUrl('file.txt', $expiration);

        self::assertSame('https://storage.googleapis.com/my-bucket/uploads/file.txt?X-Goog-Signature=abc', $url);
    }

    #[Test]
    public function listContentsRecursiveYieldsObjects(): void
    {
        $now = new \DateTimeImmutable();

        $object1 = $this->createMock(StorageObject::class);
        $object1->method('name')->willReturn('file1.txt');
        $object1->method('info')->willReturn([
            'size' => '100',
            'contentType' => 'text/plain',
            'updated' => $now->format(\DateTimeInterface::RFC3339),
        ]);

        $object2 = $this->createMock(StorageObject::class);
        $object2->method('name')->willReturn('dir/file2.txt');
        $object2->method('info')->willReturn([
            'size' => '200',
            'contentType' => 'application/pdf',
            'updated' => $now->format(\DateTimeInterface::RFC3339),
        ]);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('objects')
            ->with(['prefix' => ''])
            ->willReturn([$object1, $object2]);

        $adapter = new GcsStorageAdapter($bucket);
        $items = iterator_to_array($adapter->listContents('', true));

        self::assertCount(2, $items);
        self::assertSame('file1.txt', $items[0]->key);
        self::assertSame(100, $items[0]->size);
        self::assertSame('text/plain', $items[0]->mimeType);
        self::assertSame('dir/file2.txt', $items[1]->key);
        self::assertSame(200, $items[1]->size);
        self::assertSame('application/pdf', $items[1]->mimeType);
    }

    #[Test]
    public function listContentsNonRecursivePassesDelimiter(): void
    {
        $now = new \DateTimeImmutable();

        $object1 = $this->createMock(StorageObject::class);
        $object1->method('name')->willReturn('file1.txt');
        $object1->method('info')->willReturn([
            'size' => '100',
            'contentType' => 'text/plain',
            'updated' => $now->format(\DateTimeInterface::RFC3339),
        ]);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('objects')
            ->with(['prefix' => '', 'delimiter' => '/'])
            ->willReturn([$object1]);

        $adapter = new GcsStorageAdapter($bucket);
        $items = iterator_to_array($adapter->listContents('', false));

        self::assertCount(1, $items);
        self::assertSame('file1.txt', $items[0]->key);
    }

    #[Test]
    public function readThrowsUnexpectedException(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('downloadAsString')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);

        $this->expectException(\RuntimeException::class);
        $adapter->read('file.txt');
    }

    #[Test]
    public function readStreamThrowsUnexpectedException(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('downloadAsStream')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);

        $this->expectException(\RuntimeException::class);
        $adapter->readStream('file.txt');
    }

    #[Test]
    public function metadataThrowsUnexpectedException(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('info')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);

        $this->expectException(\RuntimeException::class);
        $adapter->metadata('file.txt');
    }

    #[Test]
    public function metadataWithMinimalInfo(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('info')->willReturn([]);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('object')->willReturn($object);

        $adapter = new GcsStorageAdapter($bucket);
        $metadata = $adapter->metadata('file.txt');

        self::assertSame('file.txt', $metadata->key);
        self::assertNull($metadata->size);
        self::assertNull($metadata->lastModified);
        self::assertNull($metadata->mimeType);
    }

    #[Test]
    public function writeWithCustomMetadata(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('upload')
            ->with('contents', [
                'name' => 'file.txt',
                'metadata' => [
                    'contentType' => 'text/plain',
                    'metadata' => ['x-custom' => 'value'],
                ],
            ]);

        $adapter = new GcsStorageAdapter($bucket);
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

        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('upload')
            ->with($resource, [
                'name' => 'file.txt',
                'metadata' => ['metadata' => ['x-custom' => 'value']],
            ]);

        $adapter = new GcsStorageAdapter($bucket);
        $adapter->writeStream('file.txt', $resource, ['x-custom' => 'value']);

        fclose($resource);
    }

    #[Test]
    public function listContentsWithPrefix(): void
    {
        $now = new \DateTimeImmutable();

        $object1 = $this->createMock(StorageObject::class);
        $object1->method('name')->willReturn('uploads/file1.txt');
        $object1->method('info')->willReturn([
            'size' => '100',
            'contentType' => 'text/plain',
            'updated' => $now->format(\DateTimeInterface::RFC3339),
        ]);

        $bucket = $this->createMock(Bucket::class);
        $bucket->method('objects')
            ->with(['prefix' => 'uploads/sub/'])
            ->willReturn([$object1]);

        $adapter = new GcsStorageAdapter($bucket, 'uploads');
        $items = iterator_to_array($adapter->listContents('sub/', true));

        self::assertCount(1, $items);
        self::assertSame('file1.txt', $items[0]->key);
    }
}
