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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Driver\Driver;
use WPPack\Component\Database\Bridge\MySQLDataApi\MySQLDataApiDriver;
use WPPack\Component\Database\Driver\MySQLDriver;
use WPPack\Component\Database\Exception\UnsupportedSchemeException;

/**
 * Behaviour of the default static entry point Driver::fromDsn().
 *
 * Previously routed through a first-match factory iteration, now uses an
 * explicit scheme→factory map. These tests pin the contract so adding a new
 * bridge doesn't silently change routing for existing schemes.
 */
final class DriverFromDsnTest extends TestCase
{
    #[Test]
    public function mysqlSchemeResolvesToMySQLDriver(): void
    {
        $driver = Driver::fromDsn('mysql://u:p@host:3306/db');

        self::assertInstanceOf(MySQLDriver::class, $driver);
        self::assertSame('mysql', $driver->getName());
    }

    #[Test]
    public function mariadbAliasResolvesToMySQLDriver(): void
    {
        $driver = Driver::fromDsn('mariadb://u:p@host:3306/db');

        self::assertInstanceOf(MySQLDriver::class, $driver);
    }

    #[Test]
    public function unknownSchemeRaisesUnsupported(): void
    {
        $this->expectException(UnsupportedSchemeException::class);

        Driver::fromDsn('gopher://nope');
    }

    #[Test]
    public function dataApiSchemeIsNotShadowedByMySQL(): void
    {
        // The critical safety property: `mysql+dataapi` must NOT be
        // routed through MySQLDriverFactory. With an explicit
        // scheme→factory map a future factory claiming 'mysql' via
        // loose matching cannot shadow this; the DSN therefore resolves
        // to MySQLDataApiDriver and never degrades into a plain-MySQL
        // connection attempt with a malformed host.
        $driver = Driver::fromDsn('mysql+dataapi://arn:aws:rds:us-east-1:000:cluster/wp');

        self::assertInstanceOf(MySQLDataApiDriver::class, $driver);
        self::assertSame('mysql+dataapi', $driver->getName());
    }

    #[Test]
    public function emptyDsnIsRejected(): void
    {
        $this->expectException(\Throwable::class);

        // Either Dsn::fromString() rejects first or UnsupportedSchemeException
        // comes out; either is fine as long as we do not silently pick a
        // factory.
        Driver::fromDsn('');
    }
}
