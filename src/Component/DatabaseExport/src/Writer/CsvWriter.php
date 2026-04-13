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

namespace WpPack\Component\DatabaseExport\Writer;

use WpPack\Component\Database\Schema\TableSchema;
use WpPack\Component\DatabaseExport\ExportConfiguration;

/**
 * Writes database export in CSV format (RFC 4180).
 *
 * Multiple tables are separated by marker lines: "--- table_name ---"
 * Each table section has a header row followed by data rows.
 */
final class CsvWriter implements ExportWriterInterface
{
    private bool $firstTable = true;

    public function begin($stream, ExportConfiguration $config): void
    {
        $this->firstTable = true;
    }

    public function beginTable($stream, TableSchema $schema): void
    {
        if (!$this->firstTable) {
            fwrite($stream, "\n");
        }

        $this->firstTable = false;

        fwrite($stream, "--- {$schema->name} ---\n");

        // Header row
        $columnNames = array_map(fn ($col) => $col->name, $schema->columns);
        fputcsv($stream, $columnNames);
    }

    public function writeRows($stream, TableSchema $schema, array $rows): void
    {
        foreach ($rows as $row) {
            $values = [];

            foreach ($schema->columns as $column) {
                $value = $row[$column->name] ?? null;

                if ($value === null) {
                    $values[] = '';
                } elseif ($column->isBinary) {
                    $values[] = base64_encode((string) $value);
                } else {
                    $values[] = (string) $value;
                }
            }

            fputcsv($stream, $values);
        }
    }

    public function endTable($stream, TableSchema $schema): void {}

    public function end($stream): void {}
}
