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

namespace WPPack\Component\DatabaseExport\Writer;

use WPPack\Component\Database\Schema\ColumnSchema;
use WPPack\Component\Database\Schema\TableSchema;
use WPPack\Component\DatabaseExport\ExportConfiguration;

/**
 * Writes database export in wpress-compatible SQL format.
 *
 * Produces a MySQL-compatible SQL dump with placeholder-based table prefixes
 * and configurable transaction boundaries.
 */
final class WpressSqlWriter implements ExportWriterInterface
{
    private string $tablePrefix = '';
    private string $dbPrefix = '';
    private int $transactionSize = 1000;
    private int $rowCount = 0;
    private bool $inTransaction = false;

    public function begin($stream, ExportConfiguration $config): void
    {
        $this->tablePrefix = $config->tablePrefix;
        $this->dbPrefix = $config->dbPrefix;
        $this->transactionSize = $config->transactionSize;

        fwrite($stream, "-- WPPack Database Export\n");
        fwrite($stream, '-- Generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n");
        fwrite($stream, "--\n\n");
    }

    public function beginTable($stream, TableSchema $schema): void
    {
        $this->rowCount = 0;
        $this->inTransaction = false;

        $placeholderName = $this->replacePrefix($schema->name);
        $createSql = $this->replacePrefixInSql($schema->createTableSql, $schema->name);

        fwrite($stream, "\nDROP TABLE IF EXISTS `{$placeholderName}`;\n");
        fwrite($stream, $createSql . ";\n\n");
    }

    public function writeRows($stream, TableSchema $schema, array $rows): void
    {
        $placeholderName = $this->replacePrefix($schema->name);

        foreach ($rows as $row) {
            if ($this->rowCount % $this->transactionSize === 0) {
                if ($this->inTransaction) {
                    fwrite($stream, "COMMIT;\n");
                }

                fwrite($stream, "START TRANSACTION;\n");
                $this->inTransaction = true;
            }

            $values = [];

            foreach ($schema->columns as $column) {
                $values[] = $this->formatValue($row[$column->name] ?? null, $column);
            }

            fwrite($stream, "INSERT INTO `{$placeholderName}` VALUES (" . implode(',', $values) . ");\n");
            ++$this->rowCount;
        }
    }

    public function endTable($stream, TableSchema $schema): void
    {
        if ($this->inTransaction) {
            fwrite($stream, "COMMIT;\n");
            $this->inTransaction = false;
        }
    }

    public function end($stream): void {}

    private function formatValue(mixed $value, ColumnSchema $column): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($column->isBinary) {
            return '0x' . bin2hex((string) $value);
        }

        if ($column->isNumeric) {
            return (string) $value;
        }

        return $this->escapeString((string) $value);
    }

    private function escapeString(string $value): string
    {
        $escaped = str_replace(
            ['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
            ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
            $value,
        );

        return "'" . $escaped . "'";
    }

    private function replacePrefix(string $tableName): string
    {
        if ($this->dbPrefix === '' || !str_starts_with($tableName, $this->dbPrefix)) {
            return $tableName;
        }

        return $this->tablePrefix . substr($tableName, \strlen($this->dbPrefix));
    }

    private function replacePrefixInSql(string $sql, string $tableName): string
    {
        if ($this->dbPrefix === '') {
            return $sql;
        }

        $quotedOriginal = '`' . $tableName . '`';
        $quotedReplaced = '`' . $this->replacePrefix($tableName) . '`';

        return str_replace($quotedOriginal, $quotedReplaced, $sql);
    }
}
