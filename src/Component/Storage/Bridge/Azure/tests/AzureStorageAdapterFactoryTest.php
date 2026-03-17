<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\Azure\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Adapter\Dsn;
use WpPack\Component\Storage\Bridge\Azure\AzureBlobClientInterface;
use WpPack\Component\Storage\Bridge\Azure\AzureStorageAdapter;
use WpPack\Component\Storage\Bridge\Azure\AzureStorageAdapterFactory;
use WpPack\Component\Storage\Exception\InvalidArgumentException;

#[CoversClass(AzureStorageAdapterFactory::class)]
final class AzureStorageAdapterFactoryTest extends TestCase
{
    #[Test]
    public function supportsAzureScheme(): void
    {
        $factory = new AzureStorageAdapterFactory();

        self::assertTrue($factory->supports(Dsn::fromString('azure://myaccount.blob.core.windows.net/mycontainer')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new AzureStorageAdapterFactory();

        self::assertFalse($factory->supports(Dsn::fromString('s3://my-bucket.s3.amazonaws.com')));
        self::assertFalse($factory->supports(Dsn::fromString('gcs://my-bucket.storage.googleapis.com')));
    }

    #[Test]
    public function createFromFullDsn(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $factory = new AzureStorageAdapterFactory();
        $dsn = Dsn::fromString('azure://myaccount.blob.core.windows.net/mycontainer/uploads');

        $adapter = $factory->create($dsn, ['client' => $client]);

        self::assertInstanceOf(AzureStorageAdapter::class, $adapter);
        self::assertSame('azure', $adapter->getName());
    }

    #[Test]
    public function createFromPlainAccountHost(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $factory = new AzureStorageAdapterFactory();
        $dsn = Dsn::fromString('azure://myaccount/mycontainer');

        $adapter = $factory->create($dsn, ['client' => $client]);

        self::assertInstanceOf(AzureStorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithOptionsOverride(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $factory = new AzureStorageAdapterFactory();
        $dsn = Dsn::fromString('azure://myaccount.blob.core.windows.net/mycontainer');

        $adapter = $factory->create($dsn, [
            'client' => $client,
            'public_url' => 'https://cdn.example.com',
        ]);

        self::assertInstanceOf(AzureStorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithContainerAndPrefix(): void
    {
        $client = $this->createMock(AzureBlobClientInterface::class);
        $factory = new AzureStorageAdapterFactory();
        $dsn = Dsn::fromString('azure://myaccount.blob.core.windows.net/mycontainer/wp-content/uploads');

        $adapter = $factory->create($dsn, ['client' => $client]);

        self::assertInstanceOf(AzureStorageAdapter::class, $adapter);
    }

    #[Test]
    public function throwsWhenAccountCannotBeDetermined(): void
    {
        $factory = new AzureStorageAdapterFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot determine account name');

        $factory->create(Dsn::fromString('azure://'), []);
    }

    #[Test]
    public function throwsWhenContainerCannotBeDetermined(): void
    {
        $factory = new AzureStorageAdapterFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot determine container name');

        $factory->create(Dsn::fromString('azure://myaccount.blob.core.windows.net'), []);
    }
}
