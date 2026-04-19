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

namespace WPPack\Component\Database\TypeMapper;

/**
 * Handles MariaDB-specific column type detection.
 *
 * MariaDB types like INET4, INET6, UUID, XMLTYPE, VECTOR are detected
 * for proper isBinary/isNumeric classification. The actual DDL type conversion
 * is handled by DdlNormalizer.
 */
final class MariadbTypeMapper implements TypeMapperInterface
{
    private const BINARY_TYPES = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'];
    private const NUMERIC_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal', 'bit'];

    public function toMysqlType(string $sourceType): string
    {
        $normalized = strtolower(trim($sourceType));

        return match (true) {
            $normalized === 'inet4' => 'VARCHAR(15)',
            $normalized === 'inet6' => 'VARCHAR(45)',
            $normalized === 'uuid' => 'CHAR(36)',
            $normalized === 'xmltype' => 'LONGTEXT',
            str_starts_with($normalized, 'vector') => 'BLOB',
            default => $sourceType,
        };
    }

    public function isBinary(string $sourceType): bool
    {
        $lower = strtolower(trim($sourceType));

        if (str_starts_with($lower, 'vector')) {
            return true;
        }

        foreach (self::BINARY_TYPES as $type) {
            if (str_starts_with($lower, $type)) {
                return true;
            }
        }

        return false;
    }

    public function isNumeric(string $sourceType): bool
    {
        $lower = strtolower(trim($sourceType));

        foreach (self::NUMERIC_TYPES as $type) {
            if (str_starts_with($lower, $type)) {
                return true;
            }
        }

        return false;
    }
}
