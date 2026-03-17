<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\S3\Tests;

use AsyncAws\S3\S3Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Adapter\Dsn;
use WpPack\Component\Storage\Bridge\S3\S3StorageAdapter;
use WpPack\Component\Storage\Bridge\S3\S3StorageAdapterFactory;
use WpPack\Component\Storage\Exception\InvalidArgumentException;

#[CoversClass(S3StorageAdapterFactory::class)]
final class S3StorageAdapterFactoryTest extends TestCase
{
    #[Test]
    public function supportsS3Scheme(): void
    {
        $factory = new S3StorageAdapterFactory();

        self::assertTrue($factory->supports(Dsn::fromString('s3://my-bucket.s3.ap-northeast-1.amazonaws.com')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new S3StorageAdapterFactory();

        self::assertFalse($factory->supports(Dsn::fromString('gcs://my-bucket.storage.googleapis.com')));
        self::assertFalse($factory->supports(Dsn::fromString('azure://account.blob.core.windows.net')));
    }

    #[Test]
    public function createFromVirtualHostedStyleDsn(): void
    {
        $factory = new S3StorageAdapterFactory();
        $dsn = Dsn::fromString('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');

        $adapter = $factory->create($dsn);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
        self::assertSame('s3', $adapter->getName());
    }

    #[Test]
    public function createFromVirtualHostedStyleWithoutRegion(): void
    {
        $factory = new S3StorageAdapterFactory();
        $dsn = Dsn::fromString('s3://my-bucket.s3.amazonaws.com/uploads');

        $adapter = $factory->create($dsn);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
    }

    #[Test]
    public function createFromPlainBucketHost(): void
    {
        $factory = new S3StorageAdapterFactory();
        $dsn = Dsn::fromString('s3://my-bucket?region=ap-northeast-1');

        $adapter = $factory->create($dsn);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithOptionsOverride(): void
    {
        $factory = new S3StorageAdapterFactory();
        $dsn = Dsn::fromString('s3://my-bucket.s3.ap-northeast-1.amazonaws.com');

        $adapter = $factory->create($dsn, [
            'prefix' => 'media',
        ]);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithCustomS3Client(): void
    {
        $s3Client = $this->createMock(S3Client::class);

        $factory = new S3StorageAdapterFactory();
        $dsn = Dsn::fromString('s3://my-bucket.s3.ap-northeast-1.amazonaws.com');

        $adapter = $factory->create($dsn, ['s3_client' => $s3Client]);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithCredentialsInDsn(): void
    {
        $factory = new S3StorageAdapterFactory();
        $dsn = Dsn::fromString('s3://AKID:SECRET@my-bucket.s3.us-east-1.amazonaws.com');

        $adapter = $factory->create($dsn);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
    }

    #[Test]
    public function createForCustomEndpoint(): void
    {
        $factory = new S3StorageAdapterFactory();
        $dsn = Dsn::fromString('s3://my-bucket?endpoint=http://localhost:9000');

        $adapter = $factory->create($dsn);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
    }

    #[Test]
    public function pathBecomesPrefix(): void
    {
        $factory = new S3StorageAdapterFactory();
        $dsn = Dsn::fromString('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/wp-content/uploads');

        $adapter = $factory->create($dsn);

        // Verify the prefix is reflected in the URL
        self::assertStringContainsString('wp-content/uploads', $adapter->url('file.txt'));
    }

    #[Test]
    public function regionOptionOverridesHostRegion(): void
    {
        $factory = new S3StorageAdapterFactory();
        $dsn = Dsn::fromString('s3://my-bucket.s3.ap-northeast-1.amazonaws.com');

        // options region takes precedence over host-parsed region
        $adapter = $factory->create($dsn, ['region' => 'us-west-2']);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
    }

    #[Test]
    public function throwsWhenBucketCannotBeDetermined(): void
    {
        $factory = new S3StorageAdapterFactory();

        // DSN without host cannot determine the bucket
        $this->expectException(InvalidArgumentException::class);

        $factory->create(Dsn::fromString('s3://'), []);
    }
}
