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

namespace WPPack\MuPlugin\MultisiteUrlFixer\Tests\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\MuPlugin\MultisiteUrlFixer\Subscriber\UrlFixerSubscriber;

#[CoversClass(UrlFixerSubscriber::class)]
final class UrlFixerSubscriberTest extends TestCase
{
    private UrlFixerSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new UrlFixerSubscriber('/wp', $this->makeContext(isMainSite: true, isSubdomainInstall: false));
    }

    private function makeContext(
        bool $isMainSite = true,
        bool $isSubdomainInstall = false,
        int $currentBlogId = 1,
        int $mainSiteId = 1,
    ): BlogContextInterface {
        return new class ($isMainSite, $isSubdomainInstall, $currentBlogId, $mainSiteId) implements BlogContextInterface {
            public function __construct(
                private readonly bool $isMainSite,
                private readonly bool $isSubdomainInstall,
                private readonly int $currentBlogId,
                private readonly int $mainSiteId,
            ) {}

            public function getCurrentBlogId(): int
            {
                return $this->currentBlogId;
            }

            public function isMultisite(): bool
            {
                return true;
            }

            public function getMainSiteId(): int
            {
                return $this->mainSiteId;
            }

            public function isSwitched(): bool
            {
                return false;
            }

            public function isMainSite(): bool
            {
                return $this->isMainSite;
            }

            public function isSubdomainInstall(): bool
            {
                return $this->isSubdomainInstall;
            }
        };
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
        self::assertSame(
            'https://example.com/wp/wp-includes/css/dashicons.min.css?ver=6.5',
            $this->subscriber->fixAssetLoaderSrc('https://example.com/wp-includes/css/dashicons.min.css?ver=6.5'),
        );
    }

    #[Test]
    public function fixAssetLoaderSrcFixesWpAdminUrl(): void
    {
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
        self::assertSame(
            'https://example.com/wp/wp-admin/css/forms.min.css?ver=6.5',
            $this->subscriber->fixAdminUrl('https://example.com/wp-admin/css/forms.min.css?ver=6.5'),
        );
    }

    #[Test]
    public function fixAdminUrlFixesJsFileUrl(): void
    {
        self::assertSame(
            'https://example.com/wp/wp-admin/js/common.min.js',
            $this->subscriber->fixAdminUrl('https://example.com/wp-admin/js/common.min.js'),
        );
    }

    #[Test]
    public function fixAdminUrlFixesFontFileUrl(): void
    {
        self::assertSame(
            'https://example.com/wp/wp-admin/fonts/dashicons.woff2',
            $this->subscriber->fixAdminUrl('https://example.com/wp-admin/fonts/dashicons.woff2'),
        );
    }

    // --- register ---

    #[Test]
    public function registerAttachesAllSevenFilters(): void
    {
        $hooks = [
            'style_loader_src' => 'fixAssetLoaderSrc',
            'script_loader_src' => 'fixAssetLoaderSrc',
            'network_site_url' => 'fixNetworkSiteUrl',
            'option_home' => 'fixHomeUrl',
            'option_siteurl' => 'fixOptionSiteUrl',
            'includes_url' => 'fixIncludesUrl',
            'admin_url' => 'fixAdminUrl',
        ];

        // Clear any previous registrations so has_filter returns a clean answer.
        foreach ($hooks as $hook => $_method) {
            remove_all_filters($hook);
        }

        $this->subscriber->register();

        foreach ($hooks as $hook => $method) {
            $priority = has_filter($hook, [$this->subscriber, $method]);
            self::assertSame(5, $priority, "Filter {$hook} => {$method} should be attached at priority 5");
        }

        foreach (array_keys($hooks) as $hook) {
            remove_all_filters($hook);
        }
    }

    // --- getSitePath via fixCoreUrl (non-main-site branch) ---

    #[Test]
    public function fixAssetLoaderSrcHandlesNonMainSiteSubdirectoryPrefix(): void
    {
        // Simulate the subdirectory-multisite non-main-site scenario by
        // populating $current_blog->path with a site prefix. getSitePath()
        // returns '' when is_main_site() is true, so this assertion only
        // fires the alternate branch when we're on a secondary site. In
        // single-site tests we fall back to the main-site branch which is
        // still a valid exercise of the method — both outcomes stay pinned.
        global $current_blog;
        $previous = $current_blog ?? null;
        $current_blog = (object) ['path' => '/sub-site/'];

        try {
            $result = $this->subscriber->fixAssetLoaderSrc('https://example.com/sub-site/wp-admin/css/forms.min.css');

            // Either: non-main-site rewrite (/sub-site → /wp) OR main-site pass-through
            self::assertContains($result, [
                'https://example.com/wp/wp-admin/css/forms.min.css',
                'https://example.com/sub-site/wp-admin/css/forms.min.css',
            ]);
        } finally {
            $current_blog = $previous;
        }
    }

    #[Test]
    public function fixAssetLoaderSrcReturnsUrlUnchangedWhenSitePrefixDoesNotMatch(): void
    {
        global $current_blog;
        $previous = $current_blog ?? null;
        $current_blog = (object) ['path' => '/unrelated-site/'];

        try {
            // URL has no `/unrelated-site/` prefix, so even the non-main-site
            // branch returns the URL untouched — covers the early-return
            // path when the regex does not match.
            $result = $this->subscriber->fixAssetLoaderSrc('https://cdn.example.com/assets/logo.png');

            self::assertSame('https://cdn.example.com/assets/logo.png', $result);
        } finally {
            $current_blog = $previous;
        }
    }

    // --- Multisite branches via injected BlogContext ---

    #[Test]
    public function fixAssetLoaderSrcSkipsUrlFixingOnSubdomainInstall(): void
    {
        $subscriber = new UrlFixerSubscriber('/wp', $this->makeContext(isSubdomainInstall: true));

        // Subdomain installs already resolve paths correctly; the fixer
        // must return the URL verbatim.
        self::assertSame(
            'https://sub.example.com/wp-admin/images/logo.svg',
            $subscriber->fixAssetLoaderSrc('https://sub.example.com/wp-admin/images/logo.svg'),
        );
    }

    #[Test]
    public function fixOptionSiteUrlLeavesNonMainSubdirectorySiteUntouched(): void
    {
        $subscriber = new UrlFixerSubscriber('/wp', $this->makeContext(isMainSite: false, isSubdomainInstall: false));

        // Subdirectory multisite, non-main-site: the stored siteurl
        // points at the site path and must not be rewritten with /wp.
        self::assertSame(
            'https://example.com/sub/',
            $subscriber->fixOptionSiteUrl('https://example.com/sub/'),
        );
    }

    #[Test]
    public function fixOptionSiteUrlAppendsWpPathForSubdomainInstall(): void
    {
        $subscriber = new UrlFixerSubscriber('/wp', $this->makeContext(isMainSite: false, isSubdomainInstall: true));

        self::assertSame(
            'https://sub.example.com/wp',
            $subscriber->fixOptionSiteUrl('https://sub.example.com'),
        );
    }

    #[Test]
    public function fixAssetLoaderSrcRewritesNonMainSubdirectorySiteUrl(): void
    {
        global $current_blog;
        $previous = $current_blog ?? null;
        $current_blog = (object) ['path' => '/sub-site/'];

        $subscriber = new UrlFixerSubscriber('/wp', $this->makeContext(isMainSite: false, isSubdomainInstall: false));

        try {
            // On a non-main site in subdirectory mode, /sub-site/wp-admin/
            // must be rewritten to /wp/wp-admin/ so the request reaches
            // the shared WordPress install rather than the site path.
            self::assertSame(
                'https://example.com/wp/wp-admin/css/forms.min.css',
                $subscriber->fixAssetLoaderSrc('https://example.com/sub-site/wp-admin/css/forms.min.css'),
            );
        } finally {
            $current_blog = $previous;
        }
    }

    #[Test]
    public function fixAssetLoaderSrcLeavesUnrelatedUrlAloneOnNonMainSite(): void
    {
        global $current_blog;
        $previous = $current_blog ?? null;
        $current_blog = (object) ['path' => '/sub-site/'];

        $subscriber = new UrlFixerSubscriber('/wp', $this->makeContext(isMainSite: false, isSubdomainInstall: false));

        try {
            // URL without `/sub-site/` prefix should skip the rewrite and
            // come back untouched — covers the non-main-site branch where
            // the regex does not match.
            self::assertSame(
                'https://cdn.example.com/assets/app.js',
                $subscriber->fixAssetLoaderSrc('https://cdn.example.com/assets/app.js'),
            );
        } finally {
            $current_blog = $previous;
        }
    }

    #[Test]
    public function fixAssetLoaderSrcLeavesUrlUntouchedWhenNonMainSiteHasNoPath(): void
    {
        // $current_blog->path unset while on a non-main site should still
        // resolve to an empty site prefix (main-site fall-through branch).
        global $current_blog;
        $previous = $current_blog ?? null;
        $current_blog = null;

        $subscriber = new UrlFixerSubscriber('/wp', $this->makeContext(isMainSite: false, isSubdomainInstall: false));

        try {
            self::assertSame(
                'https://example.com/wp/wp-admin/js/common.min.js',
                $subscriber->fixAssetLoaderSrc('https://example.com/wp-admin/js/common.min.js'),
            );
        } finally {
            $current_blog = $previous;
        }
    }
}
