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
 * Schema reader for PostgreSQL databases.
 *
 * Reads PostgreSQL schema (information_schema) and produces
 * MySQL-compatible CREATE TABLE SQL for wpress export.
 */
class PostgresqlSchemaReader implements SchemaReaderInterface
{
    private const PGSQL_TO_MYSQL_TYPE = [
        'bigint' => 'bigint(20)',
        'bigserial' => 'bigint(20) unsigned',
        'integer' => 'int(11)',
        'serial' => 'int(11) unsigned',
        'smallint' => 'smallint(6)',
        'smallserial' => 'smallint(6) unsigned',
        'boolean' => 'tinyint(1)',
        'double precision' => 'double',
        'real' => 'float',
        'numeric' => 'decimal',
        'text' => 'longtext',
        'character varying' => 'varchar',
        'character' => 'char',
        'bytea' => 'longblob',
        'timestamp without time zone' => 'datetime',
        'timestamp with time zone' => 'datetime',
        'date' => 'date',
        'time without time zone' => 'time',
        'jsonb' => 'json',
        'json' => 'json',
        'uuid' => 'varchar(36)',
    ];

    private const BINARY_TYPES = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'];
    private const NUMERIC_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal', 'bit'];

    public function supports(DatabaseManager $db): bool
    {
        return $db->engine === DatabaseEngine::PostgreSQL;
    }

    public function getTableNames(DatabaseManager $db): array
    {
        $rows = $db->fetchAllAssociative(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
             ORDER BY table_name",
        );

        return array_column($rows, 'table_name');
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
        $primaryKey = $this->getPrimaryKey($db, $tableName);
        $offset = 0;

        do {
            if ($primaryKey !== null) {
                $pkColumns = implode(', ', array_map(
                    static fn(string $c): string => '"' . str_replace('"', '""', $c) . '"',
                    $primaryKey,
                ));
                $query = \sprintf(
                    'SELECT * FROM %s ORDER BY %s LIMIT %d OFFSET %d',
                    $quoted,
                    $pkColumns,
                    $batchSize,
                    $offset,
                );
            } else {
                $query = \sprintf(
                    'SELECT * FROM %s ORDER BY 1 LIMIT %d OFFSET %d',
                    $quoted,
                    $batchSize,
                    $offset,
                );
            }

            $rows = $db->fetchAllAssociative($query);

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
        $rows = $db->fetchAllAssociative(
            "SELECT column_name, data_type, character_maximum_length, numeric_precision,
                    numeric_scale, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = ?
             ORDER BY ordinal_position",
            [$tableName],
        );

        $columns = [];

        foreach ($rows as $row) {
            $pgsqlType = strtolower($row['data_type'] ?? 'text');
            $mysqlType = $this->pgsqlToMysqlType($pgsqlType, $row);
            $nullable = ($row['is_nullable'] ?? 'YES') === 'YES';
            $default = $row['column_default'] ?? null;
            $isAutoIncrement = $default !== null && str_contains($default, 'nextval(');

            // Clean up default: strip PostgreSQL casts and nextval
            if ($isAutoIncrement) {
                $default = null;
            } elseif ($default !== null) {
                // Strip ::type casts: 'value'::character varying → value
                $default = preg_replace("/^'(.*?)'::.*$/", '$1', $default);
            }

            $columns[] = new ColumnSchema(
                name: $row['column_name'],
                type: $mysqlType,
                nullable: $nullable,
                default: $default,
                extra: $isAutoIncrement ? 'auto_increment' : '',
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
            "SELECT kcu.column_name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
               AND tc.table_schema = kcu.table_schema
             WHERE tc.table_schema = 'public'
               AND tc.table_name = ?
               AND tc.constraint_type = 'PRIMARY KEY'
             ORDER BY kcu.ordinal_position",
            [$tableName],
        );

        if ($rows === []) {
            return null;
        }

        return array_column($rows, 'column_name');
    }

    /**
     * @return list<array{name: string, unique: bool, columns: list<string>}>
     */
    private function getIndexes(DatabaseManager $db, string $tableName): array
    {
        $rows = $db->fetchAllAssociative(
            "SELECT i.relname AS index_name,
                    ix.indisunique AS is_unique,
                    a.attname AS column_name
             FROM pg_index ix
             JOIN pg_class t ON t.oid = ix.indrelid
             JOIN pg_class i ON i.oid = ix.indexrelid
             JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE n.nspname = 'public'
               AND t.relname = ?
               AND NOT ix.indisprimary
             ORDER BY i.relname, a.attnum",
            [$tableName],
        );

        $grouped = [];

        foreach ($rows as $row) {
            $name = $row['index_name'];
            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'name' => $name,
                    'unique' => (bool) $row['is_unique'],
                    'columns' => [],
                ];
            }
            $grouped[$name]['columns'][] = $row['column_name'];
        }

        return array_values($grouped);
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

    /**
     * @param array<string, mixed> $row
     */
    private function pgsqlToMysqlType(string $pgsqlType, array $row): string
    {
        $mysqlBase = self::PGSQL_TO_MYSQL_TYPE[$pgsqlType] ?? 'longtext';

        // Add length for varchar/char
        if ($pgsqlType === 'character varying' && isset($row['character_maximum_length'])) {
            return 'varchar(' . $row['character_maximum_length'] . ')';
        }

        if ($pgsqlType === 'character' && isset($row['character_maximum_length'])) {
            return 'char(' . $row['character_maximum_length'] . ')';
        }

        if ($pgsqlType === 'numeric' && isset($row['numeric_precision'])) {
            $scale = $row['numeric_scale'] ?? 0;

            return 'decimal(' . $row['numeric_precision'] . ',' . $scale . ')';
        }

        return $mysqlBase;
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
