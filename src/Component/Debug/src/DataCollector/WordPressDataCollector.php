<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'wordpress', priority: 50)]
final class WordPressDataCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'wordpress';
    }

    public function getLabel(): string
    {
        return 'WordPress';
    }

    public function collect(): void
    {
        $this->data = [
            'wp_version' => $this->getWpVersion(),
            'php_version' => PHP_VERSION,
            'theme' => $this->getActiveTheme(),
            'active_plugins' => $this->getActivePlugins(),
            'environment_type' => $this->getEnvironmentType(),
            'is_multisite' => $this->isMultisite(),
            'extensions' => get_loaded_extensions(),
            'constants' => $this->getDebugConstants(),
        ];
    }

    public function getBadgeValue(): string
    {
        return $this->data['wp_version'] ?? '';
    }

    public function getBadgeColor(): string
    {
        return 'default';
    }

    private function getWpVersion(): string
    {
        global $wp_version;

        if (isset($wp_version) && is_string($wp_version)) {
            return $wp_version;
        }

        return '';
    }

    private function getActiveTheme(): string
    {
        if (!function_exists('get_template')) {
            return '';
        }

        return get_template();
    }

    /**
     * @return list<string>
     */
    private function getActivePlugins(): array
    {
        if (!function_exists('get_option')) {
            return [];
        }

        $plugins = get_option('active_plugins');

        if (!is_array($plugins)) {
            return [];
        }

        return array_values($plugins);
    }

    private function getEnvironmentType(): string
    {
        if (function_exists('wp_get_environment_type')) {
            return wp_get_environment_type();
        }

        return '';
    }

    private function isMultisite(): bool
    {
        if (function_exists('is_multisite')) {
            return is_multisite();
        }

        return false;
    }

    /**
     * @return array<string, bool|null>
     */
    private function getDebugConstants(): array
    {
        $constants = [
            'WP_DEBUG',
            'SAVEQUERIES',
            'SCRIPT_DEBUG',
            'WP_DEBUG_LOG',
            'WP_DEBUG_DISPLAY',
            'WP_CACHE',
        ];

        $result = [];
        foreach ($constants as $constant) {
            $result[$constant] = defined($constant) ? constant($constant) : null;
        }

        return $result;
    }
}
