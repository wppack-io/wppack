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

namespace WpPack\MuPlugin\MultisiteUrlFixer\Subscriber;

use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\EventDispatcher\WordPressEvent;

final readonly class UrlFixerSubscriber
{
    public function __construct(
        private string $wpPath,
    ) {}

    /**
     * Fix CSS/JS asset URLs for subdirectory multisite.
     */
    #[AsEventListener(event: 'style_loader_src', priority: 5)]
    #[AsEventListener(event: 'script_loader_src', priority: 5)]
    public function fixAssetLoaderSrc(WordPressEvent $event): void
    {
        if (!\is_string($event->filterValue) || $event->filterValue === '') {
            return;
        }

        $event->filterValue = $this->fixCoreUrl($event->filterValue, ['wp-admin', 'wp-includes']);
    }

    /**
     * Fix network_site_url — ensure /wp is included for network admin.
     */
    #[AsEventListener(event: 'network_site_url', priority: 5)]
    public function fixNetworkSiteUrl(WordPressEvent $event): void
    {
        if (!\is_string($event->filterValue) || $event->filterValue === '') {
            return;
        }

        $url = $event->filterValue;

        if (str_contains($url, $this->wpPath . '/')) {
            return;
        }

        $fixed = preg_replace('#^([^:]+://[^/]+)/#', '$1' . $this->wpPath . '/', $url, 1);

        $event->filterValue = \is_string($fixed) ? $fixed : $url;
    }

    /**
     * Fix option_home — remove trailing /wp.
     */
    #[AsEventListener(event: 'option_home', priority: 5)]
    public function fixHomeUrl(WordPressEvent $event): void
    {
        $value = $event->filterValue;

        if (!\is_string($value) || $value === '') {
            return;
        }

        if (str_ends_with($value, $this->wpPath)) {
            $event->filterValue = substr($value, 0, -\strlen($this->wpPath));
        }
    }

    /**
     * Fix option_siteurl — ensure /wp is at the end.
     */
    #[AsEventListener(event: 'option_siteurl', priority: 5)]
    public function fixOptionSiteUrl(WordPressEvent $event): void
    {
        $value = $event->filterValue;

        if (!\is_string($value) || $value === '') {
            return;
        }

        if (!is_main_site() && !(function_exists('is_subdomain_install') && is_subdomain_install())) {
            return;
        }

        $value = rtrim($value, '/');

        if (!str_ends_with($value, $this->wpPath)) {
            $value .= $this->wpPath;
        }

        $event->filterValue = $value;
    }

    /**
     * Fix includes_url for subdirectory multisite.
     */
    #[AsEventListener(event: 'includes_url', priority: 5)]
    public function fixIncludesUrl(WordPressEvent $event): void
    {
        if (!\is_string($event->filterValue) || $event->filterValue === '') {
            return;
        }

        $event->filterValue = $this->fixCoreUrl($event->filterValue, ['wp-includes']);
    }

    /**
     * Fix admin_url for subdirectory multisite (static files only).
     */
    #[AsEventListener(event: 'admin_url', priority: 5)]
    public function fixAdminUrl(WordPressEvent $event): void
    {
        if (!\is_string($event->filterValue) || $event->filterValue === '') {
            return;
        }

        if (!$this->isStaticFile($event->filterValue)) {
            return;
        }

        $event->filterValue = $this->fixCoreUrl($event->filterValue, ['wp-admin']);
    }

    private function getSitePath(): string
    {
        global $current_blog;

        if (!isset($current_blog->path) || is_main_site()) {
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
        if (function_exists('is_subdomain_install') && is_subdomain_install()) {
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
