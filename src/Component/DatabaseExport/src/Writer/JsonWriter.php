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
 * Writes database export in streaming JSON format.
 *
 * Output structure:
 * {
 *   "tables": {
 *     "table_name": {
 *       "columns": ["col1", "col2"],
 *       "rows": [[val1, val2], ...]
 *     }
 *   }
 * }
 */
final class JsonWriter implements ExportWriterInterface
{
    private bool $firstTable = true;
    private bool $firstRow = true;

    public function begin($stream, ExportConfiguration $config): void
    {
        $this->firstTable = true;

        fwrite($stream, '{"tables":{');
    }

    public function beginTable($stream, TableSchema $schema): void
    {
        $this->firstRow = true;

        if (!$this->firstTable) {
            fwrite($stream, ',');
        }

        $this->firstTable = false;

        $columnNames = array_map(fn($col) => $col->name, $schema->columns);
        $columnsJson = json_encode($columnNames, \JSON_UNESCAPED_UNICODE);

        fwrite($stream, json_encode($schema->name, \JSON_UNESCAPED_UNICODE) . ':{"columns":' . $columnsJson . ',"rows":[');
    }

    public function writeRows($stream, TableSchema $schema, array $rows): void
    {
        foreach ($rows as $row) {
            if (!$this->firstRow) {
                fwrite($stream, ',');
            }

            $this->firstRow = false;

            $values = [];

            foreach ($schema->columns as $column) {
                $value = $row[$column->name] ?? null;

                if ($value === null) {
                    $values[] = null;
                } elseif ($column->isBinary) {
                    $values[] = base64_encode((string) $value);
                } elseif ($column->isNumeric) {
                    $values[] = is_int($value) || is_float($value) ? $value : (int) $value;
                } else {
                    $values[] = (string) $value;
                }
            }

            fwrite($stream, json_encode($values, \JSON_UNESCAPED_UNICODE));
        }
    }

    public function endTable($stream, TableSchema $schema): void
    {
        fwrite($stream, ']}');
    }

    public function end($stream): void
    {
        fwrite($stream, '}}');
    }
}
