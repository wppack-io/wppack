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

namespace WPPack\Component\DatabaseExport\Tests\Writer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Schema\ColumnSchema;
use WPPack\Component\Database\Schema\TableSchema;
use WPPack\Component\DatabaseExport\ExportConfiguration;
use WPPack\Component\DatabaseExport\Writer\CsvWriter;

final class CsvWriterTest extends TestCase
{
    private CsvWriter $writer;

    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $this->writer = new CsvWriter();
        $this->stream = fopen('php://temp', 'r+b');
    }

    protected function tearDown(): void
    {
        if (\is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    #[Test]
    public function writesTableMarkerAndHeader(): void
    {
        $config = new ExportConfiguration();
        $schema = $this->createSchema('wp_posts', [
            new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true),
            new ColumnSchema(name: 'title', type: 'varchar(255)'),
        ]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema);
        $this->writer->endTable($this->stream, $schema);
        $this->writer->end($this->stream);

        $output = $this->getOutput();
        $lines = explode("\n", trim($output));

        self::assertSame('--- wp_posts ---', $lines[0]);
        self::assertSame('id,title', $lines[1]);
    }

    #[Test]
    public function writesDataRows(): void
    {
        $config = new ExportConfiguration();
        $schema = $this->createSchema('t', [
            new ColumnSchema(name: 'id', type: 'int', isNumeric: true),
            new ColumnSchema(name: 'name', type: 'text'),
        ]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $this->writer->endTable($this->stream, $schema);
        $this->writer->end($this->stream);

        $output = $this->getOutput();
        $lines = explode("\n", trim($output));

        self::assertSame('--- t ---', $lines[0]);
        self::assertSame('id,name', $lines[1]);
        self::assertSame('1,Alice', $lines[2]);
        self::assertSame('2,Bob', $lines[3]);
    }

    #[Test]
    public function escapesQuotesInValues(): void
    {
        $config = new ExportConfiguration();
        $schema = $this->createSchema('t', [new ColumnSchema(name: 'val', type: 'text')]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [
            ['val' => 'He said "hello"'],
        ]);
        $this->writer->endTable($this->stream, $schema);
        $this->writer->end($this->stream);

        $output = $this->getOutput();

        self::assertStringContainsString('"He said ""hello"""', $output);
    }

    #[Test]
    public function encodesBinaryAsBase64(): void
    {
        $config = new ExportConfiguration();
        $schema = $this->createSchema('t', [
            new ColumnSchema(name: 'data', type: 'blob', isBinary: true),
        ]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [['data' => "Hello"]]);
        $this->writer->endTable($this->stream, $schema);
        $this->writer->end($this->stream);

        $output = $this->getOutput();

        self::assertStringContainsString(base64_encode('Hello'), $output);
    }

    #[Test]
    public function handlesNullAsEmptyString(): void
    {
        $config = new ExportConfiguration();
        $schema = $this->createSchema('t', [
            new ColumnSchema(name: 'id', type: 'int', isNumeric: true),
            new ColumnSchema(name: 'val', type: 'text', nullable: true),
        ]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [['id' => 1, 'val' => null]]);
        $this->writer->endTable($this->stream, $schema);
        $this->writer->end($this->stream);

        $lines = explode("\n", trim($this->getOutput()));

        self::assertSame('1,', $lines[2]);
    }

    #[Test]
    public function separatesMultipleTablesWithBlankLine(): void
    {
        $config = new ExportConfiguration();
        $schema1 = $this->createSchema('t1', [new ColumnSchema(name: 'id', type: 'int', isNumeric: true)]);
        $schema2 = $this->createSchema('t2', [new ColumnSchema(name: 'id', type: 'int', isNumeric: true)]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema1);
        $this->writer->writeRows($this->stream, $schema1, [['id' => 1]]);
        $this->writer->endTable($this->stream, $schema1);
        $this->writer->beginTable($this->stream, $schema2);
        $this->writer->writeRows($this->stream, $schema2, [['id' => 2]]);
        $this->writer->endTable($this->stream, $schema2);
        $this->writer->end($this->stream);

        $output = $this->getOutput();

        self::assertStringContainsString("--- t1 ---\n", $output);
        self::assertStringContainsString("\n\n--- t2 ---\n", $output);
    }

    private function getOutput(): string
    {
        rewind($this->stream);

        return stream_get_contents($this->stream);
    }

    /**
     * @param list<ColumnSchema> $columns
     */
    private function createSchema(string $name, array $columns): TableSchema
    {
        return new TableSchema(name: $name, createTableSql: '', columns: $columns);
    }
}
