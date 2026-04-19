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

namespace WPPack\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Cache\Adapter\Adapter;
use WPPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WPPack\Component\Cache\Adapter\AdapterInterface;
use WPPack\Component\Cache\Adapter\Dsn;
use WPPack\Component\Cache\Exception\UnsupportedSchemeException;

final class AdapterTest extends TestCase
{
    #[Test]
    public function createWithMatchingFactory(): void
    {
        $mockAdapter = $this->createMock(AdapterInterface::class);
        $mockAdapter->method('getName')->willReturn('test');

        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->method('supports')->willReturn(true);
        $factory->method('create')->willReturn($mockAdapter);

        $adapter = new Adapter([$factory]);
        $result = $adapter->fromString('test://localhost');

        self::assertSame('test', $result->getName());
    }

    #[Test]
    public function throwsOnUnsupportedScheme(): void
    {
        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->method('supports')->willReturn(false);

        $adapter = new Adapter([$factory]);

        $this->expectException(UnsupportedSchemeException::class);
        $adapter->fromString('unsupported://localhost');
    }

    #[Test]
    public function throwsWithNoFactories(): void
    {
        $adapter = new Adapter([]);

        $this->expectException(UnsupportedSchemeException::class);
        $adapter->fromString('redis://localhost');
    }

    #[Test]
    public function passesOptionsToFactory(): void
    {
        $mockAdapter = $this->createMock(AdapterInterface::class);

        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->method('supports')->willReturn(true);
        $factory->expects($this->once())
            ->method('create')
            ->with(
                $this->isInstanceOf(Dsn::class),
                $this->equalTo(['timeout' => 5]),
            )
            ->willReturn($mockAdapter);

        $adapter = new Adapter([$factory]);
        $adapter->fromString('test://localhost', ['timeout' => 5]);
    }

    #[Test]
    public function selectsFirstMatchingFactory(): void
    {
        $adapter1 = $this->createMock(AdapterInterface::class);
        $adapter1->method('getName')->willReturn('first');

        $adapter2 = $this->createMock(AdapterInterface::class);
        $adapter2->method('getName')->willReturn('second');

        $factory1 = $this->createMock(AdapterFactoryInterface::class);
        $factory1->method('supports')->willReturn(true);
        $factory1->method('create')->willReturn($adapter1);

        $factory2 = $this->createMock(AdapterFactoryInterface::class);
        $factory2->method('supports')->willReturn(true);
        $factory2->method('create')->willReturn($adapter2);

        $adapter = new Adapter([$factory1, $factory2]);
        $result = $adapter->fromString('test://localhost');

        self::assertSame('first', $result->getName());
    }

    #[Test]
    public function createWithDsnObject(): void
    {
        $mockAdapter = $this->createMock(AdapterInterface::class);
        $mockAdapter->method('getName')->willReturn('test');

        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->method('supports')->willReturn(true);
        $factory->method('create')->willReturn($mockAdapter);

        $adapter = new Adapter([$factory]);
        $dsn = Dsn::fromString('test://localhost');
        $result = $adapter->create($dsn);

        self::assertSame('test', $result->getName());
    }

    #[Test]
    public function fromDsnStaticMethodThrowsForUnsupportedScheme(): void
    {
        $this->expectException(UnsupportedSchemeException::class);

        Adapter::fromDsn('unsupported://localhost');
    }
}
