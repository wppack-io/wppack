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
use WpPack\Component\DatabaseExport\RowTransformer\WpOptionsTransformer;

final class WpOptionsTransformerTest extends TestCase
{
    private TableSchema $schema;

    protected function setUp(): void
    {
        $this->schema = new TableSchema(
            name: 'wp_options',
            createTableSql: 'CREATE TABLE `wp_options` (option_id INT, option_name VARCHAR(191), option_value LONGTEXT)',
            columns: [
                new ColumnSchema(name: 'option_id', type: 'bigint(20)', isNumeric: true),
                new ColumnSchema(name: 'option_name', type: 'varchar(191)'),
                new ColumnSchema(name: 'option_value', type: 'longtext'),
            ],
        );
    }

    #[Test]
    public function supportsOptionsTable(): void
    {
        $transformer = new WpOptionsTransformer(new ExportConfiguration());

        self::assertTrue($transformer->supports('wp_options'));
        self::assertTrue($transformer->supports('wp_2_options'));
        self::assertFalse($transformer->supports('wp_posts'));
    }

    #[Test]
    public function resetsActivePlugins(): void
    {
        $transformer = new WpOptionsTransformer(new ExportConfiguration(resetActivePlugins: true));

        $row = $transformer->transform(
            ['option_id' => 1, 'option_name' => 'active_plugins', 'option_value' => 'a:2:{...}'],
            $this->schema,
        );

        self::assertSame('a:0:{}', $row['option_value']);
    }

    #[Test]
    public function doesNotResetActivePluginsWhenDisabled(): void
    {
        $transformer = new WpOptionsTransformer(new ExportConfiguration(resetActivePlugins: false));

        $row = $transformer->transform(
            ['option_id' => 1, 'option_name' => 'active_plugins', 'option_value' => 'a:2:{...}'],
            $this->schema,
        );

        self::assertSame('a:2:{...}', $row['option_value']);
    }

    #[Test]
    public function resetsTheme(): void
    {
        $transformer = new WpOptionsTransformer(new ExportConfiguration(resetTheme: true));

        $template = $transformer->transform(
            ['option_id' => 1, 'option_name' => 'template', 'option_value' => 'flavor'],
            $this->schema,
        );
        $stylesheet = $transformer->transform(
            ['option_id' => 2, 'option_name' => 'stylesheet', 'option_value' => 'flavor-child'],
            $this->schema,
        );

        self::assertSame('', $template['option_value']);
        self::assertSame('', $stylesheet['option_value']);
    }

    #[Test]
    public function skipsExcludedOptionPrefixes(): void
    {
        $transformer = new WpOptionsTransformer(new ExportConfiguration(
            excludeOptionPrefixes: ['ai1wm_', '_transient_'],
        ));

        $result1 = $transformer->transform(
            ['option_id' => 1, 'option_name' => 'ai1wm_status', 'option_value' => 'running'],
            $this->schema,
        );
        $result2 = $transformer->transform(
            ['option_id' => 2, 'option_name' => '_transient_feed', 'option_value' => 'data'],
            $this->schema,
        );
        $result3 = $transformer->transform(
            ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'My Blog'],
            $this->schema,
        );

        self::assertNull($result1);
        self::assertNull($result2);
        self::assertNotNull($result3);
    }

    #[Test]
    public function passesNormalRowsThrough(): void
    {
        $transformer = new WpOptionsTransformer(new ExportConfiguration(excludeOptionPrefixes: []));

        $row = ['option_id' => 1, 'option_name' => 'blogname', 'option_value' => 'My Blog'];
        $result = $transformer->transform($row, $this->schema);

        self::assertSame($row, $result);
    }

    #[Test]
    public function defaultConfigExcludesTransients(): void
    {
        $transformer = new WpOptionsTransformer(new ExportConfiguration());

        self::assertNull($transformer->transform(
            ['option_id' => 1, 'option_name' => '_transient_timeout_feed', 'option_value' => '123'],
            $this->schema,
        ));
        self::assertNull($transformer->transform(
            ['option_id' => 2, 'option_name' => '_site_transient_update_core', 'option_value' => 'data'],
            $this->schema,
        ));
        self::assertNull($transformer->transform(
            ['option_id' => 3, 'option_name' => '_wc_session_abc123', 'option_value' => 'session'],
            $this->schema,
        ));
    }

    #[Test]
    public function replacesPrefixInOptionName(): void
    {
        $transformer = new WpOptionsTransformer(
            new ExportConfiguration(tablePrefix: 'WPPACK_PREFIX_', replacePrefixInValues: true),
            dbPrefix: 'wp_',
        );

        $row = $transformer->transform(
            ['option_id' => 1, 'option_name' => 'wp_user_roles', 'option_value' => 'a:5:{...}'],
            $this->schema,
        );

        self::assertSame('WPPACK_PREFIX_user_roles', $row['option_name']);
    }

    #[Test]
    public function doesNotReplaceReservedOptionNames(): void
    {
        $transformer = new WpOptionsTransformer(
            new ExportConfiguration(tablePrefix: 'WPPACK_PREFIX_', replacePrefixInValues: true),
            dbPrefix: 'wp_',
        );

        $row = $transformer->transform(
            ['option_id' => 1, 'option_name' => 'wp_page_for_privacy_policy', 'option_value' => '3'],
            $this->schema,
        );

        self::assertSame('wp_page_for_privacy_policy', $row['option_name']);
    }
}
