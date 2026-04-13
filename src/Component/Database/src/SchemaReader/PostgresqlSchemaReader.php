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
use WpPack\Component\Database\TypeMapper\PostgresqlTypeMapper;

/**
 * Schema reader for PostgreSQL databases.
 *
 * Reads schema from information_schema and synthesizes MySQL-compatible
 * CREATE TABLE statements via PostgresqlTypeMapper.
 */
final class PostgresqlSchemaReader implements SchemaReaderInterface
{
    public function __construct(
        private readonly PostgresqlTypeMapper $typeMapper = new PostgresqlTypeMapper(),
    ) {}

    public function supports(DatabaseManager $db): bool
    {
        return $db->engine === DatabaseEngine::PostgreSQL;
    }

    public function getTableNames(DatabaseManager $db): array
    {
        $rows = $db->fetchAllAssociative(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name",
        );

        return array_column($rows, 'table_name');
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
                \sprintf('SELECT * FROM %s ORDER BY 1 LIMIT %d OFFSET %d', $quoted, $batchSize, $offset),
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
            "SELECT column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, "
            . "is_nullable, column_default "
            . "FROM information_schema.columns "
            . "WHERE table_schema = 'public' AND table_name = '{$tableName}' "
            . 'ORDER BY ordinal_position',
        );

        $columns = [];

        foreach ($rows as $row) {
            $sourceType = $this->buildSourceType($row);
            $extra = $this->detectAutoIncrement($row) ? 'auto_increment' : '';

            $columns[] = new ColumnSchema(
                name: $row['column_name'],
                type: $this->typeMapper->toMysqlType($sourceType),
                nullable: ($row['is_nullable'] ?? 'NO') === 'YES',
                default: $this->normalizeDefault($row['column_default'] ?? null),
                extra: $extra,
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
            'SELECT kcu.column_name '
            . 'FROM information_schema.table_constraints tc '
            . 'JOIN information_schema.key_column_usage kcu '
            . '  ON tc.constraint_name = kcu.constraint_name '
            . "  AND tc.table_schema = kcu.table_schema "
            . "WHERE tc.constraint_type = 'PRIMARY KEY' "
            . "  AND tc.table_schema = 'public' "
            . "  AND tc.table_name = '{$tableName}' "
            . 'ORDER BY kcu.ordinal_position',
        );

        if ($rows === []) {
            return null;
        }

        return array_column($rows, 'column_name');
    }

    /**
     * Build the full source type string including precision/length parameters.
     *
     * @param array<string, mixed> $row
     */
    private function buildSourceType(array $row): string
    {
        $dataType = $row['data_type'] ?? '';
        $maxLen = $row['character_maximum_length'] ?? null;
        $precision = $row['numeric_precision'] ?? null;
        $scale = $row['numeric_scale'] ?? null;

        if ($dataType === 'character varying' && $maxLen !== null) {
            return "character varying({$maxLen})";
        }

        if (($dataType === 'numeric' || $dataType === 'decimal') && $precision !== null) {
            if ($scale !== null && (int) $scale > 0) {
                return "numeric({$precision},{$scale})";
            }

            return "numeric({$precision})";
        }

        return $dataType;
    }

    /**
     * Detect if a column uses a sequence (serial/bigserial).
     *
     * @param array<string, mixed> $row
     */
    private function detectAutoIncrement(array $row): bool
    {
        $default = $row['column_default'] ?? '';

        return \is_string($default) && str_starts_with($default, 'nextval(');
    }

    /**
     * Normalize PostgreSQL default values for MySQL compatibility.
     */
    private function normalizeDefault(?string $default): ?string
    {
        if ($default === null) {
            return null;
        }

        // Strip sequence defaults (handled as AUTO_INCREMENT)
        if (str_starts_with($default, 'nextval(')) {
            return null;
        }

        // Strip type casts (e.g., 'value'::character varying)
        if (str_contains($default, '::')) {
            $default = substr($default, 0, (int) strpos($default, '::'));
        }

        return $default;
    }

    /**
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
