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

namespace WpPack\MuPlugin\MultisiteUrlFixer\Tests\Subscriber;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\MuPlugin\MultisiteUrlFixer\Subscriber\UrlFixerSubscriber;

final class UrlFixerSubscriberTest extends TestCase
{
    private UrlFixerSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new UrlFixerSubscriber('/wp');
    }

    // --- fixAssetLoaderSrc ---

    #[Test]
    public function fixAssetLoaderSrcReturnsEmptySrcUnchanged(): void
    {
        self::assertSame('', $this->subscriber->fixAssetLoaderSrc(''));
    }

    #[Test]
    public function fixAssetLoaderSrcFixesWpIncludesUrl(): void
    {
        if (\function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        self::assertSame(
            'https://example.com/wp/wp-includes/css/dashicons.min.css?ver=6.5',
            $this->subscriber->fixAssetLoaderSrc('https://example.com/wp-includes/css/dashicons.min.css?ver=6.5'),
        );
    }

    #[Test]
    public function fixAssetLoaderSrcFixesWpAdminUrl(): void
    {
        if (\function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        self::assertSame(
            'https://example.com/wp/wp-admin/js/common.min.js?ver=6.5',
            $this->subscriber->fixAssetLoaderSrc('https://example.com/wp-admin/js/common.min.js?ver=6.5'),
        );
    }

    #[Test]
    public function fixAssetLoaderSrcLeavesAlreadyFixedUrlUnchanged(): void
    {
        self::assertSame(
            'https://example.com/wp/wp-includes/css/dashicons.min.css',
            $this->subscriber->fixAssetLoaderSrc('https://example.com/wp/wp-includes/css/dashicons.min.css'),
        );
    }

    #[Test]
    public function fixAssetLoaderSrcLeavesThemeAssetUnchanged(): void
    {
        self::assertSame(
            'https://example.com/wp-content/themes/my-theme/style.css',
            $this->subscriber->fixAssetLoaderSrc('https://example.com/wp-content/themes/my-theme/style.css'),
        );
    }

    // --- fixNetworkSiteUrl ---

    #[Test]
    public function fixNetworkSiteUrlReturnsEmptyUrlUnchanged(): void
    {
        self::assertSame('', $this->subscriber->fixNetworkSiteUrl(''));
    }

    #[Test]
    public function fixNetworkSiteUrlInsertsWpPath(): void
    {
        self::assertSame(
            'https://example.com/wp/wp-admin/network/',
            $this->subscriber->fixNetworkSiteUrl('https://example.com/wp-admin/network/'),
        );
    }

    #[Test]
    public function fixNetworkSiteUrlLeavesAlreadyFixedUrlUnchanged(): void
    {
        self::assertSame(
            'https://example.com/wp/wp-admin/network/',
            $this->subscriber->fixNetworkSiteUrl('https://example.com/wp/wp-admin/network/'),
        );
    }

    // --- fixHomeUrl ---

    #[Test]
    public function fixHomeUrlReturnsNonStringUnchanged(): void
    {
        self::assertSame(123, $this->subscriber->fixHomeUrl(123));
    }

    #[Test]
    public function fixHomeUrlReturnsEmptyStringUnchanged(): void
    {
        self::assertSame('', $this->subscriber->fixHomeUrl(''));
    }

    #[Test]
    public function fixHomeUrlRemovesTrailingWpPath(): void
    {
        self::assertSame(
            'https://example.com',
            $this->subscriber->fixHomeUrl('https://example.com/wp'),
        );
    }

    #[Test]
    public function fixHomeUrlLeavesUrlWithoutWpPathUnchanged(): void
    {
        self::assertSame(
            'https://example.com',
            $this->subscriber->fixHomeUrl('https://example.com'),
        );
    }

    // --- fixOptionSiteUrl ---

    #[Test]
    public function fixOptionSiteUrlReturnsNonStringUnchanged(): void
    {
        self::assertNull($this->subscriber->fixOptionSiteUrl(null));
    }

    #[Test]
    public function fixOptionSiteUrlReturnsEmptyStringUnchanged(): void
    {
        self::assertSame('', $this->subscriber->fixOptionSiteUrl(''));
    }

    #[Test]
    public function fixOptionSiteUrlAppendsWpPath(): void
    {
        // is_main_site() returns true in non-multisite environments
        self::assertSame(
            'https://example.com/wp',
            $this->subscriber->fixOptionSiteUrl('https://example.com'),
        );
    }

    #[Test]
    public function fixOptionSiteUrlLeavesExistingWpPathUnchanged(): void
    {
        self::assertSame(
            'https://example.com/wp',
            $this->subscriber->fixOptionSiteUrl('https://example.com/wp'),
        );
    }

    #[Test]
    public function fixOptionSiteUrlStripsTrailingSlash(): void
    {
        self::assertSame(
            'https://example.com/wp',
            $this->subscriber->fixOptionSiteUrl('https://example.com/'),
        );
    }

    // --- fixIncludesUrl ---

    #[Test]
    public function fixIncludesUrlReturnsEmptyUrlUnchanged(): void
    {
        self::assertSame('', $this->subscriber->fixIncludesUrl(''));
    }

    #[Test]
    public function fixIncludesUrlFixesWpIncludesUrl(): void
    {
        if (\function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        self::assertSame(
            'https://example.com/wp/wp-includes/js/jquery/jquery.min.js',
            $this->subscriber->fixIncludesUrl('https://example.com/wp-includes/js/jquery/jquery.min.js'),
        );
    }

    #[Test]
    public function fixIncludesUrlLeavesAlreadyFixedUrlUnchanged(): void
    {
        self::assertSame(
            'https://example.com/wp/wp-includes/js/jquery/jquery.min.js',
            $this->subscriber->fixIncludesUrl('https://example.com/wp/wp-includes/js/jquery/jquery.min.js'),
        );
    }

    // --- fixAdminUrl ---

    #[Test]
    public function fixAdminUrlReturnsEmptyUrlUnchanged(): void
    {
        self::assertSame('', $this->subscriber->fixAdminUrl(''));
    }

    #[Test]
    public function fixAdminUrlFixesStaticFileUrl(): void
    {
        if (\function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        self::assertSame(
            'https://example.com/wp/wp-admin/images/wordpress-logo.svg',
            $this->subscriber->fixAdminUrl('https://example.com/wp-admin/images/wordpress-logo.svg'),
        );
    }

    #[Test]
    public function fixAdminUrlLeavesNonStaticFileUrlUnchanged(): void
    {
        self::assertSame(
            'https://example.com/wp-admin/edit.php',
            $this->subscriber->fixAdminUrl('https://example.com/wp-admin/edit.php'),
        );
    }

    #[Test]
    public function fixAdminUrlLeavesAdminPageUrlUnchanged(): void
    {
        self::assertSame(
            'https://example.com/wp-admin/options-general.php?page=my-plugin',
            $this->subscriber->fixAdminUrl('https://example.com/wp-admin/options-general.php?page=my-plugin'),
        );
    }

    #[Test]
    public function fixAdminUrlFixesCssFileUrl(): void
    {
        if (\function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        self::assertSame(
            'https://example.com/wp/wp-admin/css/forms.min.css?ver=6.5',
            $this->subscriber->fixAdminUrl('https://example.com/wp-admin/css/forms.min.css?ver=6.5'),
        );
    }

    #[Test]
    public function fixAdminUrlFixesJsFileUrl(): void
    {
        if (\function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        self::assertSame(
            'https://example.com/wp/wp-admin/js/common.min.js',
            $this->subscriber->fixAdminUrl('https://example.com/wp-admin/js/common.min.js'),
        );
    }

    #[Test]
    public function fixAdminUrlFixesFontFileUrl(): void
    {
        if (\function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        self::assertSame(
            'https://example.com/wp/wp-admin/fonts/dashicons.woff2',
            $this->subscriber->fixAdminUrl('https://example.com/wp-admin/fonts/dashicons.woff2'),
        );
    }
}
