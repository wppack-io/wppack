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
 * Maps source database types to MySQL-compatible equivalents.
 */
interface TypeMapperInterface
{
    /**
     * Convert a source database column type to its MySQL equivalent.
     */
    public function toMysqlType(string $sourceType): string;

    /**
     * Determine whether the source type is binary (BLOB, BINARY, etc.).
     */
    public function isBinary(string $sourceType): bool;

    /**
     * Determine whether the source type is numeric (INT, FLOAT, DECIMAL, etc.).
     */
    public function isNumeric(string $sourceType): bool;
}
