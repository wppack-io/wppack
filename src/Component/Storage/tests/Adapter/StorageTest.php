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

namespace WpPack\Component\Storage\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Adapter\Dsn;
use WpPack\Component\Storage\Adapter\Storage;
use WpPack\Component\Storage\Adapter\StorageAdapterFactoryInterface;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Exception\UnsupportedSchemeException;

#[CoversClass(Storage::class)]
final class StorageTest extends TestCase
{
    #[Test]
    public function createWithMatchingFactory(): void
    {
        $mockAdapter = $this->createMock(StorageAdapterInterface::class);
        $mockAdapter->method('getName')->willReturn('test');

        $factory = $this->createMock(StorageAdapterFactoryInterface::class);
        $factory->method('supports')->willReturn(true);
        $factory->method('create')->willReturn($mockAdapter);

        $storage = new Storage([$factory]);
        $result = $storage->fromString('test://default?bucket=test');

        self::assertSame('test', $result->getName());
    }

    #[Test]
    public function throwsOnUnsupportedScheme(): void
    {
        $factory = $this->createMock(StorageAdapterFactoryInterface::class);
        $factory->method('supports')->willReturn(false);

        $storage = new Storage([$factory]);

        $this->expectException(UnsupportedSchemeException::class);
        $storage->fromString('unsupported://default');
    }

    #[Test]
    public function throwsWithNoFactories(): void
    {
        $storage = new Storage([]);

        $this->expectException(UnsupportedSchemeException::class);
        $storage->fromString('s3://default?bucket=test');
    }

    #[Test]
    public function passesOptionsToFactory(): void
    {
        $mockAdapter = $this->createMock(StorageAdapterInterface::class);

        $factory = $this->createMock(StorageAdapterFactoryInterface::class);
        $factory->method('supports')->willReturn(true);
        $factory->expects($this->once())
            ->method('create')
            ->with(
                $this->isInstanceOf(Dsn::class),
                $this->equalTo(['prefix' => 'uploads']),
            )
            ->willReturn($mockAdapter);

        $storage = new Storage([$factory]);
        $storage->fromString('test://default?bucket=test', ['prefix' => 'uploads']);
    }

    #[Test]
    public function selectsFirstMatchingFactory(): void
    {
        $adapter1 = $this->createMock(StorageAdapterInterface::class);
        $adapter1->method('getName')->willReturn('first');

        $adapter2 = $this->createMock(StorageAdapterInterface::class);
        $adapter2->method('getName')->willReturn('second');

        $factory1 = $this->createMock(StorageAdapterFactoryInterface::class);
        $factory1->method('supports')->willReturn(true);
        $factory1->method('create')->willReturn($adapter1);

        $factory2 = $this->createMock(StorageAdapterFactoryInterface::class);
        $factory2->method('supports')->willReturn(true);
        $factory2->method('create')->willReturn($adapter2);

        $storage = new Storage([$factory1, $factory2]);
        $result = $storage->fromString('test://default?bucket=test');

        self::assertSame('first', $result->getName());
    }

    #[Test]
    public function createWithDsnObject(): void
    {
        $mockAdapter = $this->createMock(StorageAdapterInterface::class);
        $mockAdapter->method('getName')->willReturn('test');

        $factory = $this->createMock(StorageAdapterFactoryInterface::class);
        $factory->method('supports')->willReturn(true);
        $factory->method('create')->willReturn($mockAdapter);

        $storage = new Storage([$factory]);
        $dsn = Dsn::fromString('test://default?bucket=test');
        $result = $storage->create($dsn);

        self::assertSame('test', $result->getName());
    }

    #[Test]
    public function fromDsnStaticMethodThrowsForUnsupportedScheme(): void
    {
        $this->expectException(UnsupportedSchemeException::class);

        Storage::fromDsn('unsupported://default');
    }
}
