<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\ChannelResolver;

final class WordPressChannelResolver implements ChannelResolverInterface
{
    public function resolve(string $filePath): string
    {
        // Check plugin directory
        $pluginDir = defined('WP_PLUGIN_DIR') ? \WP_PLUGIN_DIR : '';
        if ($pluginDir !== '' && str_starts_with($filePath, $pluginDir)) {
            $relative = substr($filePath, strlen($pluginDir) + 1);
            $parts = explode('/', $relative, 2);

            return 'plugin:' . $parts[0];
        }

        // Check must-use plugin directory (treated as plugin)
        $muPluginDir = defined('WPMU_PLUGIN_DIR') ? \WPMU_PLUGIN_DIR : '';
        if ($muPluginDir !== '' && str_starts_with($filePath, $muPluginDir)) {
            $relative = substr($filePath, strlen($muPluginDir) + 1);
            $parts = explode('/', $relative, 2);

            return 'plugin:' . $parts[0];
        }

        // Check theme directory
        $absPath = defined('ABSPATH') ? \ABSPATH : '';
        $themeDir = $absPath !== '' ? ($absPath . 'wp-content/themes') : '';
        if ($themeDir !== '' && str_starts_with($filePath, $themeDir)) {
            $relative = substr($filePath, strlen($themeDir) + 1);
            $parts = explode('/', $relative, 2);

            return 'theme:' . $parts[0];
        }

        // Check WordPress core
        if ($absPath !== '' && (str_starts_with($filePath, $absPath . 'wp-includes') || str_starts_with($filePath, $absPath . 'wp-admin'))) {
            return 'wordpress';
        }

        return 'php';
    }
}
