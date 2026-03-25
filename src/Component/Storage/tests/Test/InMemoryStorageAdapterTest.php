<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Storage\Tests\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;
use WpPack\Component\Storage\Test\InMemoryStorageAdapter;

#[CoversClass(InMemoryStorageAdapter::class)]
final class InMemoryStorageAdapterTest extends TestCase
{
    private InMemoryStorageAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryStorageAdapter();
    }

    #[Test]
    public function getName(): void
    {
        self::assertSame('in-memory', $this->adapter->getName());
    }

    #[Test]
    public function writeAndRead(): void
    {
        $this->adapter->write('file.txt', 'hello world');

        self::assertSame('hello world', $this->adapter->read('file.txt'));
    }

    #[Test]
    public function writeWithMetadata(): void
    {
        $this->adapter->write('file.txt', 'contents', ['Content-Type' => 'text/plain']);

        $metadata = $this->adapter->metadata('file.txt');
        self::assertSame('text/plain', $metadata->mimeType);
    }

    #[Test]
    public function writeStreamAndRead(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'stream contents');
        rewind($stream);

        $this->adapter->writeStream('file.txt', $stream);

        self::assertSame('stream contents', $this->adapter->read('file.txt'));

        fclose($stream);
    }

    #[Test]
    public function readThrowsForMissingObject(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        $this->adapter->read('nonexistent.txt');
    }

    #[Test]
    public function readStream(): void
    {
        $this->adapter->write('file.txt', 'stream test');

        $stream = $this->adapter->readStream('file.txt');
        self::assertIsResource($stream);
        self::assertSame('stream test', stream_get_contents($stream));

        fclose($stream);
    }

    #[Test]
    public function readStreamThrowsForMissingObject(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        $this->adapter->readStream('nonexistent.txt');
    }

    #[Test]
    public function delete(): void
    {
        $this->adapter->write('file.txt', 'contents');
        $this->adapter->delete('file.txt');

        self::assertFalse($this->adapter->fileExists('file.txt'));
    }

    #[Test]
    public function deleteNonexistentIsNoop(): void
    {
        $this->adapter->delete('nonexistent.txt');

        self::assertFalse($this->adapter->fileExists('nonexistent.txt'));
    }

    #[Test]
    public function deleteMultiple(): void
    {
        $this->adapter->write('a.txt', 'a');
        $this->adapter->write('b.txt', 'b');
        $this->adapter->write('c.txt', 'c');

        $this->adapter->deleteMultiple(['a.txt', 'b.txt']);

        self::assertFalse($this->adapter->fileExists('a.txt'));
        self::assertFalse($this->adapter->fileExists('b.txt'));
        self::assertTrue($this->adapter->fileExists('c.txt'));
    }

    #[Test]
    public function fileExistsCheck(): void
    {
        self::assertFalse($this->adapter->fileExists('file.txt'));

        $this->adapter->write('file.txt', 'contents');

        self::assertTrue($this->adapter->fileExists('file.txt'));
    }

    #[Test]
    public function createDirectory(): void
    {
        $this->adapter->createDirectory('my-dir');

        self::assertTrue($this->adapter->directoryExists('my-dir'));
    }

    #[Test]
    public function deleteDirectory(): void
    {
        $this->adapter->createDirectory('my-dir');
        $this->adapter->write('my-dir/file.txt', 'contents');

        $this->adapter->deleteDirectory('my-dir');

        self::assertFalse($this->adapter->directoryExists('my-dir'));
        self::assertFalse($this->adapter->fileExists('my-dir/file.txt'));
    }

    #[Test]
    public function directoryExistsCheck(): void
    {
        self::assertFalse($this->adapter->directoryExists('my-dir'));

        $this->adapter->createDirectory('my-dir');

        self::assertTrue($this->adapter->directoryExists('my-dir'));
    }

    #[Test]
    public function directoryExistsImpliedByObjects(): void
    {
        $this->adapter->write('uploads/file.txt', 'contents');

        self::assertTrue($this->adapter->directoryExists('uploads'));
    }

    #[Test]
    public function copy(): void
    {
        $this->adapter->write('source.txt', 'contents');
        $this->adapter->copy('source.txt', 'dest.txt');

        self::assertSame('contents', $this->adapter->read('source.txt'));
        self::assertSame('contents', $this->adapter->read('dest.txt'));
    }

    #[Test]
    public function copyThrowsForMissingSource(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        $this->adapter->copy('nonexistent.txt', 'dest.txt');
    }

    #[Test]
    public function move(): void
    {
        $this->adapter->write('source.txt', 'contents');
        $this->adapter->move('source.txt', 'dest.txt');

        self::assertFalse($this->adapter->fileExists('source.txt'));
        self::assertSame('contents', $this->adapter->read('dest.txt'));
    }

    #[Test]
    public function metadata(): void
    {
        $this->adapter->write('file.txt', 'hello', ['Content-Type' => 'text/plain']);

        $metadata = $this->adapter->metadata('file.txt');

        self::assertSame('file.txt', $metadata->path);
        self::assertSame(5, $metadata->size);
        self::assertNotNull($metadata->lastModified);
        self::assertSame('text/plain', $metadata->mimeType);
    }

    #[Test]
    public function metadataThrowsForMissingObject(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        $this->adapter->metadata('nonexistent.txt');
    }

    #[Test]
    public function publicUrl(): void
    {
        self::assertSame('memory://file.txt', $this->adapter->publicUrl('file.txt'));
    }

    #[Test]
    public function temporaryUrlThrowsUnsupportedOperationException(): void
    {
        $this->expectException(UnsupportedOperationException::class);

        $this->adapter->temporaryUrl('file.txt', new \DateTimeImmutable('+1 hour'));
    }

    #[Test]
    public function temporaryUploadUrlThrowsUnsupportedOperationException(): void
    {
        $this->expectException(UnsupportedOperationException::class);

        $this->adapter->temporaryUploadUrl('file.txt', new \DateTimeImmutable('+1 hour'));
    }

    #[Test]
    public function listContentsWithPrefix(): void
    {
        $this->adapter->write('uploads/a.txt', 'a');
        $this->adapter->write('uploads/b.txt', 'b');
        $this->adapter->write('other/c.txt', 'c');

        $items = iterator_to_array($this->adapter->listContents('uploads/', deep: true));

        self::assertCount(2, $items);
        self::assertSame('uploads/a.txt', $items[0]->path);
        self::assertSame('uploads/b.txt', $items[1]->path);
    }

    #[Test]
    public function listContentsShallow(): void
    {
        $this->adapter->write('uploads/a.txt', 'a');
        $this->adapter->write('uploads/sub/b.txt', 'b');

        $items = iterator_to_array($this->adapter->listContents('uploads/', deep: false));

        $files = array_values(array_filter($items, fn($m) => !$m->isDirectory));
        $dirs = array_values(array_filter($items, fn($m) => $m->isDirectory));

        self::assertCount(1, $files);
        self::assertSame('uploads/a.txt', $files[0]->path);
        self::assertCount(1, $dirs);
        self::assertSame('uploads/sub', $dirs[0]->path);
    }

    #[Test]
    public function listContentsAll(): void
    {
        $this->adapter->write('a.txt', 'a');
        $this->adapter->write('b.txt', 'b');

        $items = iterator_to_array($this->adapter->listContents('', deep: true));

        self::assertCount(2, $items);
    }

    #[Test]
    public function writeOverwritesExistingObject(): void
    {
        $this->adapter->write('file.txt', 'old');
        $this->adapter->write('file.txt', 'new');

        self::assertSame('new', $this->adapter->read('file.txt'));
    }
}
