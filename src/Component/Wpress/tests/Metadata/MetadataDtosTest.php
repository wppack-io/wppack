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

namespace WPPack\Component\Wpress\Tests\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Wpress\Metadata\CompressionInfo;
use WPPack\Component\Wpress\Metadata\DatabaseInfo;
use WPPack\Component\Wpress\Metadata\PhpInfo;
use WPPack\Component\Wpress\Metadata\PluginInfo;
use WPPack\Component\Wpress\Metadata\ReplaceInfo;
use WPPack\Component\Wpress\Metadata\ServerInfo;
use WPPack\Component\Wpress\Metadata\SiteInfo;
use WPPack\Component\Wpress\Metadata\SiteWordPressInfo;
use WPPack\Component\Wpress\Metadata\WordPressInfo;

#[CoversClass(PluginInfo::class)]
#[CoversClass(ServerInfo::class)]
#[CoversClass(PhpInfo::class)]
#[CoversClass(WordPressInfo::class)]
#[CoversClass(SiteWordPressInfo::class)]
#[CoversClass(DatabaseInfo::class)]
#[CoversClass(CompressionInfo::class)]
#[CoversClass(ReplaceInfo::class)]
#[CoversClass(SiteInfo::class)]
final class MetadataDtosTest extends TestCase
{
    #[Test]
    public function pluginInfoRoundTripsVersion(): void
    {
        $info = PluginInfo::fromArray(['Version' => '1.2.3']);

        self::assertSame('1.2.3', $info->version);
        self::assertSame(['Version' => '1.2.3'], $info->jsonSerialize());
    }

    #[Test]
    public function pluginInfoDefaultsVersionToEmptyString(): void
    {
        self::assertSame('', PluginInfo::fromArray([])->version);
    }

    #[Test]
    public function serverInfoOmitsNullsOnSerialize(): void
    {
        $empty = ServerInfo::fromArray([]);
        self::assertSame([], $empty->jsonSerialize());

        $populated = ServerInfo::fromArray(['.htaccess' => 'Options -Indexes', 'web.config' => '<xml/>']);
        self::assertSame([
            '.htaccess' => 'Options -Indexes',
            'web.config' => '<xml/>',
        ], $populated->jsonSerialize());
    }

    #[Test]
    public function phpInfoCastsIntegerField(): void
    {
        $info = PhpInfo::fromArray(['Version' => '8.4', 'System' => 'Darwin', 'Integer' => '64']);

        self::assertSame('8.4', $info->version);
        self::assertSame('Darwin', $info->system);
        self::assertSame(64, $info->integer);
    }

    #[Test]
    public function phpInfoSerializeFiltersNulls(): void
    {
        $info = PhpInfo::fromArray(['Version' => '8.4']);

        self::assertSame(['Version' => '8.4'], $info->jsonSerialize());
    }

    #[Test]
    public function wordPressInfoRoundTripsAllFields(): void
    {
        $input = [
            'Version' => '6.5',
            'Absolute' => '/var/www/wp/',
            'Content' => '/var/www/wp-content/',
            'Plugins' => '/var/www/wp-content/plugins/',
            'Themes' => ['twentytwentyfour'],
            'Uploads' => '/var/www/wp-content/uploads/',
            'UploadsURL' => 'https://example.com/wp-content/uploads/',
        ];

        $info = WordPressInfo::fromArray($input);

        self::assertSame('6.5', $info->version);
        self::assertSame(['twentytwentyfour'], $info->themes);
        self::assertSame($input, $info->jsonSerialize());
    }

    #[Test]
    public function siteWordPressInfoRoundTripsUploadFields(): void
    {
        $info = SiteWordPressInfo::fromArray(['Uploads' => '/u', 'UploadsURL' => 'https://u']);

        self::assertSame('/u', $info->uploads);
        self::assertSame('https://u', $info->uploadsUrl);
        self::assertSame(['Uploads' => '/u', 'UploadsURL' => 'https://u'], $info->jsonSerialize());
    }

    #[Test]
    public function databaseInfoOmitsNullArrays(): void
    {
        $info = DatabaseInfo::fromArray(['Version' => '8.0', 'Prefix' => 'wp_']);

        self::assertSame('8.0', $info->version);
        self::assertSame('wp_', $info->prefix);

        $out = $info->jsonSerialize();
        self::assertArrayNotHasKey('ExcludedTables', $out);
        self::assertArrayNotHasKey('IncludedTables', $out);
    }

    #[Test]
    public function compressionInfoDefaultsToGzipDisabled(): void
    {
        $info = CompressionInfo::fromArray([]);

        self::assertFalse($info->enabled);
        self::assertSame('gzip', $info->type);
        self::assertSame(['Enabled' => false, 'Type' => 'gzip'], $info->jsonSerialize());
    }

    #[Test]
    public function replaceInfoDefaultsToEmptyLists(): void
    {
        $info = ReplaceInfo::fromArray([]);

        self::assertSame([], $info->oldValues);
        self::assertSame([], $info->newValues);
    }

    #[Test]
    public function replaceInfoRoundTrips(): void
    {
        $info = ReplaceInfo::fromArray([
            'OldValues' => ['http://old.com'],
            'NewValues' => ['https://new.com'],
        ]);

        self::assertSame(['http://old.com'], $info->oldValues);
        self::assertSame(['https://new.com'], $info->newValues);
    }

    #[Test]
    public function siteInfoPopulatesNestedWordPressInfo(): void
    {
        $info = SiteInfo::fromArray([
            'BlogID' => '3',
            'SiteID' => '1',
            'LangID' => '0',
            'SiteURL' => 'https://example.com/site3',
            'HomeURL' => 'https://example.com/site3',
            'Domain' => 'example.com',
            'Path' => '/site3/',
            'Plugins' => ['akismet/akismet.php'],
            'Template' => 'twentytwentyfour',
            'Stylesheet' => 'twentytwentyfour-child',
            'Uploads' => '/site/uploads',
            'UploadsURL' => 'https://example.com/uploads',
            'WordPress' => ['Uploads' => '/u', 'UploadsURL' => 'https://u'],
        ]);

        self::assertSame(3, $info->blogId);
        self::assertSame(1, $info->siteId);
        self::assertSame(0, $info->langId);
        self::assertSame('twentytwentyfour', $info->template);
        self::assertInstanceOf(SiteWordPressInfo::class, $info->wordPress);
        self::assertSame('/u', $info->wordPress->uploads);
    }

    #[Test]
    public function siteInfoOmitsNullFieldsOnSerialize(): void
    {
        $info = SiteInfo::fromArray(['BlogID' => '1', 'SiteURL' => 'https://example.com']);

        $out = $info->jsonSerialize();

        self::assertArrayHasKey('BlogID', $out);
        self::assertArrayHasKey('SiteURL', $out);
        self::assertArrayNotHasKey('Template', $out);
        self::assertArrayNotHasKey('WordPress', $out);
    }
}
