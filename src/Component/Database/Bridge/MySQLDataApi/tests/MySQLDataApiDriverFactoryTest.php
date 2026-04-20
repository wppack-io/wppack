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

namespace WPPack\Component\Database\Bridge\MySQLDataApi\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\MySQLDataApi\MySQLDataApiDriver;
use WPPack\Component\Database\Bridge\MySQLDataApi\MySQLDataApiDriverFactory;
use WPPack\Component\Database\Driver\Driver;
use WPPack\Component\Database\Driver\MySQLDriver;
use WPPack\Component\Database\Exception\UnsupportedSchemeException;
use WPPack\Component\Dsn\Dsn;

final class MySQLDataApiDriverFactoryTest extends TestCase
{
    #[Test]
    public function supportsMySQLDataApiScheme(): void
    {
        $factory = new MySQLDataApiDriverFactory();

        if (!class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            self::assertFalse($factory->supports(Dsn::fromString('mysql+dataapi://arn/db')));

            return;
        }

        self::assertTrue($factory->supports(Dsn::fromString('mysql+dataapi://arn/db')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new MySQLDataApiDriverFactory();

        self::assertFalse($factory->supports(Dsn::fromString('mysql://host/db')));
        self::assertFalse($factory->supports(Dsn::fromString('pgsql+dataapi://arn/db')));
    }

    #[Test]
    public function definitions(): void
    {
        $defs = MySQLDataApiDriverFactory::definitions();

        self::assertCount(1, $defs);
        self::assertSame('mysql+dataapi', $defs[0]->scheme);
        self::assertSame('Aurora MySQL Data API', $defs[0]->label);
    }

    #[Test]
    public function createsDriverFromDsn(): void
    {
        if (!class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            self::markTestSkipped('async-aws/rds-data-service not installed.');
        }

        $factory = new MySQLDataApiDriverFactory();
        $driver = $factory->create(Dsn::fromString('mysql+dataapi://arn:aws:rds:us-east-1:123:cluster:my-cluster/mydb?secret_arn=arn:secret'));

        self::assertSame('mysql+dataapi', $driver->getName());
    }

    /**
     * Regression guard: mysql+dataapi must route to THIS bridge and
     * must never fall back to the plain MySQLDriverFactory. Observable
     * behaviour depends on whether async-aws/rds-data-service is
     * available at runtime:
     *   - installed → Driver::fromDsn() resolves to MySQLDataApiDriver
     *   - absent    → this factory's supports() returns false and the
     *                 router raises UnsupportedSchemeException (no silent
     *                 degradation into a real MySQL connection).
     */
    #[Test]
    public function mysqlDataApiSchemeIsNotShadowedByPlainMySQL(): void
    {
        if (class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            $driver = Driver::fromDsn('mysql+dataapi://arn:aws:rds:us-east-1:000:cluster/wp');

            self::assertInstanceOf(MySQLDataApiDriver::class, $driver);
            self::assertNotInstanceOf(MySQLDriver::class, $driver);

            return;
        }

        $this->expectException(UnsupportedSchemeException::class);

        Driver::fromDsn('mysql+dataapi://arn:aws:rds:us-east-1:000:cluster/wp');
    }
}
