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

namespace WPPack\Component\DatabaseExport;

use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\Schema\TableSchema;
use WPPack\Component\Database\SchemaReader\SchemaReaderInterface;
use WPPack\Component\DatabaseExport\Exception\ExportException;
use WPPack\Component\DatabaseExport\RowTransformer\RowTransformerInterface;
use WPPack\Component\DatabaseExport\TableFilter\TableFilterInterface;
use WPPack\Component\DatabaseExport\Writer\ExportWriterInterface;

final class DatabaseExporter
{
    /**
     * @param iterable<RowTransformerInterface> $rowTransformers
     */
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SchemaReaderInterface $schemaReader,
        private readonly ExportWriterInterface $writer,
        private readonly TableFilterInterface $tableFilter,
        private readonly iterable $rowTransformers = [],
    ) {}

    /**
     * Export the database to the given stream.
     *
     * @param resource $outputStream
     */
    public function export($outputStream, ExportConfiguration $config): void
    {
        $allTables = $this->schemaReader->getTableNames($this->db);
        $tables = $this->tableFilter->filter($allTables);

        if ($tables === []) {
            throw new ExportException('No tables matched the export filter.');
        }

        $this->writer->begin($outputStream, $config);

        foreach ($tables as $tableName) {
            $schema = $this->schemaReader->readTableSchema($this->db, $tableName);

            $this->writer->beginTable($outputStream, $schema);

            foreach ($this->schemaReader->readRows($this->db, $tableName, $config->batchSize) as $batch) {
                $transformed = $this->applyTransformers($batch, $schema, $tableName);

                if ($transformed !== []) {
                    $this->writer->writeRows($outputStream, $schema, $transformed);
                }
            }

            $this->writer->endTable($outputStream, $schema);
        }

        $this->writer->end($outputStream);
    }

    /**
     * Export to a string (convenience method).
     */
    public function exportToString(ExportConfiguration $config): string
    {
        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            throw new ExportException('Failed to open php://temp stream.');
        }

        try {
            $this->export($stream, $config);
            rewind($stream);

            $content = stream_get_contents($stream);

            if ($content === false) {
                throw new ExportException('Failed to read export stream.');
            }

            return $content;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function applyTransformers(array $rows, TableSchema $schema, string $tableName): array
    {
        $hasTransformers = false;

        /** @var list<RowTransformerInterface> $applicable */
        $applicable = [];

        foreach ($this->rowTransformers as $transformer) {
            if ($transformer->supports($tableName)) {
                $applicable[] = $transformer;
                $hasTransformers = true;
            }
        }

        if (!$hasTransformers) {
            return $rows;
        }

        $result = [];

        foreach ($rows as $row) {
            foreach ($applicable as $transformer) {
                $row = $transformer->transform($row, $schema);

                if ($row === null) {
                    continue 2;
                }
            }

            $result[] = $row;
        }

        return $result;
    }
}
