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

namespace WPPack\Component\Storage\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Storage\Adapter\AbstractStorageAdapter;
use WPPack\Component\Storage\Exception\StorageException;
use WPPack\Component\Storage\Exception\UnsupportedOperationException;
use WPPack\Component\Storage\ObjectMetadata;

#[CoversClass(AbstractStorageAdapter::class)]
final class AbstractStorageAdapterTest extends TestCase
{
    #[Test]
    public function writeDelegatesToDoWrite(): void
    {
        $adapter = $this->createConcreteAdapter();

        $adapter->write('file.txt', 'contents', ['Content-Type' => 'text/plain']);

        self::assertSame([['doWrite', ['file.txt', 'contents', ['Content-Type' => 'text/plain']]]], $adapter->calls);
    }

    #[Test]
    public function writeStreamDelegatesToDoWriteStream(): void
    {
        $adapter = $this->createConcreteAdapter();
        $stream = fopen('php://memory', 'r+');

        $adapter->writeStream('file.txt', $stream, ['Content-Type' => 'text/plain']);

        self::assertCount(1, $adapter->calls);
        self::assertSame('doWriteStream', $adapter->calls[0][0]);
        self::assertSame('file.txt', $adapter->calls[0][1][0]);
        self::assertSame($stream, $adapter->calls[0][1][1]);

        fclose($stream);
    }

    #[Test]
    public function readDelegatesToDoRead(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doRead'] = 'file contents';

        self::assertSame('file contents', $adapter->read('file.txt'));
        self::assertSame([['doRead', ['file.txt']]], $adapter->calls);
    }

    #[Test]
    public function readStreamDelegatesToDoReadStream(): void
    {
        $adapter = $this->createConcreteAdapter();
        $stream = fopen('php://memory', 'r+');
        $adapter->returnValues['doReadStream'] = $stream;

        self::assertSame($stream, $adapter->readStream('file.txt'));
        self::assertSame([['doReadStream', ['file.txt']]], $adapter->calls);

        fclose($stream);
    }

    #[Test]
    public function deleteDelegatesToDoDelete(): void
    {
        $adapter = $this->createConcreteAdapter();

        $adapter->delete('file.txt');

        self::assertSame([['doDelete', ['file.txt']]], $adapter->calls);
    }

    #[Test]
    public function deleteMultipleDefaultsToLoopingDelete(): void
    {
        $adapter = $this->createConcreteAdapter();

        $adapter->deleteMultiple(['a.txt', 'b.txt']);

        self::assertSame([['doDeleteMultiple', [['a.txt', 'b.txt']]]], $adapter->calls);
    }

    #[Test]
    public function fileExistsDelegatesToDoFileExists(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doFileExists'] = true;

        self::assertTrue($adapter->fileExists('file.txt'));
        self::assertSame([['doFileExists', ['file.txt']]], $adapter->calls);
    }

    #[Test]
    public function createDirectoryDelegatesToDoCreateDirectory(): void
    {
        $adapter = $this->createConcreteAdapter();

        $adapter->createDirectory('some/dir');

        self::assertSame([['doCreateDirectory', ['some/dir']]], $adapter->calls);
    }

    #[Test]
    public function deleteDirectoryDelegatesToDoDeleteDirectory(): void
    {
        $adapter = $this->createConcreteAdapter();

        $adapter->deleteDirectory('some/dir');

        self::assertSame([['doDeleteDirectory', ['some/dir']]], $adapter->calls);
    }

    #[Test]
    public function directoryExistsDelegatesToDoDirectoryExists(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doDirectoryExists'] = true;

        self::assertTrue($adapter->directoryExists('some/dir'));
        self::assertSame([['doDirectoryExists', ['some/dir']]], $adapter->calls);
    }

    #[Test]
    public function copyDelegatesToDoCopy(): void
    {
        $adapter = $this->createConcreteAdapter();

        $adapter->copy('source.txt', 'dest.txt');

        self::assertSame([['doCopy', ['source.txt', 'dest.txt']]], $adapter->calls);
    }

    #[Test]
    public function moveDelegatesToDoMove(): void
    {
        $adapter = $this->createConcreteAdapter();

        $adapter->move('source.txt', 'dest.txt');

        self::assertSame([['doMove', ['source.txt', 'dest.txt']]], $adapter->calls);
    }

    #[Test]
    public function metadataDelegatesToDoMetadata(): void
    {
        $adapter = $this->createConcreteAdapter();
        $meta = new ObjectMetadata(path: 'file.txt', size: 100);
        $adapter->returnValues['doMetadata'] = $meta;

        self::assertSame($meta, $adapter->metadata('file.txt'));
        self::assertSame([['doMetadata', ['file.txt']]], $adapter->calls);
    }

    #[Test]
    public function publicUrlDelegatesToDoPublicUrl(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doPublicUrl'] = 'https://example.com/file.txt';

        self::assertSame('https://example.com/file.txt', $adapter->publicUrl('file.txt'));
        self::assertSame([['doPublicUrl', ['file.txt']]], $adapter->calls);
    }

    #[Test]
    public function temporaryUrlDefaultThrowsUnsupportedOperationException(): void
    {
        $adapter = $this->createConcreteAdapter();

        $this->expectException(UnsupportedOperationException::class);
        $adapter->temporaryUrl('file.txt', new \DateTimeImmutable('+1 hour'));
    }

    #[Test]
    public function temporaryUploadUrlDefaultThrowsUnsupportedOperationException(): void
    {
        $adapter = $this->createConcreteAdapter();

        $this->expectException(UnsupportedOperationException::class);
        $adapter->temporaryUploadUrl('file.txt', new \DateTimeImmutable('+1 hour'));
    }

    #[Test]
    public function listContentsDelegatesToDoListContents(): void
    {
        $adapter = $this->createConcreteAdapter();
        $items = [new ObjectMetadata(path: 'a.txt'), new ObjectMetadata(path: 'b.txt')];
        $adapter->returnValues['doListContents'] = $items;

        self::assertSame($items, $adapter->listContents('prefix/', true));
        self::assertSame([['doListContents', ['prefix/', true]]], $adapter->calls);
    }

    #[Test]
    public function executeWrapsProviderExceptionInStorageException(): void
    {
        $adapter = $this->createConcreteAdapter();
        $original = new \RuntimeException('connection lost');
        $adapter->throwOn['doRead'] = $original;

        try {
            $adapter->read('file.txt');
            self::fail('Expected StorageException');
        } catch (StorageException $e) {
            self::assertSame('connection lost', $e->getMessage());
            self::assertSame($original, $e->getPrevious());
        }
    }

    #[Test]
    public function executeRethrowsStorageExceptionUnchanged(): void
    {
        $adapter = $this->createConcreteAdapter();
        $original = new StorageException('storage error');
        $adapter->throwOn['doRead'] = $original;

        try {
            $adapter->read('file.txt');
            self::fail('Expected StorageException');
        } catch (StorageException $e) {
            self::assertSame($original, $e);
            self::assertNull($e->getPrevious());
        }
    }

    #[Test]
    public function executeRethrowsUnsupportedOperationExceptionUnchanged(): void
    {
        $adapter = $this->createConcreteAdapter();
        $original = new UnsupportedOperationException('temporaryUrl', 'test');
        $adapter->throwOn['doPublicUrl'] = $original;

        try {
            $adapter->publicUrl('file.txt');
            self::fail('Expected UnsupportedOperationException');
        } catch (UnsupportedOperationException $e) {
            self::assertSame($original, $e);
        }
    }

    private function createConcreteAdapter(): AbstractStorageAdapter
    {
        return new class extends AbstractStorageAdapter {
            /** @var list<array{string, list<mixed>}> */
            public array $calls = [];

            /** @var array<string, mixed> */
            public array $returnValues = [];

            /** @var array<string, \Throwable> */
            public array $throwOn = [];

            public function getName(): string
            {
                return 'test';
            }

            protected function doWrite(string $path, string $contents, array $metadata = []): void
            {
                $this->record('doWrite', [$path, $contents, $metadata]);
            }

            protected function doWriteStream(string $path, mixed $resource, array $metadata = []): void
            {
                $this->record('doWriteStream', [$path, $resource, $metadata]);
            }

            protected function doRead(string $path): string
            {
                return $this->record('doRead', [$path]);
            }

            protected function doReadStream(string $path): mixed
            {
                return $this->record('doReadStream', [$path]);
            }

            protected function doDelete(string $path): void
            {
                $this->record('doDelete', [$path]);
            }

            protected function doDeleteMultiple(array $paths): void
            {
                $this->record('doDeleteMultiple', [$paths]);
            }

            protected function doFileExists(string $path): bool
            {
                return $this->record('doFileExists', [$path]);
            }

            protected function doCreateDirectory(string $path): void
            {
                $this->record('doCreateDirectory', [$path]);
            }

            protected function doDeleteDirectory(string $path): void
            {
                $this->record('doDeleteDirectory', [$path]);
            }

            protected function doDirectoryExists(string $path): bool
            {
                return $this->record('doDirectoryExists', [$path]);
            }

            protected function doCopy(string $source, string $destination): void
            {
                $this->record('doCopy', [$source, $destination]);
            }

            protected function doMove(string $source, string $destination): void
            {
                $this->record('doMove', [$source, $destination]);
            }

            protected function doMetadata(string $path): ObjectMetadata
            {
                return $this->record('doMetadata', [$path]);
            }

            protected function doPublicUrl(string $path): string
            {
                return $this->record('doPublicUrl', [$path]);
            }

            protected function doListContents(string $path, bool $deep): iterable
            {
                return $this->record('doListContents', [$path, $deep]);
            }

            private function record(string $method, array $args): mixed
            {
                $this->calls[] = [$method, $args];

                if (isset($this->throwOn[$method])) {
                    throw $this->throwOn[$method];
                }

                return $this->returnValues[$method] ?? null;
            }
        };
    }
}
