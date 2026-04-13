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
use WpPack\Component\Database\Schema\DdlNormalizer;
use WpPack\Component\Database\Schema\TableSchema;

class MysqlSchemaReader implements SchemaReaderInterface
{
    private const BINARY_TYPES = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'];
    private const NUMERIC_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal', 'bit'];

    public function __construct(
        private readonly DdlNormalizer $ddlNormalizer = new DdlNormalizer(),
    ) {}

    public function supports(DatabaseManager $db): bool
    {
        return $db->engine === DatabaseEngine::MySQL;
    }

    public function getTableNames(DatabaseManager $db): array
    {
        $wpdb = $db->wpdb();

        /** @phpstan-ignore property.protected */
        $dbName = $wpdb->dbname;

        $rows = $db->fetchAllAssociative(
            "SHOW FULL TABLES FROM {$db->quoteIdentifier($dbName)} WHERE Table_type = 'BASE TABLE'",
        );

        $names = [];

        foreach ($rows as $row) {
            $values = array_values($row);
            $names[] = $values[0];
        }

        return $names;
    }

    public function readTableSchema(DatabaseManager $db, string $tableName): TableSchema
    {
        $createSql = $this->getCreateTableSql($db, $tableName);
        $createSql = $this->ddlNormalizer->normalize($createSql, $db->engine);
        $columns = $this->getColumns($db, $tableName);
        $primaryKey = $this->getPrimaryKey($db, $tableName);

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
        $primaryKey = $this->getPrimaryKey($db, $tableName);
        $offset = 0;

        do {
            if ($primaryKey !== null) {
                $pk = $db->quoteIdentifier($primaryKey);
                $query = \sprintf(
                    'SELECT t1.* FROM %s AS t1 JOIN (SELECT %s FROM %s ORDER BY %s LIMIT %d, %d) AS t2 USING (%s)',
                    $quoted,
                    $pk,
                    $quoted,
                    $pk,
                    $offset,
                    $batchSize,
                    $pk,
                );
            } else {
                $query = \sprintf(
                    'SELECT * FROM %s ORDER BY 1 LIMIT %d, %d',
                    $quoted,
                    $offset,
                    $batchSize,
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

    protected function getCreateTableSql(DatabaseManager $db, string $tableName): string
    {
        $row = $db->fetchAssociative(
            \sprintf('SHOW CREATE TABLE %s', $db->quoteIdentifier($tableName)),
        );

        return $row['Create Table'] ?? '';
    }

    /**
     * @return list<ColumnSchema>
     */
    protected function getColumns(DatabaseManager $db, string $tableName): array
    {
        $rows = $db->fetchAllAssociative(
            \sprintf('SHOW COLUMNS FROM %s', $db->quoteIdentifier($tableName)),
        );

        $columns = [];

        foreach ($rows as $row) {
            $type = $row['Type'] ?? '';
            $columns[] = new ColumnSchema(
                name: $row['Field'],
                type: $type,
                nullable: ($row['Null'] ?? 'NO') === 'YES',
                default: $row['Default'] ?? null,
                extra: $row['Extra'] ?? '',
                isBinary: $this->isBinaryType($type),
                isNumeric: $this->isNumericType($type),
            );
        }

        return $columns;
    }

    protected function getPrimaryKey(DatabaseManager $db, string $tableName): ?string
    {
        $rows = $db->fetchAllAssociative(
            \sprintf("SHOW KEYS FROM %s WHERE Key_name = 'PRIMARY'", $db->quoteIdentifier($tableName)),
        );

        if ($rows === []) {
            return null;
        }

        return $rows[0]['Column_name'] ?? null;
    }

    private function isBinaryType(string $type): bool
    {
        $lower = strtolower($type);

        foreach (self::BINARY_TYPES as $binaryType) {
            if (str_starts_with($lower, $binaryType)) {
                return true;
            }
        }

        return false;
    }

    private function isNumericType(string $type): bool
    {
        $lower = strtolower($type);

        foreach (self::NUMERIC_TYPES as $numericType) {
            if (str_starts_with($lower, $numericType)) {
                return true;
            }
        }

        return false;
    }
}
