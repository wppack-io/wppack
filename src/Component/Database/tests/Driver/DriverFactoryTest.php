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

namespace WpPack\Component\Database\Tests\Driver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Driver\Driver;
use WpPack\Component\Database\Driver\MysqlDriver;
use WpPack\Component\Database\Driver\MysqlDriverFactory;
use WpPack\Component\Database\Exception\UnsupportedSchemeException;
use WpPack\Component\Dsn\Dsn;

final class DriverFactoryTest extends TestCase
{
    #[Test]
    public function mysqlFactorySupportsSchemes(): void
    {
        $factory = new MysqlDriverFactory();

        self::assertTrue($factory->supports(Dsn::fromString('mysql://host/db')));
        self::assertTrue($factory->supports(Dsn::fromString('mariadb://host/db')));
        self::assertTrue($factory->supports(Dsn::fromString('mysqli://host/db')));
        self::assertTrue($factory->supports(Dsn::fromString('wpdb://default')));
        self::assertFalse($factory->supports(Dsn::fromString('sqlite:///path')));
        self::assertFalse($factory->supports(Dsn::fromString('pgsql://host/db')));
    }

    #[Test]
    public function mysqlFactoryCreatesMysqlDriver(): void
    {
        $factory = new MysqlDriverFactory();
        $driver = $factory->create(Dsn::fromString('mysql://user:pass@localhost:3306/mydb'));

        self::assertInstanceOf(MysqlDriver::class, $driver);
        self::assertSame('mysql', $driver->getName());
    }

    #[Test]
    public function mysqlFactoryDefinitions(): void
    {
        $definitions = MysqlDriverFactory::definitions();

        self::assertCount(2, $definitions);
        self::assertSame('mysql', $definitions[0]->scheme);
        self::assertSame('wpdb', $definitions[1]->scheme);
    }

    #[Test]
    public function driverFromDsnCreatesMysqlDriver(): void
    {
        $driver = Driver::fromDsn('mysql://user:pass@localhost:3306/testdb');

        self::assertInstanceOf(MysqlDriver::class, $driver);
    }

    #[Test]
    public function driverFromDsnThrowsForUnsupportedScheme(): void
    {
        $this->expectException(UnsupportedSchemeException::class);
        $this->expectExceptionMessage('unsupported');

        Driver::fromDsn('unsupported://host/db');
    }
}
