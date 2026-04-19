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
use WPPack\Component\DatabaseExport\Writer\JsonWriter;

final class JsonWriterTest extends TestCase
{
    private JsonWriter $writer;

    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $this->writer = new JsonWriter();
        $this->stream = fopen('php://temp', 'r+b');
    }

    protected function tearDown(): void
    {
        if (\is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    #[Test]
    public function producesValidJsonForSingleTable(): void
    {
        $config = new ExportConfiguration();
        $schema = $this->createSchema('wp_posts', [
            new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true),
            new ColumnSchema(name: 'title', type: 'varchar(255)'),
        ]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [
            ['id' => 1, 'title' => 'Hello'],
            ['id' => 2, 'title' => 'World'],
        ]);
        $this->writer->endTable($this->stream, $schema);
        $this->writer->end($this->stream);

        $json = json_decode($this->getOutput(), true);

        self::assertIsArray($json);
        self::assertArrayHasKey('tables', $json);
        self::assertArrayHasKey('wp_posts', $json['tables']);
        self::assertSame(['id', 'title'], $json['tables']['wp_posts']['columns']);
        self::assertSame([[1, 'Hello'], [2, 'World']], $json['tables']['wp_posts']['rows']);
    }

    #[Test]
    public function producesValidJsonForMultipleTables(): void
    {
        $config = new ExportConfiguration();
        $schema1 = $this->createSchema('t1', [new ColumnSchema(name: 'id', type: 'int', isNumeric: true)]);
        $schema2 = $this->createSchema('t2', [new ColumnSchema(name: 'name', type: 'text')]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema1);
        $this->writer->writeRows($this->stream, $schema1, [['id' => 1]]);
        $this->writer->endTable($this->stream, $schema1);
        $this->writer->beginTable($this->stream, $schema2);
        $this->writer->writeRows($this->stream, $schema2, [['name' => 'test']]);
        $this->writer->endTable($this->stream, $schema2);
        $this->writer->end($this->stream);

        $json = json_decode($this->getOutput(), true);

        self::assertCount(2, $json['tables']);
        self::assertArrayHasKey('t1', $json['tables']);
        self::assertArrayHasKey('t2', $json['tables']);
    }

    #[Test]
    public function handlesNullValues(): void
    {
        $config = new ExportConfiguration();
        $schema = $this->createSchema('t', [
            new ColumnSchema(name: 'val', type: 'text', nullable: true),
        ]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [['val' => null]]);
        $this->writer->endTable($this->stream, $schema);
        $this->writer->end($this->stream);

        $json = json_decode($this->getOutput(), true);

        self::assertSame([[null]], $json['tables']['t']['rows']);
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

        $json = json_decode($this->getOutput(), true);

        self::assertSame([[base64_encode('Hello')]], $json['tables']['t']['rows']);
    }

    #[Test]
    public function handlesEmptyTable(): void
    {
        $config = new ExportConfiguration();
        $schema = $this->createSchema('t', [new ColumnSchema(name: 'id', type: 'int', isNumeric: true)]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema);
        $this->writer->endTable($this->stream, $schema);
        $this->writer->end($this->stream);

        $json = json_decode($this->getOutput(), true);

        self::assertSame([], $json['tables']['t']['rows']);
    }

    #[Test]
    public function handlesUnicodeStrings(): void
    {
        $config = new ExportConfiguration();
        $schema = $this->createSchema('t', [new ColumnSchema(name: 'name', type: 'text')]);

        $this->writer->begin($this->stream, $config);
        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [['name' => 'テスト']]);
        $this->writer->endTable($this->stream, $schema);
        $this->writer->end($this->stream);

        $json = json_decode($this->getOutput(), true);

        self::assertSame([['テスト']], $json['tables']['t']['rows']);
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
