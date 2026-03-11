<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'wordpress', priority: 40)]
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
        $themeInfo = $this->getThemeInfo();

        $this->data = [
            'wp_version' => $this->getWpVersion(),
            'php_version' => PHP_VERSION,
            'theme' => $themeInfo['theme'],
            'is_block_theme' => $themeInfo['is_block_theme'],
            'is_child_theme' => $themeInfo['is_child_theme'],
            'child_theme' => $themeInfo['child_theme'],
            'parent_theme' => $themeInfo['parent_theme'],
            'theme_version' => $themeInfo['theme_version'],
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

    /**
     * @return array{theme: string, is_block_theme: bool, is_child_theme: bool, child_theme: string, parent_theme: string, theme_version: string}
     */
    private function getThemeInfo(): array
    {
        $info = [
            'theme' => '',
            'is_block_theme' => false,
            'is_child_theme' => false,
            'child_theme' => '',
            'parent_theme' => '',
            'theme_version' => '',
        ];

        if (!function_exists('wp_get_theme')) {
            return $info;
        }

        $theme = wp_get_theme();
        $info['theme'] = $theme->get('Name');
        $info['theme_version'] = $theme->get('Version');

        if (function_exists('get_stylesheet') && function_exists('get_template')) {
            $stylesheet = get_stylesheet();
            $template = get_template();
            $info['is_child_theme'] = $stylesheet !== $template;
            if ($info['is_child_theme']) {
                $info['child_theme'] = $stylesheet;
                $info['parent_theme'] = $template;
            }
        }

        if (function_exists('wp_is_block_theme')) {
            $info['is_block_theme'] = wp_is_block_theme();
        }

        return $info;
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
