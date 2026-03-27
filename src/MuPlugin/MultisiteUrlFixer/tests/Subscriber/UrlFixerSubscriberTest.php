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
use WpPack\Component\EventDispatcher\WordPressEvent;
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
    public function fixAssetLoaderSrcReturnsFalseValueUnchanged(): void
    {
        $event = new WordPressEvent('style_loader_src', [false, 'jquery']);
        $this->subscriber->fixAssetLoaderSrc($event);

        self::assertFalse($event->filterValue);
    }

    #[Test]
    public function fixAssetLoaderSrcReturnsEmptySrcUnchanged(): void
    {
        $event = new WordPressEvent('style_loader_src', ['', 'jquery']);
        $this->subscriber->fixAssetLoaderSrc($event);

        self::assertSame('', $event->filterValue);
    }

    #[Test]
    public function fixAssetLoaderSrcFixesWpIncludesUrl(): void
    {
        if (function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        $event = new WordPressEvent('style_loader_src', [
            'https://example.com/wp-includes/css/dashicons.min.css?ver=6.5',
            'dashicons',
        ]);
        $this->subscriber->fixAssetLoaderSrc($event);

        self::assertSame(
            'https://example.com/wp/wp-includes/css/dashicons.min.css?ver=6.5',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixAssetLoaderSrcFixesWpAdminUrl(): void
    {
        if (function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        $event = new WordPressEvent('script_loader_src', [
            'https://example.com/wp-admin/js/common.min.js?ver=6.5',
            'common',
        ]);
        $this->subscriber->fixAssetLoaderSrc($event);

        self::assertSame(
            'https://example.com/wp/wp-admin/js/common.min.js?ver=6.5',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixAssetLoaderSrcLeavesAlreadyFixedUrlUnchanged(): void
    {
        $event = new WordPressEvent('style_loader_src', [
            'https://example.com/wp/wp-includes/css/dashicons.min.css',
            'dashicons',
        ]);
        $this->subscriber->fixAssetLoaderSrc($event);

        self::assertSame(
            'https://example.com/wp/wp-includes/css/dashicons.min.css',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixAssetLoaderSrcLeavesThemeAssetUnchanged(): void
    {
        $event = new WordPressEvent('style_loader_src', [
            'https://example.com/wp-content/themes/my-theme/style.css',
            'theme-style',
        ]);
        $this->subscriber->fixAssetLoaderSrc($event);

        self::assertSame(
            'https://example.com/wp-content/themes/my-theme/style.css',
            $event->filterValue,
        );
    }

    // --- fixNetworkSiteUrl ---

    #[Test]
    public function fixNetworkSiteUrlReturnsEmptyUrlUnchanged(): void
    {
        $event = new WordPressEvent('network_site_url', ['', '', null]);
        $this->subscriber->fixNetworkSiteUrl($event);

        self::assertSame('', $event->filterValue);
    }

    #[Test]
    public function fixNetworkSiteUrlInsertsWpPath(): void
    {
        $event = new WordPressEvent('network_site_url', [
            'https://example.com/wp-admin/network/',
            '/wp-admin/network/',
            null,
        ]);
        $this->subscriber->fixNetworkSiteUrl($event);

        self::assertSame(
            'https://example.com/wp/wp-admin/network/',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixNetworkSiteUrlLeavesAlreadyFixedUrlUnchanged(): void
    {
        $event = new WordPressEvent('network_site_url', [
            'https://example.com/wp/wp-admin/network/',
            '/wp-admin/network/',
            null,
        ]);
        $this->subscriber->fixNetworkSiteUrl($event);

        self::assertSame(
            'https://example.com/wp/wp-admin/network/',
            $event->filterValue,
        );
    }

    // --- fixHomeUrl ---

    #[Test]
    public function fixHomeUrlReturnsNonStringUnchanged(): void
    {
        $event = new WordPressEvent('option_home', [123, 'home']);
        $this->subscriber->fixHomeUrl($event);

        self::assertSame(123, $event->filterValue);
    }

    #[Test]
    public function fixHomeUrlReturnsEmptyStringUnchanged(): void
    {
        $event = new WordPressEvent('option_home', ['', 'home']);
        $this->subscriber->fixHomeUrl($event);

        self::assertSame('', $event->filterValue);
    }

    #[Test]
    public function fixHomeUrlRemovesTrailingWpPath(): void
    {
        $event = new WordPressEvent('option_home', ['https://example.com/wp', 'home']);
        $this->subscriber->fixHomeUrl($event);

        self::assertSame('https://example.com', $event->filterValue);
    }

    #[Test]
    public function fixHomeUrlLeavesUrlWithoutWpPathUnchanged(): void
    {
        $event = new WordPressEvent('option_home', ['https://example.com', 'home']);
        $this->subscriber->fixHomeUrl($event);

        self::assertSame('https://example.com', $event->filterValue);
    }

    // --- fixOptionSiteUrl ---

    #[Test]
    public function fixOptionSiteUrlReturnsNonStringUnchanged(): void
    {
        $event = new WordPressEvent('option_siteurl', [null, 'siteurl']);
        $this->subscriber->fixOptionSiteUrl($event);

        self::assertNull($event->filterValue);
    }

    #[Test]
    public function fixOptionSiteUrlReturnsEmptyStringUnchanged(): void
    {
        $event = new WordPressEvent('option_siteurl', ['', 'siteurl']);
        $this->subscriber->fixOptionSiteUrl($event);

        self::assertSame('', $event->filterValue);
    }

    #[Test]
    public function fixOptionSiteUrlAppendsWpPath(): void
    {
        // is_main_site() returns true in non-multisite environments
        $event = new WordPressEvent('option_siteurl', ['https://example.com', 'siteurl']);
        $this->subscriber->fixOptionSiteUrl($event);

        self::assertSame('https://example.com/wp', $event->filterValue);
    }

    #[Test]
    public function fixOptionSiteUrlLeavesExistingWpPathUnchanged(): void
    {
        $event = new WordPressEvent('option_siteurl', ['https://example.com/wp', 'siteurl']);
        $this->subscriber->fixOptionSiteUrl($event);

        self::assertSame('https://example.com/wp', $event->filterValue);
    }

    #[Test]
    public function fixOptionSiteUrlStripsTrailingSlash(): void
    {
        $event = new WordPressEvent('option_siteurl', ['https://example.com/', 'siteurl']);
        $this->subscriber->fixOptionSiteUrl($event);

        self::assertSame('https://example.com/wp', $event->filterValue);
    }

    // --- fixIncludesUrl ---

    #[Test]
    public function fixIncludesUrlReturnsEmptyUrlUnchanged(): void
    {
        $event = new WordPressEvent('includes_url', ['', '', null]);
        $this->subscriber->fixIncludesUrl($event);

        self::assertSame('', $event->filterValue);
    }

    #[Test]
    public function fixIncludesUrlFixesWpIncludesUrl(): void
    {
        if (function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        $event = new WordPressEvent('includes_url', [
            'https://example.com/wp-includes/js/jquery/jquery.min.js',
            'js/jquery/jquery.min.js',
            null,
        ]);
        $this->subscriber->fixIncludesUrl($event);

        self::assertSame(
            'https://example.com/wp/wp-includes/js/jquery/jquery.min.js',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixIncludesUrlLeavesAlreadyFixedUrlUnchanged(): void
    {
        $event = new WordPressEvent('includes_url', [
            'https://example.com/wp/wp-includes/js/jquery/jquery.min.js',
            'js/jquery/jquery.min.js',
            null,
        ]);
        $this->subscriber->fixIncludesUrl($event);

        self::assertSame(
            'https://example.com/wp/wp-includes/js/jquery/jquery.min.js',
            $event->filterValue,
        );
    }

    // --- fixAdminUrl ---

    #[Test]
    public function fixAdminUrlReturnsEmptyUrlUnchanged(): void
    {
        $event = new WordPressEvent('admin_url', ['', '', null]);
        $this->subscriber->fixAdminUrl($event);

        self::assertSame('', $event->filterValue);
    }

    #[Test]
    public function fixAdminUrlFixesStaticFileUrl(): void
    {
        if (function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        $event = new WordPressEvent('admin_url', [
            'https://example.com/wp-admin/images/wordpress-logo.svg',
            'images/wordpress-logo.svg',
            null,
        ]);
        $this->subscriber->fixAdminUrl($event);

        self::assertSame(
            'https://example.com/wp/wp-admin/images/wordpress-logo.svg',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixAdminUrlLeavesNonStaticFileUrlUnchanged(): void
    {
        $event = new WordPressEvent('admin_url', [
            'https://example.com/wp-admin/edit.php',
            'edit.php',
            null,
        ]);
        $this->subscriber->fixAdminUrl($event);

        self::assertSame(
            'https://example.com/wp-admin/edit.php',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixAdminUrlLeavesAdminPageUrlUnchanged(): void
    {
        $event = new WordPressEvent('admin_url', [
            'https://example.com/wp-admin/options-general.php?page=my-plugin',
            'options-general.php?page=my-plugin',
            null,
        ]);
        $this->subscriber->fixAdminUrl($event);

        self::assertSame(
            'https://example.com/wp-admin/options-general.php?page=my-plugin',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixAdminUrlFixesCssFileUrl(): void
    {
        if (function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        $event = new WordPressEvent('admin_url', [
            'https://example.com/wp-admin/css/forms.min.css?ver=6.5',
            'css/forms.min.css',
            null,
        ]);
        $this->subscriber->fixAdminUrl($event);

        self::assertSame(
            'https://example.com/wp/wp-admin/css/forms.min.css?ver=6.5',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixAdminUrlFixesJsFileUrl(): void
    {
        if (function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        $event = new WordPressEvent('admin_url', [
            'https://example.com/wp-admin/js/common.min.js',
            'js/common.min.js',
            null,
        ]);
        $this->subscriber->fixAdminUrl($event);

        self::assertSame(
            'https://example.com/wp/wp-admin/js/common.min.js',
            $event->filterValue,
        );
    }

    #[Test]
    public function fixAdminUrlFixesFontFileUrl(): void
    {
        if (function_exists('is_subdomain_install') && is_subdomain_install()) {
            self::markTestSkipped('Subdomain install does not apply URL fixing.');
        }

        $event = new WordPressEvent('admin_url', [
            'https://example.com/wp-admin/fonts/dashicons.woff2',
            'fonts/dashicons.woff2',
            null,
        ]);
        $this->subscriber->fixAdminUrl($event);

        self::assertSame(
            'https://example.com/wp/wp-admin/fonts/dashicons.woff2',
            $event->filterValue,
        );
    }
}
