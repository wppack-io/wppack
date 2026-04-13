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

namespace WpPack\Component\Database\Bridge\Pgsql\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriverFactory;
use WpPack\Component\Dsn\Dsn;

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
}
