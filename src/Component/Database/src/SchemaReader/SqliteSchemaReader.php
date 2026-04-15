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

namespace WpPack\Component\Database\SchemaReader;

use WpPack\Component\Database\DatabaseEngine;
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\Schema\ColumnSchema;
use WpPack\Component\Database\Schema\TableSchema;

/**
 * Schema reader for SQLite databases.
 *
 * Reads SQLite schema (PRAGMA table_info + _mysql_data_types_cache) and
 * produces MySQL-compatible CREATE TABLE SQL for wpress export.
 */
class SqliteSchemaReader implements SchemaReaderInterface
{
    private const SQLITE_TO_MYSQL_TYPE = [
        'INTEGER' => 'bigint(20)',
        'TEXT' => 'longtext',
        'REAL' => 'double',
        'BLOB' => 'longblob',
        'NUMERIC' => 'decimal(10,0)',
    ];

    private const BINARY_TYPES = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'];
    private const NUMERIC_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal', 'bit'];

    public function supports(DatabaseManager $db): bool
    {
        return $db->engine === DatabaseEngine::SQLite;
    }

    public function getTableNames(DatabaseManager $db): array
    {
        $rows = $db->fetchAllAssociative(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '\_%' ESCAPE '\\'",
        );

        return array_column($rows, 'name');
    }

    public function readTableSchema(DatabaseManager $db, string $tableName): TableSchema
    {
        $columns = $this->getColumns($db, $tableName);
        $primaryKey = $this->getPrimaryKey($db, $tableName);
        $indexes = $this->getIndexes($db, $tableName);
        $createSql = $this->buildMysqlCreateTable($tableName, $columns, $primaryKey, $indexes);

        return new TableSchema(
            name: $tableName,
            createTableSql: $createSql,
            columns: $columns,
            primaryKey: $primaryKey,
        );
    }

    public function readRows(DatabaseManager $db, string $tableName, int $batchSize): \Generator
    {
        $quoted = '"' . str_replace('"', '""', $tableName) . '"';
        $offset = 0;

        do {
            $rows = $db->fetchAllAssociative(
                \sprintf('SELECT * FROM %s LIMIT %d OFFSET %d', $quoted, $batchSize, $offset),
            );

            if ($rows === []) {
                break;
            }

            yield $rows;

            $offset += $batchSize;
        } while (\count($rows) === $batchSize);
    }

    /**
     * @return list<ColumnSchema>
     */
    private function getColumns(DatabaseManager $db, string $tableName): array
    {
        $pragmaRows = $db->fetchAllAssociative(
            \sprintf('PRAGMA table_info("%s")', str_replace('"', '""', $tableName)),
        );

        // Try to get original MySQL types from cache
        $cachedTypes = $this->getCachedMysqlTypes($db, $tableName);

        $columns = [];

        foreach ($pragmaRows as $row) {
            $name = $row['name'];
            $sqliteType = strtoupper($row['type'] ?? 'TEXT');
            $mysqlType = $cachedTypes[$name] ?? $this->sqliteToMysqlType($sqliteType);
            $nullable = ($row['notnull'] ?? 1) === 0;

            $columns[] = new ColumnSchema(
                name: $name,
                type: $mysqlType,
                nullable: $nullable,
                default: $row['dflt_value'],
                extra: ($row['pk'] ?? 0) === 1 && str_contains(strtoupper($sqliteType), 'INTEGER') ? 'auto_increment' : '',
                isBinary: $this->isBinaryType($mysqlType),
                isNumeric: $this->isNumericType($mysqlType),
            );
        }

        return $columns;
    }

    /**
     * @return list<string>|null
     */
    private function getPrimaryKey(DatabaseManager $db, string $tableName): ?array
    {
        $rows = $db->fetchAllAssociative(
            \sprintf('PRAGMA table_info("%s")', str_replace('"', '""', $tableName)),
        );

        $pkColumns = [];

        foreach ($rows as $row) {
            if (($row['pk'] ?? 0) > 0) {
                $pkColumns[$row['pk']] = $row['name'];
            }
        }

        if ($pkColumns === []) {
            return null;
        }

        ksort($pkColumns);

        return array_values($pkColumns);
    }

    /**
     * @return list<array{name: string, unique: bool, columns: list<string>}>
     */
    private function getIndexes(DatabaseManager $db, string $tableName): array
    {
        $indexList = $db->fetchAllAssociative(
            \sprintf('PRAGMA index_list("%s")', str_replace('"', '""', $tableName)),
        );

        $indexes = [];

        foreach ($indexList as $idx) {
            $indexName = $idx['name'] ?? '';
            if (str_starts_with($indexName, 'sqlite_autoindex_')) {
                continue;
            }

            $indexInfo = $db->fetchAllAssociative(
                \sprintf('PRAGMA index_info("%s")', str_replace('"', '""', $indexName)),
            );

            $columns = array_column($indexInfo, 'name');

            $indexes[] = [
                'name' => $indexName,
                'unique' => ($idx['unique'] ?? 0) === 1,
                'columns' => $columns,
            ];
        }

        return $indexes;
    }

    /**
     * @return array<string, string>
     */
    private function getCachedMysqlTypes(DatabaseManager $db, string $tableName): array
    {
        try {
            $rows = $db->fetchAllAssociative(
                "SELECT column_or_index, mysql_type FROM _mysql_data_types_cache WHERE \"table\" = ?",
                [$tableName],
            );
        } catch (\Throwable) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $map[$row['column_or_index']] = $row['mysql_type'];
        }

        return $map;
    }

    /**
     * @param list<ColumnSchema> $columns
     * @param list<string>|null $primaryKey
     * @param list<array{name: string, unique: bool, columns: list<string>}> $indexes
     */
    private function buildMysqlCreateTable(
        string $tableName,
        array $columns,
        ?array $primaryKey,
        array $indexes,
    ): string {
        $parts = [];

        foreach ($columns as $col) {
            $def = '  `' . $col->name . '` ' . $col->type;

            if (!$col->nullable) {
                $def .= ' NOT NULL';
            }

            if ($col->extra === 'auto_increment') {
                $def .= ' AUTO_INCREMENT';
            } elseif ($col->default !== null) {
                $def .= " DEFAULT '" . str_replace("'", "''", $col->default) . "'";
            }

            $parts[] = $def;
        }

        if ($primaryKey !== null) {
            $parts[] = '  PRIMARY KEY (`' . implode('`,`', $primaryKey) . '`)';
        }

        foreach ($indexes as $idx) {
            $prefix = $idx['unique'] ? 'UNIQUE KEY' : 'KEY';
            $parts[] = '  ' . $prefix . ' `' . $idx['name'] . '` (`' . implode('`,`', $idx['columns']) . '`)';
        }

        return \sprintf(
            "CREATE TABLE `%s` (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci",
            $tableName,
            implode(",\n", $parts),
        );
    }

    private function sqliteToMysqlType(string $sqliteType): string
    {
        $upper = strtoupper(trim($sqliteType));

        if ($upper === '' || $upper === 'TEXT') {
            return 'longtext';
        }

        return self::SQLITE_TO_MYSQL_TYPE[$upper] ?? 'longtext';
    }

    private function isBinaryType(string $type): bool
    {
        $lower = strtolower($type);

        foreach (self::BINARY_TYPES as $bt) {
            if (str_starts_with($lower, $bt)) {
                return true;
            }
        }

        return false;
    }

    private function isNumericType(string $type): bool
    {
        $lower = strtolower($type);

        foreach (self::NUMERIC_TYPES as $nt) {
            if (str_starts_with($lower, $nt)) {
                return true;
            }
        }

        return false;
    }
}
