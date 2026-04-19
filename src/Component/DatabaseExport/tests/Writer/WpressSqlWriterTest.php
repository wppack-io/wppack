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
use WPPack\Component\DatabaseExport\Writer\WpressSqlWriter;

final class WpressSqlWriterTest extends TestCase
{
    private WpressSqlWriter $writer;

    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $this->writer = new WpressSqlWriter();
        $this->stream = fopen('php://temp', 'r+b');
    }

    protected function tearDown(): void
    {
        if (\is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    #[Test]
    public function beginWritesHeader(): void
    {
        $config = new ExportConfiguration();
        $this->writer->begin($this->stream, $config);

        $output = $this->getOutput();

        self::assertStringContainsString('-- WPPack Database Export', $output);
        self::assertStringContainsString('-- Generated:', $output);
    }

    #[Test]
    public function beginTableWritesDropAndCreate(): void
    {
        $config = new ExportConfiguration(dbPrefix: 'wp_', tablePrefix: 'WPPACK_PREFIX_');
        $this->writer->begin($this->stream, $config);

        $schema = $this->createTableSchema('wp_posts', 'CREATE TABLE `wp_posts` (id INT)');
        $this->writer->beginTable($this->stream, $schema);

        $output = $this->getOutput();

        self::assertStringContainsString('DROP TABLE IF EXISTS `WPPACK_PREFIX_posts`', $output);
        self::assertStringContainsString('CREATE TABLE `WPPACK_PREFIX_posts` (id INT)', $output);
    }

    #[Test]
    public function writeRowsFormatsValues(): void
    {
        $config = new ExportConfiguration(dbPrefix: 'wp_', tablePrefix: 'PREFIX_');
        $this->writer->begin($this->stream, $config);

        $schema = new TableSchema(
            name: 'wp_posts',
            createTableSql: 'CREATE TABLE `wp_posts` (id INT, title VARCHAR(255))',
            columns: [
                new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true),
                new ColumnSchema(name: 'title', type: 'varchar(255)'),
            ],
        );

        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [
            ['id' => 1, 'title' => 'Hello World'],
            ['id' => 2, 'title' => "It's a test"],
        ]);

        $output = $this->getOutput();

        self::assertStringContainsString("INSERT INTO `PREFIX_posts` VALUES (1,'Hello World')", $output);
        self::assertStringContainsString("INSERT INTO `PREFIX_posts` VALUES (2,'It\\'s a test')", $output);
    }

    #[Test]
    public function writeRowsHandlesNullValues(): void
    {
        $config = new ExportConfiguration();
        $this->writer->begin($this->stream, $config);

        $schema = new TableSchema(
            name: 'test_table',
            createTableSql: 'CREATE TABLE `test_table` (id INT, val TEXT)',
            columns: [
                new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true),
                new ColumnSchema(name: 'val', type: 'text', nullable: true),
            ],
        );

        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [
            ['id' => 1, 'val' => null],
        ]);

        $output = $this->getOutput();

        self::assertStringContainsString('VALUES (1,NULL)', $output);
    }

    #[Test]
    public function writeRowsHandlesBinaryValues(): void
    {
        $config = new ExportConfiguration();
        $this->writer->begin($this->stream, $config);

        $schema = new TableSchema(
            name: 'test_table',
            createTableSql: 'CREATE TABLE `test_table` (id INT, data BLOB)',
            columns: [
                new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true),
                new ColumnSchema(name: 'data', type: 'blob', isBinary: true),
            ],
        );

        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [
            ['id' => 1, 'data' => "Hello"],
        ]);

        $output = $this->getOutput();

        self::assertStringContainsString('VALUES (1,0x48656c6c6f)', $output);
    }

    #[Test]
    public function transactionWrapping(): void
    {
        $config = new ExportConfiguration(transactionSize: 2);
        $this->writer->begin($this->stream, $config);

        $schema = new TableSchema(
            name: 'test',
            createTableSql: 'CREATE TABLE `test` (id INT)',
            columns: [
                new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true),
            ],
        );

        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ]);
        $this->writer->endTable($this->stream, $schema);

        $output = $this->getOutput();

        self::assertSame(2, substr_count($output, 'START TRANSACTION;'));
        self::assertSame(2, substr_count($output, 'COMMIT;'));
    }

    #[Test]
    public function servmaskPrefixCompatibility(): void
    {
        $config = new ExportConfiguration(dbPrefix: 'wp_', tablePrefix: 'SERVMASK_PREFIX_');
        $this->writer->begin($this->stream, $config);

        $schema = $this->createTableSchema('wp_options', 'CREATE TABLE `wp_options` (option_id INT)');
        $this->writer->beginTable($this->stream, $schema);

        $output = $this->getOutput();

        self::assertStringContainsString('`SERVMASK_PREFIX_options`', $output);
    }

    #[Test]
    public function multisitePrefixReplacement(): void
    {
        $config = new ExportConfiguration(dbPrefix: 'wp_', tablePrefix: 'WPPACK_PREFIX_');
        $this->writer->begin($this->stream, $config);

        $schema = $this->createTableSchema('wp_2_posts', 'CREATE TABLE `wp_2_posts` (id INT)');
        $this->writer->beginTable($this->stream, $schema);

        $output = $this->getOutput();

        self::assertStringContainsString('`WPPACK_PREFIX_2_posts`', $output);
    }

    #[Test]
    public function escapesSpecialCharacters(): void
    {
        $config = new ExportConfiguration();
        $this->writer->begin($this->stream, $config);

        $schema = new TableSchema(
            name: 'test',
            createTableSql: 'CREATE TABLE `test` (val TEXT)',
            columns: [
                new ColumnSchema(name: 'val', type: 'text'),
            ],
        );

        $this->writer->beginTable($this->stream, $schema);
        $this->writer->writeRows($this->stream, $schema, [
            ['val' => "line1\nline2"],
            ['val' => "back\\slash"],
            ['val' => "null\x00byte"],
            ['val' => "ctrl-z\x1a"],
        ]);

        $output = $this->getOutput();

        self::assertStringContainsString("'line1\\nline2'", $output);
        self::assertStringContainsString("'back\\\\slash'", $output);
        self::assertStringContainsString("'null\\0byte'", $output);
        self::assertStringContainsString("'ctrl-z\\Z'", $output);
    }

    private function getOutput(): string
    {
        rewind($this->stream);

        return stream_get_contents($this->stream);
    }

    private function createTableSchema(string $name, string $createSql): TableSchema
    {
        return new TableSchema(
            name: $name,
            createTableSql: $createSql,
            columns: [
                new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true),
            ],
        );
    }
}
