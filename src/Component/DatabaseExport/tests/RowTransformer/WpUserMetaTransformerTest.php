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

namespace WpPack\Component\DatabaseExport\Tests\RowTransformer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Schema\ColumnSchema;
use WpPack\Component\Database\Schema\TableSchema;
use WpPack\Component\DatabaseExport\ExportConfiguration;
use WpPack\Component\DatabaseExport\RowTransformer\WpUserMetaTransformer;

final class WpUserMetaTransformerTest extends TestCase
{
    private TableSchema $schema;

    protected function setUp(): void
    {
        $this->schema = new TableSchema(
            name: 'wp_usermeta',
            createTableSql: 'CREATE TABLE `wp_usermeta` (umeta_id INT, user_id INT, meta_key VARCHAR(255), meta_value LONGTEXT)',
            columns: [
                new ColumnSchema(name: 'umeta_id', type: 'bigint(20)', isNumeric: true),
                new ColumnSchema(name: 'user_id', type: 'bigint(20)', isNumeric: true),
                new ColumnSchema(name: 'meta_key', type: 'varchar(255)'),
                new ColumnSchema(name: 'meta_value', type: 'longtext'),
            ],
        );
    }

    #[Test]
    public function supportsUsermetaTable(): void
    {
        $transformer = new WpUserMetaTransformer(new ExportConfiguration());

        self::assertTrue($transformer->supports('wp_usermeta'));
        self::assertFalse($transformer->supports('wp_postmeta'));
        self::assertFalse($transformer->supports('wp_users'));
    }

    #[Test]
    public function defaultConfigExcludesSessionTokens(): void
    {
        $transformer = new WpUserMetaTransformer(new ExportConfiguration());

        $result = $transformer->transform(
            ['umeta_id' => 1, 'user_id' => 1, 'meta_key' => 'session_tokens', 'meta_value' => 'a:1:{...}'],
            $this->schema,
        );

        self::assertNull($result);
    }

    #[Test]
    public function passesNormalMetaThrough(): void
    {
        $transformer = new WpUserMetaTransformer(new ExportConfiguration());

        $row = ['umeta_id' => 1, 'user_id' => 1, 'meta_key' => 'first_name', 'meta_value' => 'John'];
        $result = $transformer->transform($row, $this->schema);

        self::assertSame($row, $result);
    }

    #[Test]
    public function replacesPrefixInMetaKey(): void
    {
        $transformer = new WpUserMetaTransformer(
            new ExportConfiguration(tablePrefix: 'WPPACK_PREFIX_', replacePrefixInValues: true),
            dbPrefix: 'wp_',
        );

        $row = $transformer->transform(
            ['umeta_id' => 1, 'user_id' => 1, 'meta_key' => 'wp_capabilities', 'meta_value' => 'a:1:{...}'],
            $this->schema,
        );

        self::assertSame('WPPACK_PREFIX_capabilities', $row['meta_key']);
    }

    #[Test]
    public function doesNotReplacePrefixWhenDisabled(): void
    {
        $transformer = new WpUserMetaTransformer(
            new ExportConfiguration(tablePrefix: 'WPPACK_PREFIX_', replacePrefixInValues: false),
            dbPrefix: 'wp_',
        );

        $row = $transformer->transform(
            ['umeta_id' => 1, 'user_id' => 1, 'meta_key' => 'wp_capabilities', 'meta_value' => 'a:1:{...}'],
            $this->schema,
        );

        self::assertSame('wp_capabilities', $row['meta_key']);
    }

    #[Test]
    public function customExcludeKeys(): void
    {
        $transformer = new WpUserMetaTransformer(new ExportConfiguration(
            excludeUserMetaKeys: ['session_tokens', 'custom_secret'],
        ));

        self::assertNull($transformer->transform(
            ['umeta_id' => 1, 'user_id' => 1, 'meta_key' => 'custom_secret', 'meta_value' => 'secret'],
            $this->schema,
        ));
    }
}
