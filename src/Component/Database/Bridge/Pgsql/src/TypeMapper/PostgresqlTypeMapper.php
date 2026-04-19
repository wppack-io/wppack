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

namespace WPPack\Component\Database\Bridge\Pgsql\TypeMapper;

use WPPack\Component\Database\TypeMapper\TypeMapperInterface;

/**
 * Maps PostgreSQL column types to MySQL-compatible equivalents.
 */
final class PostgresqlTypeMapper implements TypeMapperInterface
{
    public function toMysqlType(string $sourceType): string
    {
        $normalized = strtolower(trim($sourceType));

        // Handle types with parameters (e.g., "character varying(255)", "numeric(10,2)")
        if (preg_match('/^character varying\((\d+)\)/', $normalized, $m)) {
            return "VARCHAR({$m[1]})";
        }

        if (preg_match('/^numeric\((\d+),(\d+)\)/', $normalized, $m)) {
            return "DECIMAL({$m[1]},{$m[2]})";
        }

        if (preg_match('/^numeric\((\d+)\)/', $normalized, $m)) {
            return "DECIMAL({$m[1]})";
        }

        return match ($normalized) {
            'integer', 'int4' => 'INT(11)',
            'bigint', 'int8' => 'BIGINT(20)',
            'smallint', 'int2' => 'SMALLINT(6)',
            'serial' => 'INT(11)',
            'bigserial' => 'BIGINT(20)',
            'character varying', 'varchar' => 'VARCHAR(255)',
            'character', 'char' => 'CHAR(1)',
            'text' => 'LONGTEXT',
            'boolean', 'bool' => 'TINYINT(1)',
            'timestamp without time zone', 'timestamp with time zone', 'timestamp' => 'DATETIME',
            'date' => 'DATE',
            'time without time zone', 'time with time zone', 'time' => 'TIME',
            'bytea' => 'LONGBLOB',
            'json', 'jsonb' => 'JSON',
            'uuid' => 'CHAR(36)',
            'inet', 'cidr' => 'VARCHAR(45)',
            'double precision', 'float8' => 'DOUBLE',
            'real', 'float4' => 'FLOAT',
            'numeric', 'decimal' => 'DECIMAL',
            'money' => 'DECIMAL(19,2)',
            'oid' => 'BIGINT(20)',
            default => 'LONGTEXT',
        };
    }

    public function isBinary(string $sourceType): bool
    {
        return strtolower(trim($sourceType)) === 'bytea';
    }

    public function isNumeric(string $sourceType): bool
    {
        $normalized = strtolower(trim($sourceType));

        return match (true) {
            \in_array($normalized, ['integer', 'int4', 'bigint', 'int8', 'smallint', 'int2', 'serial', 'bigserial'], true) => true,
            \in_array($normalized, ['double precision', 'float8', 'real', 'float4', 'money', 'oid'], true) => true,
            \in_array($normalized, ['boolean', 'bool'], true) => true,
            str_starts_with($normalized, 'numeric'), str_starts_with($normalized, 'decimal') => true,
            default => false,
        };
    }
}
