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

namespace WPPack\Component\Database\Bridge\PostgreSQLDataApi\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\PostgreSQL\PostgreSQLDriver;
use WPPack\Component\Database\Bridge\PostgreSQLDataApi\PostgreSQLDataApiDriver;
use WPPack\Component\Database\Bridge\PostgreSQLDataApi\PostgreSQLDataApiDriverFactory;
use WPPack\Component\Database\Driver\Driver;
use WPPack\Component\Database\Exception\UnsupportedSchemeException;
use WPPack\Component\Dsn\Dsn;

final class PostgreSQLDataApiDriverFactoryTest extends TestCase
{
    #[Test]
    public function supportsPostgreSQLDataApiScheme(): void
    {
        $factory = new PostgreSQLDataApiDriverFactory();

        if (!class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            self::assertFalse($factory->supports(Dsn::fromString('pgsql+dataapi://arn/db')));

            return;
        }

        self::assertTrue($factory->supports(Dsn::fromString('pgsql+dataapi://arn/db')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new PostgreSQLDataApiDriverFactory();

        self::assertFalse($factory->supports(Dsn::fromString('pgsql://host/db')));
        self::assertFalse($factory->supports(Dsn::fromString('mysql+dataapi://arn/db')));
    }

    #[Test]
    public function definitions(): void
    {
        $defs = PostgreSQLDataApiDriverFactory::definitions();

        self::assertCount(1, $defs);
        self::assertSame('pgsql+dataapi', $defs[0]->scheme);
        self::assertSame('Aurora PostgreSQL Data API', $defs[0]->label);
    }

    #[Test]
    public function createsDriverFromDsn(): void
    {
        if (!class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            self::markTestSkipped('async-aws/rds-data-service not installed.');
        }

        $factory = new PostgreSQLDataApiDriverFactory();
        $driver = $factory->create(Dsn::fromString('pgsql+dataapi://arn:aws:rds:us-east-1:123:cluster:my-cluster/mydb?secret_arn=arn:secret'));

        self::assertSame('pgsql+dataapi', $driver->getName());
    }

    /**
     * Regression guard: pgsql+dataapi must route to THIS bridge and
     * must never fall back to the plain PostgreSQLDriverFactory.
     * Observable behaviour depends on whether async-aws/rds-data-service
     * is available at runtime:
     *   - installed → Driver::fromDsn() resolves to PostgreSQLDataApiDriver
     *   - absent    → this factory's supports() returns false and the
     *                 router raises UnsupportedSchemeException (no silent
     *                 degradation into a real PostgreSQL connection).
     */
    #[Test]
    public function postgresqlDataApiSchemeIsNotShadowedByPlainPostgreSQL(): void
    {
        if (class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            $driver = Driver::fromDsn('pgsql+dataapi://arn:aws:rds:us-east-1:000:cluster/wp');

            self::assertInstanceOf(PostgreSQLDataApiDriver::class, $driver);
            self::assertNotInstanceOf(PostgreSQLDriver::class, $driver);

            return;
        }

        $this->expectException(UnsupportedSchemeException::class);

        Driver::fromDsn('pgsql+dataapi://arn:aws:rds:us-east-1:000:cluster/wp');
    }
}
