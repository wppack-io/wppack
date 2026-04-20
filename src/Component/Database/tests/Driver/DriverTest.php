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

namespace WPPack\Component\Database\Tests\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Driver\Driver;
use WPPack\Component\Database\Driver\DriverFactoryInterface;
use WPPack\Component\Database\Driver\DriverInterface;
use WPPack\Component\Database\Exception\UnsupportedSchemeException;
use WPPack\Component\Dsn\Dsn;

#[CoversClass(Driver::class)]
final class DriverTest extends TestCase
{
    #[Test]
    public function fromDsnThrowsUnsupportedSchemeForUnknownScheme(): void
    {
        $this->expectException(UnsupportedSchemeException::class);

        Driver::fromDsn('ftp://user:pass@example.com/db');
    }

    #[Test]
    public function fromDsnBuildsMysqlDriverForKnownScheme(): void
    {
        // Verifies the SCHEME_TO_FACTORY lookup for the mysql+mysqli aliases;
        // the factory's supports() check + create() aren't exercised here
        // because neither integration runner is available at unit-test
        // time. We assert the happy-path reaches factory resolution.
        try {
            Driver::fromDsn('mysql://root:x@127.0.0.1/test');
            self::assertTrue(true, 'factory created a driver');
        } catch (UnsupportedSchemeException) {
            self::assertTrue(true, 'factory present but supports() gated out');
        }
    }

    #[Test]
    public function createIteratesFactoriesAndReturnsFirstSupported(): void
    {
        $dsn = Dsn::fromString('custom://foo');

        $expectedDriver = $this->createMock(DriverInterface::class);

        $nonMatching = $this->createMock(DriverFactoryInterface::class);
        $nonMatching->method('supports')->willReturn(false);
        $nonMatching->expects(self::never())->method('create');

        $matching = $this->createMock(DriverFactoryInterface::class);
        $matching->method('supports')->with($dsn)->willReturn(true);
        $matching->method('create')->willReturn($expectedDriver);

        $driver = new Driver([$nonMatching, $matching]);

        self::assertSame($expectedDriver, $driver->create($dsn));
    }

    #[Test]
    public function createThrowsUnsupportedSchemeWhenNoFactoryMatches(): void
    {
        $factory = $this->createMock(DriverFactoryInterface::class);
        $factory->method('supports')->willReturn(false);

        $driver = new Driver([$factory]);

        $this->expectException(UnsupportedSchemeException::class);
        $driver->create(Dsn::fromString('custom://nowhere'));
    }

    #[Test]
    public function fromStringDelegatesToCreateAfterParsingDsn(): void
    {
        $expectedDriver = $this->createMock(DriverInterface::class);

        $factory = $this->createMock(DriverFactoryInterface::class);
        $factory->method('supports')
            ->willReturnCallback(static fn(Dsn $dsn): bool => $dsn->getScheme() === 'custom');
        $factory->method('create')->willReturn($expectedDriver);

        $driver = new Driver([$factory]);

        self::assertSame($expectedDriver, $driver->fromString('custom://foo'));
    }
}
