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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Wpress\Metadata\MultisiteMetadata;
use WPPack\Component\Wpress\Metadata\SiteInfo;
use WPPack\Component\Wpress\Metadata\SiteWordPressInfo;

final class MultisiteMetadataTest extends TestCase
{
    #[Test]
    public function jsonRoundTrip(): void
    {
        $metadata = new MultisiteMetadata(
            network: true,
            networks: [],
            sites: [
                new SiteInfo(
                    blogId: 1,
                    siteId: 1,
                    langId: 0,
                    siteUrl: 'https://example.com',
                    homeUrl: 'https://example.com',
                    domain: 'example.com',
                    path: '/',
                    plugins: ['plugin-a/plugin-a.php'],
                    template: 'flavor',
                    stylesheet: 'flavor-child',
                    wordPress: new SiteWordPressInfo(
                        uploads: '/var/www/html/wp-content/uploads',
                        uploadsUrl: 'https://example.com/wp-content/uploads',
                    ),
                ),
                new SiteInfo(
                    blogId: 2,
                    siteId: 1,
                    langId: 0,
                    siteUrl: 'https://example.com/blog',
                    homeUrl: 'https://example.com/blog',
                    domain: 'example.com',
                    path: '/blog/',
                    wordPress: new SiteWordPressInfo(
                        uploads: '/var/www/html/wp-content/uploads/sites/2',
                        uploadsUrl: 'https://example.com/wp-content/uploads/sites/2',
                    ),
                ),
            ],
            plugins: ['sitewide-plugin/sitewide-plugin.php'],
            admins: ['admin', 'superadmin'],
        );

        $json = json_encode($metadata, \JSON_PRETTY_PRINT);
        $restored = MultisiteMetadata::fromJson($json);

        self::assertTrue($restored->network);
        self::assertCount(2, $restored->sites);
        self::assertSame(1, $restored->sites[0]->blogId);
        self::assertSame('https://example.com', $restored->sites[0]->siteUrl);
        self::assertSame('flavor', $restored->sites[0]->template);
        self::assertSame('flavor-child', $restored->sites[0]->stylesheet);
        self::assertSame(['plugin-a/plugin-a.php'], $restored->sites[0]->plugins);
        self::assertSame('/var/www/html/wp-content/uploads', $restored->sites[0]->wordPress->uploads);

        self::assertSame(2, $restored->sites[1]->blogId);
        self::assertSame('/blog/', $restored->sites[1]->path);

        self::assertSame(['sitewide-plugin/sitewide-plugin.php'], $restored->plugins);
        self::assertSame(['admin', 'superadmin'], $restored->admins);
    }

    #[Test]
    public function fromArrayWithMinimalData(): void
    {
        $metadata = MultisiteMetadata::fromArray([
            'Network' => false,
        ]);

        self::assertFalse($metadata->network);
        self::assertSame([], $metadata->sites);
        self::assertSame([], $metadata->plugins);
        self::assertSame([], $metadata->admins);
    }

    #[Test]
    public function siteInfoNullFieldsOmittedFromJson(): void
    {
        $site = new SiteInfo(
            blogId: 1,
            siteUrl: 'https://example.com',
        );

        $json = json_encode($site, \JSON_THROW_ON_ERROR);
        $data = json_decode($json, true);

        self::assertArrayHasKey('BlogID', $data);
        self::assertArrayHasKey('SiteURL', $data);
        self::assertArrayNotHasKey('HomeURL', $data);
        self::assertArrayNotHasKey('Domain', $data);
        self::assertArrayNotHasKey('WordPress', $data);
    }

    #[Test]
    public function nestedSiteWordPressInfoRoundTrip(): void
    {
        $wpInfo = new SiteWordPressInfo(
            uploads: '/var/www/html/wp-content/uploads/sites/2',
            uploadsUrl: 'https://example.com/wp-content/uploads/sites/2',
        );

        $json = json_encode($wpInfo);
        $data = json_decode($json, true);

        self::assertSame('/var/www/html/wp-content/uploads/sites/2', $data['Uploads']);
        self::assertSame('https://example.com/wp-content/uploads/sites/2', $data['UploadsURL']);

        $restored = SiteWordPressInfo::fromArray($data);
        self::assertSame($wpInfo->uploads, $restored->uploads);
        self::assertSame($wpInfo->uploadsUrl, $restored->uploadsUrl);
    }

    #[Test]
    public function fromJsonWithInvalidJsonThrows(): void
    {
        $this->expectException(\JsonException::class);

        MultisiteMetadata::fromJson('{invalid');
    }

    #[Test]
    public function multipleSitesPreserveOrder(): void
    {
        $sites = [];

        for ($i = 1; $i <= 5; $i++) {
            $sites[] = new SiteInfo(blogId: $i, siteUrl: "https://site{$i}.example.com");
        }

        $metadata = new MultisiteMetadata(network: true, sites: $sites);

        $json = json_encode($metadata);
        $restored = MultisiteMetadata::fromJson($json);

        for ($i = 0; $i < 5; $i++) {
            self::assertSame($i + 1, $restored->sites[$i]->blogId);
        }
    }

    #[Test]
    public function allFieldsAlwaysPresentInJson(): void
    {
        $metadata = new MultisiteMetadata(network: false);

        $json = json_encode($metadata, \JSON_THROW_ON_ERROR);
        $data = json_decode($json, true);

        // These top-level fields should always be present
        self::assertArrayHasKey('Network', $data);
        self::assertArrayHasKey('Networks', $data);
        self::assertArrayHasKey('Sites', $data);
        self::assertArrayHasKey('Plugins', $data);
        self::assertArrayHasKey('Admins', $data);
    }
}
