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

namespace WpPack\Component\Wpress\Tests\Metadata;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Wpress\Metadata\CompressionInfo;
use WpPack\Component\Wpress\Metadata\DatabaseInfo;
use WpPack\Component\Wpress\Metadata\PackageMetadata;
use WpPack\Component\Wpress\Metadata\PhpInfo;
use WpPack\Component\Wpress\Metadata\PluginInfo;
use WpPack\Component\Wpress\Metadata\ReplaceInfo;
use WpPack\Component\Wpress\Metadata\ServerInfo;
use WpPack\Component\Wpress\Metadata\WordPressInfo;

final class PackageMetadataTest extends TestCase
{
    #[Test]
    public function jsonRoundTrip(): void
    {
        $metadata = new PackageMetadata(
            siteUrl: 'https://example.com',
            homeUrl: 'https://example.com',
            internalSiteUrl: 'https://example.com',
            internalHomeUrl: 'https://example.com',
            plugin: new PluginInfo(version: '7.102'),
            wordPress: new WordPressInfo(
                version: '6.4.2',
                absolute: '/var/www/html/',
                content: '/var/www/html/wp-content',
                plugins: '/var/www/html/wp-content/plugins',
                themes: ['/var/www/html/wp-content/themes'],
                uploads: '/var/www/html/wp-content/uploads',
                uploadsUrl: 'https://example.com/wp-content/uploads',
            ),
            database: new DatabaseInfo(
                version: '8.0.35',
                charset: 'utf8mb4',
                collate: 'utf8mb4_unicode_ci',
                prefix: 'wp_',
                excludedTables: [],
                includedTables: [],
            ),
            php: new PhpInfo(version: '8.1.0', system: 'Linux', integer: 8),
            plugins: ['akismet/akismet.php'],
            template: 'flavor',
            stylesheet: 'flavor-child',
        );

        $json = json_encode($metadata, \JSON_PRETTY_PRINT);
        $restored = PackageMetadata::fromJson($json);

        self::assertSame('https://example.com', $restored->siteUrl);
        self::assertSame('https://example.com', $restored->homeUrl);
        self::assertSame('7.102', $restored->plugin->version);
        self::assertSame('6.4.2', $restored->wordPress->version);
        self::assertSame('8.0.35', $restored->database->version);
        self::assertSame('utf8mb4', $restored->database->charset);
        self::assertSame('8.1.0', $restored->php->version);
        self::assertSame('Linux', $restored->php->system);
        self::assertSame(8, $restored->php->integer);
        self::assertSame(['akismet/akismet.php'], $restored->plugins);
        self::assertSame('flavor', $restored->template);
        self::assertSame('flavor-child', $restored->stylesheet);
    }

    #[Test]
    public function fromArrayWithMinimalData(): void
    {
        $metadata = PackageMetadata::fromArray([
            'SiteURL' => 'https://example.com',
            'HomeURL' => 'https://example.com',
        ]);

        self::assertSame('https://example.com', $metadata->siteUrl);
        self::assertSame('https://example.com', $metadata->homeUrl);
        self::assertNull($metadata->plugin);
        self::assertNull($metadata->wordPress);
        self::assertNull($metadata->database);
        self::assertNull($metadata->encrypted);
    }

    #[Test]
    public function nullFieldsOmittedFromJson(): void
    {
        $metadata = new PackageMetadata(
            siteUrl: 'https://example.com',
            homeUrl: 'https://example.com',
        );

        $json = json_encode($metadata, \JSON_THROW_ON_ERROR);
        $data = json_decode($json, true);

        self::assertArrayHasKey('SiteURL', $data);
        self::assertArrayHasKey('HomeURL', $data);
        self::assertArrayNotHasKey('Plugin', $data);
        self::assertArrayNotHasKey('WordPress', $data);
        self::assertArrayNotHasKey('Encrypted', $data);
        self::assertArrayNotHasKey('Compression', $data);
    }

    #[Test]
    public function encryptionFieldsRoundTrip(): void
    {
        $metadata = new PackageMetadata(
            siteUrl: 'https://example.com',
            homeUrl: 'https://example.com',
            encrypted: true,
            encryptedSignature: 'base64signature==',
            compression: new CompressionInfo(enabled: true, type: 'gzip'),
        );

        $json = json_encode($metadata);
        $restored = PackageMetadata::fromJson($json);

        self::assertTrue($restored->encrypted);
        self::assertSame('base64signature==', $restored->encryptedSignature);
        self::assertTrue($restored->compression->enabled);
        self::assertSame('gzip', $restored->compression->type);
    }

    #[Test]
    public function replaceInfoRoundTrip(): void
    {
        $metadata = new PackageMetadata(
            siteUrl: 'https://old.com',
            homeUrl: 'https://old.com',
            replace: new ReplaceInfo(
                oldValues: ['old-value-1', 'old-value-2'],
                newValues: ['new-value-1', 'new-value-2'],
            ),
        );

        $json = json_encode($metadata);
        $restored = PackageMetadata::fromJson($json);

        self::assertSame(['old-value-1', 'old-value-2'], $restored->replace->oldValues);
        self::assertSame(['new-value-1', 'new-value-2'], $restored->replace->newValues);
    }

    #[Test]
    public function serverInfoRoundTrip(): void
    {
        $metadata = new PackageMetadata(
            siteUrl: 'https://example.com',
            homeUrl: 'https://example.com',
            server: new ServerInfo(
                htaccess: base64_encode('RewriteEngine On'),
                webConfig: null,
            ),
        );

        $json = json_encode($metadata);
        $restored = PackageMetadata::fromJson($json);

        self::assertSame(base64_encode('RewriteEngine On'), $restored->server->htaccess);
        self::assertNull($restored->server->webConfig);
    }

    #[Test]
    public function booleanFlagsRoundTrip(): void
    {
        $metadata = new PackageMetadata(
            siteUrl: 'https://example.com',
            homeUrl: 'https://example.com',
            noSpamComments: true,
            noPostRevisions: true,
            noMedia: false,
            noDatabase: false,
        );

        $json = json_encode($metadata);
        $restored = PackageMetadata::fromJson($json);

        self::assertTrue($restored->noSpamComments);
        self::assertTrue($restored->noPostRevisions);
        self::assertFalse($restored->noMedia);
        self::assertFalse($restored->noDatabase);
    }

    #[Test]
    public function wordPressInfoSubObjectRoundTrip(): void
    {
        $wp = new WordPressInfo(
            version: '6.4.2',
            absolute: '/var/www/html/',
            content: '/var/www/html/wp-content',
            themes: ['/var/www/html/wp-content/themes', '/another/path'],
        );

        $json = json_encode($wp);
        $data = json_decode($json, true);

        self::assertSame('6.4.2', $data['Version']);
        self::assertSame('/var/www/html/', $data['Absolute']);
        self::assertCount(2, $data['Themes']);
    }

    #[Test]
    public function databaseInfoSubObjectRoundTrip(): void
    {
        $db = new DatabaseInfo(
            version: '8.0.35',
            charset: 'utf8mb4',
            collate: 'utf8mb4_unicode_ci',
            prefix: 'wp_',
            excludedTables: ['wp_ai1wm_tmp'],
            includedTables: [],
        );

        $json = json_encode($db);
        $restored = DatabaseInfo::fromArray(json_decode($json, true));

        self::assertSame('8.0.35', $restored->version);
        self::assertSame(['wp_ai1wm_tmp'], $restored->excludedTables);
    }

    #[Test]
    public function fromJsonWithInvalidJsonThrows(): void
    {
        $this->expectException(\JsonException::class);

        PackageMetadata::fromJson('not valid json');
    }

    #[Test]
    public function fromArrayWithEmptyArrayCreatesDefaults(): void
    {
        $metadata = PackageMetadata::fromArray([]);

        self::assertSame('', $metadata->siteUrl);
        self::assertSame('', $metadata->homeUrl);
    }
}
