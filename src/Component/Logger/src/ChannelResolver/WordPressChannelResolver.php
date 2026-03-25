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

namespace WpPack\Component\Logger\ChannelResolver;

final class WordPressChannelResolver implements ChannelResolverInterface
{
    public function resolve(string $filePath): string
    {
        // Check plugin directory
        $pluginDir = \WP_PLUGIN_DIR;
        if (str_starts_with($filePath, $pluginDir)) {
            $relative = substr($filePath, strlen($pluginDir) + 1);
            $parts = explode('/', $relative, 2);

            return 'plugin:' . $parts[0];
        }

        // Check must-use plugin directory (treated as plugin)
        $muPluginDir = \WPMU_PLUGIN_DIR;
        if (str_starts_with($filePath, $muPluginDir)) {
            $relative = substr($filePath, strlen($muPluginDir) + 1);
            $parts = explode('/', $relative, 2);

            return 'plugin:' . $parts[0];
        }

        // Check theme directory
        $themeDir = \ABSPATH . 'wp-content/themes';
        if (str_starts_with($filePath, $themeDir)) {
            $relative = substr($filePath, strlen($themeDir) + 1);
            $parts = explode('/', $relative, 2);

            return 'theme:' . $parts[0];
        }

        // Check WordPress core
        if (str_starts_with($filePath, \ABSPATH . 'wp-includes') || str_starts_with($filePath, \ABSPATH . 'wp-admin')) {
            return 'wordpress';
        }

        return 'php';
    }
}
