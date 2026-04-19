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

namespace WPPack\Component\DatabaseExport\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\Schema\ColumnSchema;
use WPPack\Component\Database\Schema\TableSchema;
use WPPack\Component\Database\SchemaReader\SchemaReaderInterface;
use WPPack\Component\DatabaseExport\DatabaseExporter;
use WPPack\Component\DatabaseExport\Exception\ExportException;
use WPPack\Component\DatabaseExport\ExportConfiguration;
use WPPack\Component\DatabaseExport\RowTransformer\RowTransformerInterface;
use WPPack\Component\DatabaseExport\RowTransformer\WpOptionsTransformer;
use WPPack\Component\DatabaseExport\TableFilter\TableFilterInterface;
use WPPack\Component\DatabaseExport\Writer\WpressSqlWriter;

final class DatabaseExporterTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->db = new DatabaseManager();
    }

    #[Test]
    public function exportToStringProducesValidSql(): void
    {
        $config = new ExportConfiguration(
            dbPrefix: 'wp_',
            tablePrefix: 'WPPACK_PREFIX_',
        );

        $schema = new TableSchema(
            name: 'wp_posts',
            createTableSql: 'CREATE TABLE `wp_posts` (`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `post_title` text NOT NULL)',
            columns: [
                new ColumnSchema(name: 'ID', type: 'bigint(20) unsigned', isNumeric: true),
                new ColumnSchema(name: 'post_title', type: 'text'),
            ],
            primaryKey: ['ID'],
        );

        $schemaReader = $this->createMock(SchemaReaderInterface::class);
        $schemaReader->method('getTableNames')->willReturn(['wp_posts']);
        $schemaReader->method('readTableSchema')->willReturn($schema);
        $schemaReader->method('readRows')->willReturn($this->generateBatches([
            [['ID' => 1, 'post_title' => 'Hello World']],
        ]));

        $tableFilter = $this->createMock(TableFilterInterface::class);
        $tableFilter->method('filter')->willReturnArgument(0);

        $exporter = new DatabaseExporter(
            db: $this->db,
            schemaReader: $schemaReader,
            writer: new WpressSqlWriter(),
            tableFilter: $tableFilter,
        );

        $sql = $exporter->exportToString($config);

        self::assertStringContainsString('-- WPPack Database Export', $sql);
        self::assertStringContainsString('DROP TABLE IF EXISTS `WPPACK_PREFIX_posts`', $sql);
        self::assertStringContainsString('CREATE TABLE `WPPACK_PREFIX_posts`', $sql);
        self::assertStringContainsString('START TRANSACTION;', $sql);
        self::assertStringContainsString("INSERT INTO `WPPACK_PREFIX_posts` VALUES (1,'Hello World')", $sql);
        self::assertStringContainsString('COMMIT;', $sql);
    }

    #[Test]
    public function exportThrowsWhenNoTablesMatch(): void
    {
        $schemaReader = $this->createMock(SchemaReaderInterface::class);
        $schemaReader->method('getTableNames')->willReturn(['wp_posts']);

        $tableFilter = $this->createMock(TableFilterInterface::class);
        $tableFilter->method('filter')->willReturn([]);

        $exporter = new DatabaseExporter(
            db: $this->db,
            schemaReader: $schemaReader,
            writer: new WpressSqlWriter(),
            tableFilter: $tableFilter,
        );

        $this->expectException(ExportException::class);
        $this->expectExceptionMessage('No tables matched');

        $exporter->exportToString(new ExportConfiguration());
    }

    #[Test]
    public function exportAppliesRowTransformers(): void
    {
        $config = new ExportConfiguration(
            dbPrefix: 'wp_',
            tablePrefix: 'WPPACK_PREFIX_',
            resetActivePlugins: true,
        );

        $optionsSchema = new TableSchema(
            name: 'wp_options',
            createTableSql: 'CREATE TABLE `wp_options` (`option_id` bigint(20), `option_name` varchar(191), `option_value` longtext)',
            columns: [
                new ColumnSchema(name: 'option_id', type: 'bigint(20)', isNumeric: true),
                new ColumnSchema(name: 'option_name', type: 'varchar(191)'),
                new ColumnSchema(name: 'option_value', type: 'longtext'),
            ],
        );

        $schemaReader = $this->createMock(SchemaReaderInterface::class);
        $schemaReader->method('getTableNames')->willReturn(['wp_options']);
        $schemaReader->method('readTableSchema')->willReturn($optionsSchema);
        $schemaReader->method('readRows')->willReturn($this->generateBatches([
            [
                ['option_id' => 1, 'option_name' => 'blogname', 'option_value' => 'My Blog'],
                ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:2:{i:0;s:10:"plugin.php";}'],
                ['option_id' => 3, 'option_name' => '_transient_feed', 'option_value' => 'cached data'],
            ],
        ]));

        $tableFilter = $this->createMock(TableFilterInterface::class);
        $tableFilter->method('filter')->willReturnArgument(0);

        $exporter = new DatabaseExporter(
            db: $this->db,
            schemaReader: $schemaReader,
            writer: new WpressSqlWriter(),
            tableFilter: $tableFilter,
            rowTransformers: [new WpOptionsTransformer($config)],
        );

        $sql = $exporter->exportToString($config);

        // blogname should be present
        self::assertStringContainsString("'My Blog'", $sql);
        // active_plugins should be reset
        self::assertStringContainsString("'a:0:{}'", $sql);
        // _transient_feed should be excluded
        self::assertStringNotContainsString('_transient_feed', $sql);
    }

    #[Test]
    public function exportHandlesMultipleTables(): void
    {
        $config = new ExportConfiguration(dbPrefix: 'wp_', tablePrefix: 'P_');

        $postsSchema = new TableSchema(
            name: 'wp_posts',
            createTableSql: 'CREATE TABLE `wp_posts` (id INT)',
            columns: [new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true)],
        );
        $usersSchema = new TableSchema(
            name: 'wp_users',
            createTableSql: 'CREATE TABLE `wp_users` (id INT)',
            columns: [new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true)],
        );

        $schemaReader = $this->createMock(SchemaReaderInterface::class);
        $schemaReader->method('getTableNames')->willReturn(['wp_posts', 'wp_users']);
        $schemaReader->method('readTableSchema')->willReturnCallback(
            fn(DatabaseManager $db, string $table) => $table === 'wp_posts' ? $postsSchema : $usersSchema,
        );
        $schemaReader->method('readRows')->willReturnCallback(
            fn() => $this->generateBatches([[['id' => 1]]]),
        );

        $tableFilter = $this->createMock(TableFilterInterface::class);
        $tableFilter->method('filter')->willReturnArgument(0);

        $exporter = new DatabaseExporter(
            db: $this->db,
            schemaReader: $schemaReader,
            writer: new WpressSqlWriter(),
            tableFilter: $tableFilter,
        );

        $sql = $exporter->exportToString($config);

        self::assertStringContainsString('DROP TABLE IF EXISTS `P_posts`', $sql);
        self::assertStringContainsString('DROP TABLE IF EXISTS `P_users`', $sql);
        self::assertSame(2, substr_count($sql, 'START TRANSACTION;'));
    }

    #[Test]
    public function exportHandlesEmptyTable(): void
    {
        $config = new ExportConfiguration(dbPrefix: 'wp_', tablePrefix: 'P_');

        $schema = new TableSchema(
            name: 'wp_empty',
            createTableSql: 'CREATE TABLE `wp_empty` (id INT)',
            columns: [new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true)],
        );

        $schemaReader = $this->createMock(SchemaReaderInterface::class);
        $schemaReader->method('getTableNames')->willReturn(['wp_empty']);
        $schemaReader->method('readTableSchema')->willReturn($schema);
        $schemaReader->method('readRows')->willReturn($this->generateBatches([]));

        $tableFilter = $this->createMock(TableFilterInterface::class);
        $tableFilter->method('filter')->willReturnArgument(0);

        $exporter = new DatabaseExporter(
            db: $this->db,
            schemaReader: $schemaReader,
            writer: new WpressSqlWriter(),
            tableFilter: $tableFilter,
        );

        $sql = $exporter->exportToString($config);

        self::assertStringContainsString('DROP TABLE IF EXISTS `P_empty`', $sql);
        self::assertStringContainsString('CREATE TABLE `P_empty`', $sql);
        // No INSERT or TRANSACTION for empty table
        self::assertStringNotContainsString('INSERT INTO', $sql);
        self::assertStringNotContainsString('START TRANSACTION', $sql);
    }

    #[Test]
    public function exportWithTransformerThatSkipsAllRows(): void
    {
        $config = new ExportConfiguration(dbPrefix: 'wp_', tablePrefix: 'P_');

        $schema = new TableSchema(
            name: 'wp_test',
            createTableSql: 'CREATE TABLE `wp_test` (id INT)',
            columns: [new ColumnSchema(name: 'id', type: 'int(11)', isNumeric: true)],
        );

        $schemaReader = $this->createMock(SchemaReaderInterface::class);
        $schemaReader->method('getTableNames')->willReturn(['wp_test']);
        $schemaReader->method('readTableSchema')->willReturn($schema);
        $schemaReader->method('readRows')->willReturn($this->generateBatches([
            [['id' => 1], ['id' => 2]],
        ]));

        $tableFilter = $this->createMock(TableFilterInterface::class);
        $tableFilter->method('filter')->willReturnArgument(0);

        // Transformer that skips all rows
        $transformer = $this->createMock(RowTransformerInterface::class);
        $transformer->method('supports')->willReturn(true);
        $transformer->method('transform')->willReturn(null);

        $exporter = new DatabaseExporter(
            db: $this->db,
            schemaReader: $schemaReader,
            writer: new WpressSqlWriter(),
            tableFilter: $tableFilter,
            rowTransformers: [$transformer],
        );

        $sql = $exporter->exportToString($config);

        self::assertStringContainsString('DROP TABLE IF EXISTS `P_test`', $sql);
        self::assertStringNotContainsString('INSERT INTO', $sql);
    }

    /**
     * @param list<list<array<string, mixed>>> $batches
     *
     * @return \Generator<list<array<string, mixed>>>
     */
    private function generateBatches(array $batches): \Generator
    {
        foreach ($batches as $batch) {
            yield $batch;
        }
    }
}
