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

namespace WPPack\Component\Database\Bridge\Pgsql\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WPPack\Component\Database\Bridge\Pgsql\PgsqlDriverFactory;
use WPPack\Component\Dsn\Dsn;

final class PgsqlDriverFactoryTest extends TestCase
{
    #[Test]
    public function supportsSchemes(): void
    {
        $factory = new PgsqlDriverFactory();

        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        self::assertTrue($factory->supports(Dsn::fromString('pgsql://host/db')));
        self::assertTrue($factory->supports(Dsn::fromString('postgresql://host/db')));
        self::assertTrue($factory->supports(Dsn::fromString('postgres://host/db')));
        self::assertFalse($factory->supports(Dsn::fromString('mysql://host/db')));
        self::assertFalse($factory->supports(Dsn::fromString('sqlite:///path')));
    }

    #[Test]
    public function createsDriver(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new PgsqlDriverFactory();
        $driver = $factory->create(Dsn::fromString('pgsql://user:pass@localhost:5432/mydb'));

        self::assertInstanceOf(PgsqlDriver::class, $driver);
        self::assertSame('pgsql', $driver->getName());
    }

    #[Test]
    public function definitions(): void
    {
        $defs = PgsqlDriverFactory::definitions();

        self::assertCount(1, $defs);
        self::assertSame('pgsql', $defs[0]->scheme);
    }

    #[Test]
    public function parsesSearchPathOption(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new PgsqlDriverFactory();
        $driver = $factory->create(Dsn::fromString('pgsql://user:pass@localhost:5432/mydb?search_path=tenant_42,public'));

        self::assertSame(['tenant_42', 'public'], self::readSearchPath($driver));
    }

    #[Test]
    public function schemaOptionIsSingleEntrySearchPathAlias(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new PgsqlDriverFactory();
        $driver = $factory->create(Dsn::fromString('pgsql://user:pass@localhost:5432/mydb?schema=myschema'));

        self::assertSame(['myschema'], self::readSearchPath($driver));
    }

    #[Test]
    public function absentSearchPathIsNull(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new PgsqlDriverFactory();
        $driver = $factory->create(Dsn::fromString('pgsql://user:pass@localhost:5432/mydb'));

        self::assertNull(self::readSearchPath($driver));
    }

    /** @return list<string>|null */
    private static function readSearchPath(object $driver): ?array
    {
        $reflection = new \ReflectionClass(PgsqlDriver::class);
        $property = $reflection->getProperty('searchPath');

        return $property->getValue($driver);
    }
}
