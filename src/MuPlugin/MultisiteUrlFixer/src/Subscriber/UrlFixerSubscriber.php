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

namespace WPPack\MuPlugin\MultisiteUrlFixer\Subscriber;

use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;

/**
 * Fix asset and content URLs for WordPress Multisite on Bedrock structure.
 *
 * Registers filters directly via add_filter() so they are active
 * immediately — before Kernel boots at init.
 */
final readonly class UrlFixerSubscriber
{
    public function __construct(
        private string $wpPath,
        private BlogContextInterface $blogContext = new BlogContext(),
    ) {}

    public function register(): void
    {
        add_filter('style_loader_src', [$this, 'fixAssetLoaderSrc'], 5);
        add_filter('script_loader_src', [$this, 'fixAssetLoaderSrc'], 5);
        add_filter('network_site_url', [$this, 'fixNetworkSiteUrl'], 5);
        add_filter('option_home', [$this, 'fixHomeUrl'], 5);
        add_filter('option_siteurl', [$this, 'fixOptionSiteUrl'], 5);
        add_filter('includes_url', [$this, 'fixIncludesUrl'], 5);
        add_filter('admin_url', [$this, 'fixAdminUrl'], 5);
    }

    public function fixAssetLoaderSrc(string $url): string
    {
        return $this->fixCoreUrl($url, ['wp-admin', 'wp-includes']);
    }

    public function fixNetworkSiteUrl(string $url): string
    {
        if ($url === '') {
            return $url;
        }

        if (str_contains($url, $this->wpPath . '/')) {
            return $url;
        }

        $fixed = preg_replace('#^([^:]+://[^/]+)/#', '$1' . $this->wpPath . '/', $url, 1);

        return \is_string($fixed) ? $fixed : $url;
    }

    public function fixHomeUrl(mixed $value): mixed
    {
        if (!\is_string($value) || $value === '') {
            return $value;
        }

        if (str_ends_with($value, $this->wpPath)) {
            return substr($value, 0, -\strlen($this->wpPath));
        }

        return $value;
    }

    public function fixOptionSiteUrl(mixed $value): mixed
    {
        if (!\is_string($value) || $value === '') {
            return $value;
        }

        if (!$this->blogContext->isMainSite() && !$this->blogContext->isSubdomainInstall()) {
            return $value;
        }

        $value = rtrim($value, '/');

        if (!str_ends_with($value, $this->wpPath)) {
            $value .= $this->wpPath;
        }

        return $value;
    }

    public function fixIncludesUrl(string $url): string
    {
        return $this->fixCoreUrl($url, ['wp-includes']);
    }

    public function fixAdminUrl(string $url): string
    {
        if (!$this->isStaticFile($url)) {
            return $url;
        }

        return $this->fixCoreUrl($url, ['wp-admin']);
    }

    private function getSitePath(): string
    {
        global $current_blog;

        if (!isset($current_blog->path) || $this->blogContext->isMainSite()) {
            return '';
        }

        $path = trim($current_blog->path, '/');

        return $path !== '' ? '/' . $path : '';
    }

    /**
     * Core URL fixing logic for subdirectory multisite.
     *
     * @param string[] $wpDirs WordPress directories to fix (e.g. ['wp-admin', 'wp-includes'])
     */
    private function fixCoreUrl(string $url, array $wpDirs): string
    {
        if ($url === '') {
            return $url;
        }

        if ($this->blogContext->isSubdomainInstall()) {
            return $url;
        }

        $sitePrefix = $this->getSitePath();
        $dirsPattern = implode('|', array_map(fn(string $dir): string => preg_quote($dir, '#'), $wpDirs));

        if ($sitePrefix === '') {
            // Main site: fix URLs without /wp prefix
            if (preg_match('#/wp/(' . $dirsPattern . ')/#', $url)) {
                return $url;
            }

            $pattern = '#(^|://[^/]+)/(' . $dirsPattern . ')/#';

            if (!preg_match($pattern, $url)) {
                return $url;
            }

            $fixed = preg_replace($pattern, '$1' . $this->wpPath . '/$2/', $url, 1);
        } else {
            // Non-main sites: fix /sitename/wp-admin/ → /wp/wp-admin/
            $pattern = '#' . preg_quote($sitePrefix, '#') . '/(' . $dirsPattern . ')/#';

            if (!preg_match($pattern, $url)) {
                return $url;
            }

            $fixed = preg_replace($pattern, $this->wpPath . '/$1/', $url, 1);
        }

        return \is_string($fixed) ? $fixed : $url;
    }

    private function isStaticFile(string $url): bool
    {
        $urlPath = strtok($url, '?');
        if ($urlPath === false) {
            $urlPath = $url;
        }

        $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

        return \in_array($extension, [
            'gif', 'png', 'jpg', 'jpeg', 'svg', 'ico', 'webp', 'avif',
            'css', 'js', 'map', 'json', 'scss',
            'woff', 'woff2', 'ttf', 'eot', 'otf',
            'txt', 'md',
        ], true);
    }
}
