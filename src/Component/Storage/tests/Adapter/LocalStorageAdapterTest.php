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
        $this->adapter->createDirectory('new/sub/dir');

        self::assertTrue($this->adapter->directoryExists('new/sub/dir'));
    }

    #[Test]
    public function createDirectoryIsIdempotent(): void
    {
        $this->adapter->createDirectory('my-dir');
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
    public function deleteDirectoryNonexistentIsNoop(): void
    {
        $this->adapter->deleteDirectory('nonexistent-dir');

        self::assertFalse($this->adapter->directoryExists('nonexistent-dir'));
    }

    #[Test]
    public function directoryExistsCheck(): void
    {
        self::assertFalse($this->adapter->directoryExists('my-dir'));

        $this->adapter->createDirectory('my-dir');

        self::assertTrue($this->adapter->directoryExists('my-dir'));
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

        self::assertFalse($this->adapter->fileExists('source.txt'));
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

        self::assertSame('file.txt', $metadata->path);
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
    public function listContentsDeep(): void
    {
        $this->adapter->write('a.txt', 'a');
        $this->adapter->write('sub/b.txt', 'b');
        $this->adapter->write('sub/deep/c.txt', 'c');

        $items = iterator_to_array($this->adapter->listContents('', deep: true));
        $paths = array_map(fn($m) => $m->path, $items);
        sort($paths);

        self::assertSame(['a.txt', 'sub/b.txt', 'sub/deep/c.txt'], $paths);
    }

    #[Test]
    public function listContentsShallow(): void
    {
        $this->adapter->write('a.txt', 'a');
        $this->adapter->write('sub/b.txt', 'b');

        $items = iterator_to_array($this->adapter->listContents('', deep: false));
        $filePaths = array_map(
            fn($m) => $m->path,
            array_values(array_filter($items, fn($m) => !$m->isDirectory)),
        );

        self::assertSame(['a.txt'], $filePaths);
    }

    #[Test]
    public function listContentsWithPrefix(): void
    {
        $this->adapter->write('uploads/a.txt', 'a');
        $this->adapter->write('uploads/b.txt', 'b');
        $this->adapter->write('other/c.txt', 'c');

        $items = iterator_to_array($this->adapter->listContents('uploads', deep: true));
        $paths = array_map(fn($m) => $m->path, $items);
        sort($paths);

        self::assertSame(['uploads/a.txt', 'uploads/b.txt'], $paths);
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
