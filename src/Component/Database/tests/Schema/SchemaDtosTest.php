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

namespace WPPack\Component\Database\Tests\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Schema\ColumnSchema;
use WPPack\Component\Database\Schema\TableSchema;

#[CoversClass(ColumnSchema::class)]
#[CoversClass(TableSchema::class)]
final class SchemaDtosTest extends TestCase
{
    #[Test]
    public function columnSchemaCarriesAllProperties(): void
    {
        $column = new ColumnSchema(
            name: 'id',
            type: 'bigint(20) unsigned',
            nullable: false,
            default: null,
            extra: 'auto_increment',
            isBinary: false,
            isNumeric: true,
        );

        self::assertSame('id', $column->name);
        self::assertSame('bigint(20) unsigned', $column->type);
        self::assertFalse($column->nullable);
        self::assertNull($column->default);
        self::assertSame('auto_increment', $column->extra);
        self::assertFalse($column->isBinary);
        self::assertTrue($column->isNumeric);
    }

    #[Test]
    public function columnSchemaDefaults(): void
    {
        $column = new ColumnSchema(name: 'title', type: 'varchar(255)');

        self::assertFalse($column->nullable);
        self::assertNull($column->default);
        self::assertSame('', $column->extra);
        self::assertFalse($column->isBinary);
        self::assertFalse($column->isNumeric);
    }

    #[Test]
    public function columnSchemaBinaryFlag(): void
    {
        $column = new ColumnSchema(name: 'blob_data', type: 'blob', isBinary: true);

        self::assertTrue($column->isBinary);
    }

    #[Test]
    public function tableSchemaCarriesColumnsAndPrimaryKey(): void
    {
        $id = new ColumnSchema('id', 'bigint(20)', extra: 'auto_increment', isNumeric: true);
        $name = new ColumnSchema('name', 'varchar(255)', nullable: true);
        $table = new TableSchema(
            name: 'wp_users',
            createTableSql: 'CREATE TABLE wp_users (id bigint(20), name varchar(255))',
            columns: [$id, $name],
            primaryKey: ['id'],
        );

        self::assertSame('wp_users', $table->name);
        self::assertStringContainsString('CREATE TABLE', $table->createTableSql);
        self::assertSame([$id, $name], $table->columns);
        self::assertSame(['id'], $table->primaryKey);
    }

    #[Test]
    public function tableSchemaAllowsNullPrimaryKey(): void
    {
        $table = new TableSchema(
            name: 'wp_logs',
            createTableSql: 'CREATE TABLE wp_logs (message TEXT)',
            columns: [new ColumnSchema('message', 'text')],
        );

        self::assertNull($table->primaryKey);
    }

    #[Test]
    public function tableSchemaSupportsCompositePrimaryKey(): void
    {
        $table = new TableSchema(
            name: 'wp_term_relationships',
            createTableSql: '...',
            columns: [
                new ColumnSchema('object_id', 'bigint(20)', isNumeric: true),
                new ColumnSchema('term_taxonomy_id', 'bigint(20)', isNumeric: true),
            ],
            primaryKey: ['object_id', 'term_taxonomy_id'],
        );

        self::assertSame(['object_id', 'term_taxonomy_id'], $table->primaryKey);
    }
}
