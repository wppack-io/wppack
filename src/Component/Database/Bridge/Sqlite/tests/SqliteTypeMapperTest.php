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

namespace WpPack\Component\Database\Bridge\Sqlite\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\Sqlite\TypeMapper\SqliteTypeMapper;

final class SqliteTypeMapperTest extends TestCase
{
    private SqliteTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SqliteTypeMapper();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function typeProvider(): iterable
    {
        yield 'INTEGER' => ['INTEGER', 'BIGINT(20)'];
        yield 'INT' => ['INT', 'BIGINT(20)'];
        yield 'TEXT' => ['TEXT', 'LONGTEXT'];
        yield 'REAL' => ['REAL', 'DOUBLE'];
        yield 'FLOAT' => ['FLOAT', 'DOUBLE'];
        yield 'DOUBLE' => ['DOUBLE', 'DOUBLE'];
        yield 'BLOB' => ['BLOB', 'LONGBLOB'];
        yield 'NUMERIC' => ['NUMERIC', 'DECIMAL'];
        yield 'DECIMAL' => ['DECIMAL', 'DECIMAL'];
        yield 'BOOLEAN' => ['BOOLEAN', 'TINYINT(1)'];
        yield 'DATE' => ['DATE', 'DATE'];
        yield 'DATETIME' => ['DATETIME', 'DATETIME'];
        yield 'TIMESTAMP' => ['TIMESTAMP', 'DATETIME'];
        yield 'empty' => ['', 'TEXT'];
        yield 'VARCHAR' => ['VARCHAR', 'LONGTEXT'];
        yield 'lowercase' => ['integer', 'BIGINT(20)'];
    }

    #[Test]
    #[DataProvider('typeProvider')]
    public function toMysqlType(string $source, string $expected): void
    {
        self::assertSame($expected, $this->mapper->toMysqlType($source));
    }

    #[Test]
    public function isBinaryOnlyForBlob(): void
    {
        self::assertTrue($this->mapper->isBinary('BLOB'));
        self::assertFalse($this->mapper->isBinary('TEXT'));
        self::assertFalse($this->mapper->isBinary('INTEGER'));
        self::assertFalse($this->mapper->isBinary(''));
    }

    #[Test]
    public function isNumericForNumericTypes(): void
    {
        self::assertTrue($this->mapper->isNumeric('INTEGER'));
        self::assertTrue($this->mapper->isNumeric('REAL'));
        self::assertTrue($this->mapper->isNumeric('NUMERIC'));
        self::assertTrue($this->mapper->isNumeric('BOOLEAN'));
        self::assertFalse($this->mapper->isNumeric('TEXT'));
        self::assertFalse($this->mapper->isNumeric('BLOB'));
        self::assertFalse($this->mapper->isNumeric(''));
    }
}
