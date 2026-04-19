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

namespace WPPack\Component\Database\Bridge\Sqlite\SchemaReader;

use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\Schema\ColumnSchema;
use WPPack\Component\Database\Schema\TableSchema;
use WPPack\Component\Database\SchemaReader\SchemaReaderInterface;
use WPPack\Component\Database\Bridge\Sqlite\TypeMapper\SqliteTypeMapper;

/**
 * Schema reader for SQLite databases.
 *
 * Reads schema from sqlite_master and PRAGMA queries, then synthesizes
 * MySQL-compatible CREATE TABLE statements via SqliteTypeMapper.
 */
final class SqliteSchemaReader implements SchemaReaderInterface
{
    public function __construct(
        private readonly SqliteTypeMapper $typeMapper = new SqliteTypeMapper(),
    ) {}

    public function supports(DatabaseManager $db): bool
    {
        return $db->engine === 'sqlite';
    }

    public function getTableNames(DatabaseManager $db): array
    {
        $rows = $db->fetchAllAssociative(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
        );

        return array_column($rows, 'name');
    }

    public function readTableSchema(DatabaseManager $db, string $tableName): TableSchema
    {
        $columns = $this->getColumns($db, $tableName);
        $primaryKey = $this->getPrimaryKey($db, $tableName);
        $createSql = $this->synthesizeCreateTable($tableName, $columns, $primaryKey);

        return new TableSchema(
            name: $tableName,
            createTableSql: $createSql,
            columns: $columns,
            primaryKey: $primaryKey,
        );
    }

    public function readRows(DatabaseManager $db, string $tableName, int $batchSize): \Generator
    {
        $quoted = $db->quoteIdentifier($tableName);
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
        $rows = $db->fetchAllAssociative(
            \sprintf('PRAGMA table_info(%s)', $db->quoteIdentifier($tableName)),
        );

        $columns = [];

        // Count PK columns to detect single-column PK (required for auto_increment)
        $pkCount = 0;

        foreach ($rows as $row) {
            if (($row['pk'] ?? 0) > 0) {
                ++$pkCount;
            }
        }

        foreach ($rows as $row) {
            $sourceType = $row['type'] ?? '';
            $isPk = ($row['pk'] ?? 0) > 0;

            // SQLite only auto-increments a single INTEGER PRIMARY KEY column
            $isAutoIncrement = $isPk
                && $pkCount === 1
                && strtoupper(trim($sourceType)) === 'INTEGER';

            $columns[] = new ColumnSchema(
                name: $row['name'],
                type: $this->typeMapper->toMysqlType($sourceType),
                nullable: ($row['notnull'] ?? 1) == 0,
                default: $row['dflt_value'] ?? null,
                extra: $isAutoIncrement ? 'auto_increment' : '',
                isBinary: $this->typeMapper->isBinary($sourceType),
                isNumeric: $this->typeMapper->isNumeric($sourceType),
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
            \sprintf('PRAGMA table_info(%s)', $db->quoteIdentifier($tableName)),
        );

        $pkColumns = [];

        foreach ($rows as $row) {
            if (($row['pk'] ?? 0) > 0) {
                $pkColumns[] = $row['name'];
            }
        }

        return $pkColumns !== [] ? $pkColumns : null;
    }

    /**
     * Synthesize a MySQL-compatible CREATE TABLE statement from column metadata.
     *
     * @param list<ColumnSchema> $columns
     * @param list<string>|null  $primaryKey
     */
    private function synthesizeCreateTable(string $tableName, array $columns, ?array $primaryKey): string
    {
        $parts = [];

        foreach ($columns as $column) {
            $def = \sprintf('  `%s` %s', $column->name, $column->type);

            if (!$column->nullable) {
                $def .= ' NOT NULL';
            }

            if ($column->extra !== '') {
                $def .= ' ' . strtoupper($column->extra);
            }

            $parts[] = $def;
        }

        if ($primaryKey !== null) {
            $pkCols = implode('`, `', $primaryKey);
            $parts[] = "  PRIMARY KEY (`{$pkCols}`)";
        }

        $columnDefs = implode(",\n", $parts);

        return "CREATE TABLE `{$tableName}` (\n{$columnDefs}\n) ENGINE=InnoDB";
    }
}
