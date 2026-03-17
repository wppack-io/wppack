<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Adapter\AbstractStorageAdapter;
use WpPack\Component\Storage\Exception\StorageException;
use WpPack\Component\Storage\Exception\UnsupportedOperationException;
use WpPack\Component\Storage\ObjectMetadata;

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
    public function existsDelegatesToDoExists(): void
    {
        $adapter = $this->createConcreteAdapter();
        $adapter->returnValues['doExists'] = true;

        self::assertTrue($adapter->exists('file.txt'));
        self::assertSame([['doExists', ['file.txt']]], $adapter->calls);
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
        $meta = new ObjectMetadata(key: 'file.txt', size: 100);
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
    public function listContentsDelegatesToDoListContents(): void
    {
        $adapter = $this->createConcreteAdapter();
        $items = [new ObjectMetadata(key: 'a.txt'), new ObjectMetadata(key: 'b.txt')];
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

            protected function doWrite(string $key, string $contents, array $metadata = []): void
            {
                $this->record('doWrite', [$key, $contents, $metadata]);
            }

            protected function doWriteStream(string $key, mixed $resource, array $metadata = []): void
            {
                $this->record('doWriteStream', [$key, $resource, $metadata]);
            }

            protected function doRead(string $key): string
            {
                return $this->record('doRead', [$key]);
            }

            protected function doReadStream(string $key): mixed
            {
                return $this->record('doReadStream', [$key]);
            }

            protected function doDelete(string $key): void
            {
                $this->record('doDelete', [$key]);
            }

            protected function doDeleteMultiple(array $keys): void
            {
                $this->record('doDeleteMultiple', [$keys]);
            }

            protected function doExists(string $key): bool
            {
                return $this->record('doExists', [$key]);
            }

            protected function doCopy(string $sourceKey, string $destinationKey): void
            {
                $this->record('doCopy', [$sourceKey, $destinationKey]);
            }

            protected function doMove(string $sourceKey, string $destinationKey): void
            {
                $this->record('doMove', [$sourceKey, $destinationKey]);
            }

            protected function doMetadata(string $key): ObjectMetadata
            {
                return $this->record('doMetadata', [$key]);
            }

            protected function doPublicUrl(string $key): string
            {
                return $this->record('doPublicUrl', [$key]);
            }

            protected function doListContents(string $prefix, bool $recursive): iterable
            {
                return $this->record('doListContents', [$prefix, $recursive]);
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
