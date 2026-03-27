<?php

declare(strict_types=1);

/**
 * Plugin Name:  WpPack MU-Plugin Loader
 * Description:  Autoload Must-use plugins from subdirectories
 * Version:      1.0.0
 * Author:       WpPack
 * License:      MIT License
 */

namespace WpPack\MuPlugin;

final class MuPluginLoader
{
    /**
     * Discovered plugins keyed by relative path (e.g. "wppack-lambda/wppack-lambda.php").
     *
     * @var array<string, array{Name: string, Version: string}>
     */
    private array $plugins = [];

    private const string CACHE_KEY = 'wppack_mu_plugins';

    public function __construct()
    {
        $this->loadPlugins();

        add_filter('show_advanced_plugins', [$this, 'showInAdminList'], 10, 2);
    }

    private function loadPlugins(): void
    {
        $this->plugins = $this->getPlugins();

        $muPluginDir = WPMU_PLUGIN_DIR;

        foreach (array_keys($this->plugins) as $relativePath) {
            require_once $muPluginDir . '/' . $relativePath;
        }
    }

    /**
     * @return array<string, array{Name: string, Version: string}>
     */
    private function getPlugins(): array
    {
        $cached = get_site_option(self::CACHE_KEY);

        if (\is_array($cached) && $this->isCacheValid($cached)) {
            /** @var array<string, array{Name: string, Version: string}> */
            return $cached;
        }

        $plugins = $this->discoverPlugins();
        update_site_option(self::CACHE_KEY, $plugins);

        return $plugins;
    }

    /**
     * @param array<string, array{Name: string, Version: string}> $cached
     */
    private function isCacheValid(array $cached): bool
    {
        $muPluginDir = WPMU_PLUGIN_DIR;

        foreach (array_keys($cached) as $relativePath) {
            if (!is_readable($muPluginDir . '/' . $relativePath)) {
                return false;
            }
        }

        // Check if any new subdirectories have been added
        $dirs = glob($muPluginDir . '/*/');
        if ($dirs === false) {
            return true;
        }

        $cachedDirs = [];
        foreach (array_keys($cached) as $relativePath) {
            $cachedDirs[\dirname($relativePath)] = true;
        }

        foreach ($dirs as $dir) {
            $dirName = basename(rtrim($dir, '/'));
            if (!isset($cachedDirs[$dirName])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array{Name: string, Version: string}>
     */
    private function discoverPlugins(): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $muPluginDir = WPMU_PLUGIN_DIR;
        $plugins = [];

        $dirs = glob($muPluginDir . '/*/');
        if ($dirs === false) {
            return $plugins;
        }

        foreach ($dirs as $dir) {
            $files = glob($dir . '*.php');
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $headers = get_plugin_data($file, false, false);

                if (!empty($headers['Name'])) {
                    $relativePath = basename(\dirname($file)) . '/' . basename($file);
                    $plugins[$relativePath] = [
                        'Name' => $headers['Name'],
                        'Version' => $headers['Version'],
                    ];
                }
            }
        }

        return $plugins;
    }

    /**
     * Show autoloaded mu-plugins in the admin Must-Use list.
     */
    public function showInAdminList(bool $show, string $type): bool
    {
        if ($type !== 'mustuse') {
            return $show;
        }

        $this->addPluginsToAdminList();

        return false;
    }

    private function addPluginsToAdminList(): void
    {
        global $plugins;

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        // Top-level mu-plugins (including this loader)
        $plugins['mustuse'] = get_mu_plugins();

        // Add subdirectory mu-plugins
        $muPluginDir = WPMU_PLUGIN_DIR;

        foreach (array_keys($this->plugins) as $relativePath) {
            $plugins['mustuse'][$relativePath] = get_plugin_data(
                $muPluginDir . '/' . $relativePath,
                false,
                false,
            );
        }
    }
}

if (is_blog_installed()) {
    new MuPluginLoader();
}
