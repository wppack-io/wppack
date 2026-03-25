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

namespace WpPack\Component\Database\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\DatabaseEngine;

#[CoversClass(DatabaseEngine::class)]
final class DatabaseEngineTest extends TestCase
{
    #[Test]
    public function mysqlCaseHasCorrectValue(): void
    {
        self::assertSame('mysql', DatabaseEngine::MySQL->value);
    }

    #[Test]
    public function sqliteCaseHasCorrectValue(): void
    {
        self::assertSame('sqlite', DatabaseEngine::SQLite->value);
    }

    #[Test]
    public function postgresqlCaseHasCorrectValue(): void
    {
        self::assertSame('pgsql', DatabaseEngine::PostgreSQL->value);
    }

    #[Test]
    public function fromInvalidStringReturnsNull(): void
    {
        self::assertNull(DatabaseEngine::tryFrom('invalid'));
    }
}
