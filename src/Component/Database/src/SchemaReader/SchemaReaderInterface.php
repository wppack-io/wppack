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

namespace WPPack\Component\Database\SchemaReader;

use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\Schema\TableSchema;

interface SchemaReaderInterface
{
    public function supports(DatabaseManager $db): bool;

    /**
     * @return list<string> All table names (full names with prefix)
     */
    public function getTableNames(DatabaseManager $db): array;

    /**
     * Read schema for a single table, returning a MySQL-compatible CREATE TABLE.
     */
    public function readTableSchema(DatabaseManager $db, string $tableName): TableSchema;

    /**
     * Fetch rows in batches via a generator for memory efficiency.
     *
     * @return \Generator<int, list<array<string, mixed>>>
     */
    public function readRows(DatabaseManager $db, string $tableName, int $batchSize): \Generator;
}
