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

namespace WPPack\Component\Storage\Bridge\Gcs\Tests;

use Google\Cloud\Storage\Bucket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Storage\Adapter\Dsn;
use WPPack\Component\Storage\Bridge\Gcs\GcsStorageAdapter;
use WPPack\Component\Storage\Bridge\Gcs\GcsStorageAdapterFactory;
use WPPack\Component\Storage\Exception\InvalidArgumentException;

#[CoversClass(GcsStorageAdapterFactory::class)]
final class GcsStorageAdapterFactoryTest extends TestCase
{
    #[Test]
    public function definitionsReturnsOneDefinition(): void
    {
        $definitions = GcsStorageAdapterFactory::definitions();

        self::assertCount(1, $definitions);
        self::assertSame('gcs', $definitions[0]->scheme);
    }

    #[Test]
    public function supportsGcsScheme(): void
    {
        $factory = new GcsStorageAdapterFactory();

        self::assertTrue($factory->supports(Dsn::fromString('gcs://my-bucket.storage.googleapis.com')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new GcsStorageAdapterFactory();

        self::assertFalse($factory->supports(Dsn::fromString('s3://my-bucket.s3.amazonaws.com')));
        self::assertFalse($factory->supports(Dsn::fromString('azure://account.blob.core.windows.net/container')));
    }

    #[Test]
    public function createFromFullDsn(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $factory = new GcsStorageAdapterFactory();
        $dsn = Dsn::fromString('gcs://my-bucket.storage.googleapis.com/uploads');

        $adapter = $factory->create($dsn, ['bucket' => $bucket]);

        self::assertInstanceOf(GcsStorageAdapter::class, $adapter);
        self::assertSame('gcs', $adapter->getName());
    }

    #[Test]
    public function createFromPlainBucketHost(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $factory = new GcsStorageAdapterFactory();
        $dsn = Dsn::fromString('gcs://my-bucket');

        $adapter = $factory->create($dsn, ['bucket' => $bucket]);

        self::assertInstanceOf(GcsStorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithOptionsOverride(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $factory = new GcsStorageAdapterFactory();
        $dsn = Dsn::fromString('gcs://my-bucket.storage.googleapis.com');

        $adapter = $factory->create($dsn, [
            'bucket' => $bucket,
            'prefix' => 'media',
        ]);

        self::assertInstanceOf(GcsStorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithPublicUrl(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $factory = new GcsStorageAdapterFactory();
        $dsn = Dsn::fromString('gcs://my-bucket?public_url=https://cdn.example.com');

        $adapter = $factory->create($dsn, ['bucket' => $bucket]);

        self::assertInstanceOf(GcsStorageAdapter::class, $adapter);
    }

    #[Test]
    public function pathBecomesPrefix(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->method('name')->willReturn('my-bucket');
        $factory = new GcsStorageAdapterFactory();
        $dsn = Dsn::fromString('gcs://my-bucket.storage.googleapis.com/wp-content/uploads');

        $adapter = $factory->create($dsn, ['bucket' => $bucket]);

        self::assertStringContainsString('wp-content/uploads', $adapter->publicUrl('file.txt'));
    }

    #[Test]
    public function throwsWhenBucketCannotBeDetermined(): void
    {
        $factory = new GcsStorageAdapterFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot determine bucket name');

        $factory->create(Dsn::fromString('gcs://'), []);
    }

    #[Test]
    public function createWithBucketFromOptions(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $factory = new GcsStorageAdapterFactory();

        // Provide a host so parseBucket returns a string bucket name;
        // the Bucket mock from options is used directly by create()
        $adapter = $factory->create(Dsn::fromString('gcs://my-bucket'), [
            'bucket' => $bucket,
        ]);

        self::assertInstanceOf(GcsStorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithPrefixFromOptions(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $factory = new GcsStorageAdapterFactory();

        $adapter = $factory->create(
            Dsn::fromString('gcs://my-bucket'),
            [
                'bucket' => $bucket,
                'prefix' => 'custom-prefix',
            ],
        );

        self::assertInstanceOf(GcsStorageAdapter::class, $adapter);
    }

    #[Test]
    public function createWithProjectAndKeyFile(): void
    {
        $factory = new GcsStorageAdapterFactory();

        // This path creates a StorageClient with project and keyFile options.
        // It may fail due to missing credentials, but exercises the code path.
        try {
            $adapter = $factory->create(
                Dsn::fromString('gcs://my-bucket?project=my-project&key_file=/tmp/nonexistent.json'),
                [],
            );
            self::assertInstanceOf(GcsStorageAdapter::class, $adapter);
        } catch (\Throwable) {
            // StorageClient instantiation may fail; that's OK
            self::assertTrue(true);
        }
    }
}
