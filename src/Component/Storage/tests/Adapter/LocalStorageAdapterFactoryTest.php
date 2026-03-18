<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Adapter\Dsn;
use WpPack\Component\Storage\Adapter\LocalStorageAdapter;
use WpPack\Component\Storage\Adapter\LocalStorageAdapterFactory;
use WpPack\Component\Storage\Exception\InvalidArgumentException;

#[CoversClass(LocalStorageAdapterFactory::class)]
final class LocalStorageAdapterFactoryTest extends TestCase
{
    #[Test]
    public function supportsLocalScheme(): void
    {
        $factory = new LocalStorageAdapterFactory();

        self::assertTrue($factory->supports(Dsn::fromString('local:///tmp/storage')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new LocalStorageAdapterFactory();

        self::assertFalse($factory->supports(Dsn::fromString('s3://my-bucket.s3.amazonaws.com')));
        self::assertFalse($factory->supports(Dsn::fromString('azure://account.blob.core.windows.net/container')));
        self::assertFalse($factory->supports(Dsn::fromString('gcs://my-bucket.storage.googleapis.com')));
    }

    #[Test]
    public function createFromAbsolutePath(): void
    {
        $factory = new LocalStorageAdapterFactory();
        $dsn = Dsn::fromString('local:///tmp/storage');

        $adapter = $factory->create($dsn);

        self::assertInstanceOf(LocalStorageAdapter::class, $adapter);
        self::assertSame('local', $adapter->getName());
    }

    #[Test]
    public function createFromRelativePath(): void
    {
        $factory = new LocalStorageAdapterFactory();
        $dsn = Dsn::fromString('local://./uploads');

        $adapter = $factory->create($dsn);

        self::assertInstanceOf(LocalStorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithPublicUrl(): void
    {
        $factory = new LocalStorageAdapterFactory();
        $dsn = Dsn::fromString('local:///var/www/uploads?public_url=https://cdn.example.com');

        $adapter = $factory->create($dsn);

        self::assertInstanceOf(LocalStorageAdapter::class, $adapter);
        self::assertSame('https://cdn.example.com/file.txt', $adapter->publicUrl('file.txt'));
    }

    #[Test]
    public function createWithRootDirOption(): void
    {
        $factory = new LocalStorageAdapterFactory();
        $dsn = Dsn::fromString('local://');

        $adapter = $factory->create($dsn, ['root_dir' => '/tmp/storage']);

        self::assertInstanceOf(LocalStorageAdapter::class, $adapter);
    }

    #[Test]
    public function throwsWhenRootDirCannotBeDetermined(): void
    {
        $factory = new LocalStorageAdapterFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot determine root directory');

        $factory->create(Dsn::fromString('local://'), []);
    }

    #[Test]
    public function createFromHostnameOnly(): void
    {
        $factory = new LocalStorageAdapterFactory();
        // local://hostname → host=hostname, path=null
        $dsn = Dsn::fromString('local://storage-host');

        $adapter = $factory->create($dsn);

        self::assertInstanceOf(LocalStorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithPublicUrlFromOptions(): void
    {
        $factory = new LocalStorageAdapterFactory();
        $dsn = Dsn::fromString('local:///var/www/uploads');

        $adapter = $factory->create($dsn, ['public_url' => 'https://static.example.com']);

        self::assertInstanceOf(LocalStorageAdapter::class, $adapter);
        self::assertSame('https://static.example.com/file.txt', $adapter->publicUrl('file.txt'));
    }
}
