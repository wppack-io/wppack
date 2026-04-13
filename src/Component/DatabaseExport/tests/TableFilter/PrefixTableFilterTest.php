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

namespace WpPack\Component\DatabaseExport\Tests\TableFilter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DatabaseExport\ExportConfiguration;
use WpPack\Component\DatabaseExport\TableFilter\PrefixTableFilter;

final class PrefixTableFilterTest extends TestCase
{
    private const ALL_TABLES = [
        'wp_posts',
        'wp_postmeta',
        'wp_options',
        'wp_users',
        'wp_usermeta',
        'wp_blogs',
        'wp_site',
        'wp_sitemeta',
        'wp_2_posts',
        'wp_2_options',
        'wp_3_posts',
        'wp_3_options',
        'wbk_services',
    ];

    #[Test]
    public function allTablesWithNoBlogIdFilter(): void
    {
        $filter = new PrefixTableFilter(new ExportConfiguration(dbPrefix: 'wp_',));

        $result = $filter->filter(self::ALL_TABLES);

        self::assertContains('wp_posts', $result);
        self::assertContains('wp_users', $result);
        self::assertContains('wp_2_posts', $result);
        self::assertContains('wp_3_options', $result);
        // wbk_ not included without additionalPrefixes
        self::assertNotContains('wbk_services', $result);
    }

    #[Test]
    public function mainSiteOnly(): void
    {
        $filter = new PrefixTableFilter(new ExportConfiguration(dbPrefix: 'wp_',blogIds: [1]));

        $result = $filter->filter(self::ALL_TABLES);

        self::assertContains('wp_posts', $result);
        self::assertContains('wp_options', $result);
        self::assertContains('wp_users', $result);  // global
        self::assertNotContains('wp_2_posts', $result);
        self::assertNotContains('wp_3_posts', $result);
    }

    #[Test]
    public function subsiteOnly(): void
    {
        $filter = new PrefixTableFilter(new ExportConfiguration(dbPrefix: 'wp_',blogIds: [2]));

        $result = $filter->filter(self::ALL_TABLES);

        self::assertContains('wp_2_posts', $result);
        self::assertContains('wp_2_options', $result);
        self::assertContains('wp_users', $result);  // global always included
        self::assertContains('wp_blogs', $result);  // global always included
        self::assertNotContains('wp_posts', $result);  // main site
        self::assertNotContains('wp_3_posts', $result);
    }

    #[Test]
    public function multipleBlogIds(): void
    {
        $filter = new PrefixTableFilter(new ExportConfiguration(dbPrefix: 'wp_',blogIds: [1, 3]));

        $result = $filter->filter(self::ALL_TABLES);

        self::assertContains('wp_posts', $result);      // blog 1
        self::assertContains('wp_3_posts', $result);     // blog 3
        self::assertContains('wp_users', $result);       // global
        self::assertNotContains('wp_2_posts', $result);  // blog 2
    }

    #[Test]
    public function additionalPrefixes(): void
    {
        $filter = new PrefixTableFilter(new ExportConfiguration(dbPrefix: 'wp_',
            additionalPrefixes: ['wbk_'],
        ));

        $result = $filter->filter(self::ALL_TABLES);

        self::assertContains('wbk_services', $result);
        self::assertContains('wp_posts', $result);
    }

    #[Test]
    public function excludeTables(): void
    {
        $filter = new PrefixTableFilter(new ExportConfiguration(dbPrefix: 'wp_',
            excludeTables: ['postmeta'],
        ));

        $result = $filter->filter(self::ALL_TABLES);

        self::assertContains('wp_posts', $result);
        self::assertNotContains('wp_postmeta', $result);
    }

    #[Test]
    public function includeTables(): void
    {
        $filter = new PrefixTableFilter(new ExportConfiguration(dbPrefix: 'wp_',
            includeTables: ['posts', 'options'],
        ));

        $result = $filter->filter(self::ALL_TABLES);

        self::assertContains('wp_posts', $result);
        self::assertContains('wp_options', $result);
        self::assertNotContains('wp_users', $result);
    }
}
