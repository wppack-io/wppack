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

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'feed', priority: 65)]
final class FeedDataCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'feed';
    }

    public function getLabel(): string
    {
        return 'Feed';
    }

    public function collect(): void
    {
        global $wp_rewrite;

        $feeds = [];

        // Built-in feeds
        $builtinTypes = ['rss2', 'atom', 'rdf', 'rss'];
        foreach ($builtinTypes as $type) {
            $url = get_bloginfo($type . '_url');
            if ($url !== '') {
                $feeds[] = [
                    'type' => $type,
                    'url' => $url,
                    'is_custom' => false,
                ];
            }
        }

        // Comments feed
        $commentsFeed = get_bloginfo('comments_rss2_url');
        if ($commentsFeed !== '') {
            $feeds[] = [
                'type' => 'comments-rss2',
                'url' => $commentsFeed,
                'is_custom' => false,
            ];
        }

        // Custom feeds from rewrite rules
        $customCount = 0;
        if (isset($wp_rewrite->extra_feeds) && is_array($wp_rewrite->extra_feeds)) {
            foreach ($wp_rewrite->extra_feeds as $feedSlug) {
                if (!in_array($feedSlug, $builtinTypes, true)) {
                    $url = get_feed_link($feedSlug);
                    $feeds[] = [
                        'type' => $feedSlug,
                        'url' => $url,
                        'is_custom' => true,
                    ];
                    $customCount++;
                }
            }
        }

        $feedDiscovery = (bool) get_option('rss_use_excerpt', true);

        $this->data = [
            'feeds' => $feeds,
            'total_count' => count($feeds),
            'custom_count' => $customCount,
            'feed_discovery' => $feedDiscovery,
        ];
    }

    public function getIndicatorValue(): string
    {
        $count = (int) ($this->data['total_count'] ?? 0);

        return $count > 0 ? (string) $count : '';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }
}
