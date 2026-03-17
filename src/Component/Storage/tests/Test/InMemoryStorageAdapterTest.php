<?php

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
    public function putAndGet(): void
    {
        $this->adapter->put('file.txt', 'hello world');

        self::assertSame('hello world', $this->adapter->get('file.txt'));
    }

    #[Test]
    public function putWithMetadata(): void
    {
        $this->adapter->put('file.txt', 'contents', ['Content-Type' => 'text/plain']);

        $metadata = $this->adapter->metadata('file.txt');
        self::assertSame('text/plain', $metadata->mimeType);
    }

    #[Test]
    public function putStreamAndGet(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'stream contents');
        rewind($stream);

        $this->adapter->putStream('file.txt', $stream);

        self::assertSame('stream contents', $this->adapter->get('file.txt'));

        fclose($stream);
    }

    #[Test]
    public function getThrowsForMissingObject(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        $this->adapter->get('nonexistent.txt');
    }

    #[Test]
    public function getStream(): void
    {
        $this->adapter->put('file.txt', 'stream test');

        $stream = $this->adapter->getStream('file.txt');
        self::assertIsResource($stream);
        self::assertSame('stream test', stream_get_contents($stream));

        fclose($stream);
    }

    #[Test]
    public function getStreamThrowsForMissingObject(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        $this->adapter->getStream('nonexistent.txt');
    }

    #[Test]
    public function delete(): void
    {
        $this->adapter->put('file.txt', 'contents');
        $this->adapter->delete('file.txt');

        self::assertFalse($this->adapter->exists('file.txt'));
    }

    #[Test]
    public function deleteNonexistentIsNoop(): void
    {
        $this->adapter->delete('nonexistent.txt');

        self::assertFalse($this->adapter->exists('nonexistent.txt'));
    }

    #[Test]
    public function deleteMultiple(): void
    {
        $this->adapter->put('a.txt', 'a');
        $this->adapter->put('b.txt', 'b');
        $this->adapter->put('c.txt', 'c');

        $this->adapter->deleteMultiple(['a.txt', 'b.txt']);

        self::assertFalse($this->adapter->exists('a.txt'));
        self::assertFalse($this->adapter->exists('b.txt'));
        self::assertTrue($this->adapter->exists('c.txt'));
    }

    #[Test]
    public function exists(): void
    {
        self::assertFalse($this->adapter->exists('file.txt'));

        $this->adapter->put('file.txt', 'contents');

        self::assertTrue($this->adapter->exists('file.txt'));
    }

    #[Test]
    public function copy(): void
    {
        $this->adapter->put('source.txt', 'contents');
        $this->adapter->copy('source.txt', 'dest.txt');

        self::assertSame('contents', $this->adapter->get('source.txt'));
        self::assertSame('contents', $this->adapter->get('dest.txt'));
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
        $this->adapter->put('source.txt', 'contents');
        $this->adapter->move('source.txt', 'dest.txt');

        self::assertFalse($this->adapter->exists('source.txt'));
        self::assertSame('contents', $this->adapter->get('dest.txt'));
    }

    #[Test]
    public function metadata(): void
    {
        $this->adapter->put('file.txt', 'hello', ['Content-Type' => 'text/plain']);

        $metadata = $this->adapter->metadata('file.txt');

        self::assertSame('file.txt', $metadata->key);
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
    public function url(): void
    {
        self::assertSame('memory://file.txt', $this->adapter->url('file.txt'));
    }

    #[Test]
    public function temporaryUrlThrowsUnsupportedOperationException(): void
    {
        $this->expectException(UnsupportedOperationException::class);

        $this->adapter->temporaryUrl('file.txt', new \DateTimeImmutable('+1 hour'));
    }

    #[Test]
    public function listContentsWithPrefix(): void
    {
        $this->adapter->put('uploads/a.txt', 'a');
        $this->adapter->put('uploads/b.txt', 'b');
        $this->adapter->put('other/c.txt', 'c');

        $items = iterator_to_array($this->adapter->listContents('uploads/'));

        self::assertCount(2, $items);
        self::assertSame('uploads/a.txt', $items[0]->key);
        self::assertSame('uploads/b.txt', $items[1]->key);
    }

    #[Test]
    public function listContentsNonRecursive(): void
    {
        $this->adapter->put('uploads/a.txt', 'a');
        $this->adapter->put('uploads/sub/b.txt', 'b');

        $items = iterator_to_array($this->adapter->listContents('uploads/', recursive: false));

        self::assertCount(1, $items);
        self::assertSame('uploads/a.txt', $items[0]->key);
    }

    #[Test]
    public function listContentsAll(): void
    {
        $this->adapter->put('a.txt', 'a');
        $this->adapter->put('b.txt', 'b');

        $items = iterator_to_array($this->adapter->listContents());

        self::assertCount(2, $items);
    }

    #[Test]
    public function putOverwritesExistingObject(): void
    {
        $this->adapter->put('file.txt', 'old');
        $this->adapter->put('file.txt', 'new');

        self::assertSame('new', $this->adapter->get('file.txt'));
    }
}
