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

namespace WPPack\Component\Database\Bridge\PostgreSQL\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\PostgreSQL\TypeMapper\PostgreSQLTypeMapper;

final class PostgreSQLTypeMapperTest extends TestCase
{
    private PostgreSQLTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PostgreSQLTypeMapper();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function typeProvider(): iterable
    {
        yield 'integer' => ['integer', 'INT(11)'];
        yield 'int4' => ['int4', 'INT(11)'];
        yield 'bigint' => ['bigint', 'BIGINT(20)'];
        yield 'int8' => ['int8', 'BIGINT(20)'];
        yield 'smallint' => ['smallint', 'SMALLINT(6)'];
        yield 'serial' => ['serial', 'INT(11)'];
        yield 'bigserial' => ['bigserial', 'BIGINT(20)'];
        yield 'varchar' => ['character varying', 'VARCHAR(255)'];
        yield 'varchar(100)' => ['character varying(100)', 'VARCHAR(100)'];
        yield 'text' => ['text', 'LONGTEXT'];
        yield 'boolean' => ['boolean', 'TINYINT(1)'];
        yield 'bool' => ['bool', 'TINYINT(1)'];
        yield 'timestamp' => ['timestamp without time zone', 'DATETIME'];
        yield 'timestamptz' => ['timestamp with time zone', 'DATETIME'];
        yield 'date' => ['date', 'DATE'];
        yield 'time' => ['time without time zone', 'TIME'];
        yield 'bytea' => ['bytea', 'LONGBLOB'];
        yield 'json' => ['json', 'JSON'];
        yield 'jsonb' => ['jsonb', 'JSON'];
        yield 'uuid' => ['uuid', 'CHAR(36)'];
        yield 'inet' => ['inet', 'VARCHAR(45)'];
        yield 'cidr' => ['cidr', 'VARCHAR(45)'];
        yield 'double precision' => ['double precision', 'DOUBLE'];
        yield 'real' => ['real', 'FLOAT'];
        yield 'numeric' => ['numeric', 'DECIMAL'];
        yield 'numeric(10,2)' => ['numeric(10,2)', 'DECIMAL(10,2)'];
        yield 'numeric(5)' => ['numeric(5)', 'DECIMAL(5)'];
        yield 'money' => ['money', 'DECIMAL(19,2)'];
        yield 'oid' => ['oid', 'BIGINT(20)'];
        yield 'unknown' => ['custom_type', 'LONGTEXT'];
    }

    #[Test]
    #[DataProvider('typeProvider')]
    public function toMySQLType(string $source, string $expected): void
    {
        self::assertSame($expected, $this->mapper->toMySQLType($source));
    }

    #[Test]
    public function isBinaryOnlyForBytea(): void
    {
        self::assertTrue($this->mapper->isBinary('bytea'));
        self::assertFalse($this->mapper->isBinary('text'));
        self::assertFalse($this->mapper->isBinary('integer'));
        self::assertFalse($this->mapper->isBinary('json'));
    }

    #[Test]
    public function isNumericForNumericTypes(): void
    {
        self::assertTrue($this->mapper->isNumeric('integer'));
        self::assertTrue($this->mapper->isNumeric('bigint'));
        self::assertTrue($this->mapper->isNumeric('smallint'));
        self::assertTrue($this->mapper->isNumeric('serial'));
        self::assertTrue($this->mapper->isNumeric('bigserial'));
        self::assertTrue($this->mapper->isNumeric('double precision'));
        self::assertTrue($this->mapper->isNumeric('real'));
        self::assertTrue($this->mapper->isNumeric('boolean'));
        self::assertTrue($this->mapper->isNumeric('numeric(10,2)'));
        self::assertFalse($this->mapper->isNumeric('text'));
        self::assertFalse($this->mapper->isNumeric('bytea'));
        self::assertFalse($this->mapper->isNumeric('json'));
        self::assertFalse($this->mapper->isNumeric('uuid'));
    }
}
