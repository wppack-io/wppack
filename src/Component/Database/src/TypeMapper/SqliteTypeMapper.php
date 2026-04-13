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

namespace WpPack\Component\Database\TypeMapper;

/**
 * Maps SQLite column types to MySQL-compatible equivalents.
 *
 * SQLite uses type affinity rather than strict types. This mapper
 * normalizes affinity-based types to explicit MySQL types.
 */
final class SqliteTypeMapper implements TypeMapperInterface
{
    public function toMysqlType(string $sourceType): string
    {
        $normalized = strtoupper(trim($sourceType));

        return match (true) {
            $normalized === '' => 'TEXT',
            str_starts_with($normalized, 'INT') => 'BIGINT(20)',
            $normalized === 'REAL', $normalized === 'FLOAT', $normalized === 'DOUBLE' => 'DOUBLE',
            $normalized === 'BLOB' => 'LONGBLOB',
            str_starts_with($normalized, 'NUMERIC'), str_starts_with($normalized, 'DECIMAL') => 'DECIMAL',
            $normalized === 'BOOLEAN' => 'TINYINT(1)',
            $normalized === 'DATE' => 'DATE',
            $normalized === 'DATETIME', str_starts_with($normalized, 'TIMESTAMP') => 'DATETIME',
            default => 'LONGTEXT',
        };
    }

    public function isBinary(string $sourceType): bool
    {
        $normalized = strtoupper(trim($sourceType));

        return $normalized === 'BLOB';
    }

    public function isNumeric(string $sourceType): bool
    {
        $normalized = strtoupper(trim($sourceType));

        return match (true) {
            $normalized === '' => false,
            str_starts_with($normalized, 'INT') => true,
            $normalized === 'REAL', $normalized === 'FLOAT', $normalized === 'DOUBLE' => true,
            str_starts_with($normalized, 'NUMERIC'), str_starts_with($normalized, 'DECIMAL') => true,
            $normalized === 'BOOLEAN' => true,
            default => false,
        };
    }
}
