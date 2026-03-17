<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Adapter\LocalStorageAdapter;
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;

#[CoversClass(LocalStorageAdapter::class)]
final class LocalStorageAdapterTest extends TestCase
{
    private string $tempDir;
    private LocalStorageAdapter $adapter;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/wppack_local_storage_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->adapter = new LocalStorageAdapter($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function getName(): void
    {
        self::assertSame('local', $this->adapter->getName());
    }

    #[Test]
    public function writeAndRead(): void
    {
        $this->adapter->write('file.txt', 'hello world');

        self::assertSame('hello world', $this->adapter->read('file.txt'));
    }

    #[Test]
    public function writeCreatesSubdirectories(): void
    {
        $this->adapter->write('a/b/c/file.txt', 'deep');

        self::assertSame('deep', $this->adapter->read('a/b/c/file.txt'));
    }

    #[Test]
    public function writeOverwritesExisting(): void
    {
        $this->adapter->write('file.txt', 'old');
        $this->adapter->write('file.txt', 'new');

        self::assertSame('new', $this->adapter->read('file.txt'));
    }

    #[Test]
    public function writeStreamAndRead(): void
    {
        $stream = fopen('php://memory', 'r+');
        \assert($stream !== false);
        fwrite($stream, 'stream contents');
        rewind($stream);

        $this->adapter->writeStream('file.txt', $stream);
        fclose($stream);

        self::assertSame('stream contents', $this->adapter->read('file.txt'));
    }

    #[Test]
    public function readThrowsForMissingFile(): void
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
    public function readStreamThrowsForMissingFile(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        $this->adapter->readStream('nonexistent.txt');
    }

    #[Test]
    public function delete(): void
    {
        $this->adapter->write('file.txt', 'contents');
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
        $this->adapter->write('a.txt', 'a');
        $this->adapter->write('b.txt', 'b');
        $this->adapter->write('c.txt', 'c');

        $this->adapter->deleteMultiple(['a.txt', 'b.txt']);

        self::assertFalse($this->adapter->exists('a.txt'));
        self::assertFalse($this->adapter->exists('b.txt'));
        self::assertTrue($this->adapter->exists('c.txt'));
    }

    #[Test]
    public function exists(): void
    {
        self::assertFalse($this->adapter->exists('file.txt'));

        $this->adapter->write('file.txt', 'contents');

        self::assertTrue($this->adapter->exists('file.txt'));
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
    public function copyCreatesSubdirectories(): void
    {
        $this->adapter->write('source.txt', 'contents');
        $this->adapter->copy('source.txt', 'sub/dir/dest.txt');

        self::assertSame('contents', $this->adapter->read('sub/dir/dest.txt'));
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

        self::assertFalse($this->adapter->exists('source.txt'));
        self::assertSame('contents', $this->adapter->read('dest.txt'));
    }

    #[Test]
    public function moveThrowsForMissingSource(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        $this->adapter->move('nonexistent.txt', 'dest.txt');
    }

    #[Test]
    public function metadata(): void
    {
        $this->adapter->write('file.txt', 'hello');

        $metadata = $this->adapter->metadata('file.txt');

        self::assertSame('file.txt', $metadata->key);
        self::assertSame(5, $metadata->size);
        self::assertNotNull($metadata->lastModified);
    }

    #[Test]
    public function metadataThrowsForMissingFile(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        $this->adapter->metadata('nonexistent.txt');
    }

    #[Test]
    public function publicUrlReturnsFilePath(): void
    {
        self::assertSame(
            $this->tempDir . '/file.txt',
            $this->adapter->publicUrl('file.txt'),
        );
    }

    #[Test]
    public function publicUrlReturnsCustomUrl(): void
    {
        $adapter = new LocalStorageAdapter($this->tempDir, 'https://cdn.example.com');

        self::assertSame(
            'https://cdn.example.com/images/photo.jpg',
            $adapter->publicUrl('images/photo.jpg'),
        );
    }

    #[Test]
    public function temporaryUrlThrowsUnsupportedOperationException(): void
    {
        $this->expectException(UnsupportedOperationException::class);

        $this->adapter->temporaryUrl('file.txt', new \DateTimeImmutable('+1 hour'));
    }

    #[Test]
    public function listContentsRecursive(): void
    {
        $this->adapter->write('a.txt', 'a');
        $this->adapter->write('sub/b.txt', 'b');
        $this->adapter->write('sub/deep/c.txt', 'c');

        $items = iterator_to_array($this->adapter->listContents());
        $keys = array_map(fn($m) => $m->key, $items);
        sort($keys);

        self::assertSame(['a.txt', 'sub/b.txt', 'sub/deep/c.txt'], $keys);
    }

    #[Test]
    public function listContentsNonRecursive(): void
    {
        $this->adapter->write('a.txt', 'a');
        $this->adapter->write('sub/b.txt', 'b');

        $items = iterator_to_array($this->adapter->listContents('', recursive: false));
        $keys = array_map(fn($m) => $m->key, $items);

        self::assertSame(['a.txt'], $keys);
    }

    #[Test]
    public function listContentsWithPrefix(): void
    {
        $this->adapter->write('uploads/a.txt', 'a');
        $this->adapter->write('uploads/b.txt', 'b');
        $this->adapter->write('other/c.txt', 'c');

        $items = iterator_to_array($this->adapter->listContents('uploads'));
        $keys = array_map(fn($m) => $m->key, $items);
        sort($keys);

        self::assertSame(['uploads/a.txt', 'uploads/b.txt'], $keys);
    }

    #[Test]
    public function listContentsEmptyDirectory(): void
    {
        $items = iterator_to_array($this->adapter->listContents('nonexistent'));

        self::assertSame([], $items);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
