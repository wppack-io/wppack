<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'wordpress')]
final class WordPressPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'wordpress';
    }

    public function renderPanel(Profile $profile): string
    {
        $wpData = $this->getCollectorData($profile, 'wordpress');
        $envData = $this->getCollectorData($profile, 'environment');
        $themeData = $this->getCollectorData($profile, 'theme');
        $pluginData = $this->getCollectorData($profile, 'plugin');

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Environment</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('WordPress Version', (string) ($wpData['wp_version'] ?? 'N/A'));

        $phpVersion = (string) ($envData['php']['version'] ?? PHP_VERSION);
        $html .= $this->renderTableRow('PHP Version', $phpVersion);
        $html .= $this->renderTableRow('Environment', (string) ($wpData['environment_type'] ?? 'N/A'));
        $html .= $this->renderTableRow('Multisite', ($wpData['is_multisite'] ?? false) ? 'Yes' : 'No');
        $html .= '</table>';
        $html .= '</div>';

        /** @var array<string, bool|null> $constants */
        $constants = $wpData['constants'] ?? [];
        if ($constants !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Debug Constants</h4>';
            $html .= '<table class="wpd-table wpd-table-kv">';
            foreach ($constants as $constant => $value) {
                $display = match ($value) {
                    null => '<span class="wpd-text-dim">undefined</span>',
                    true => '<span class="wpd-text-green">true</span>',
                    false => '<span class="wpd-text-red">false</span>',
                };
                $html .= '<tr><td class="wpd-kv-key">' . $this->esc($constant) . '</td><td class="wpd-kv-val">' . $display . '</td></tr>';
            }
            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Active Theme</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $themeName = (string) ($themeData['name'] ?? 'N/A');
        $html .= $this->renderTableRow('Name', $themeName);

        if ($themeName !== 'N/A') {
            $isBlockTheme = (bool) ($themeData['is_block_theme'] ?? false);
            $themeTypeLabel = $isBlockTheme ? 'Block (FSE)' : 'Classic';
            $html .= $this->renderTableRow('Type', '<span class="wpd-tag">' . $this->esc($themeTypeLabel) . '</span>');
        }

        $isChildTheme = (bool) ($themeData['is_child_theme'] ?? false);
        if ($isChildTheme) {
            $html .= $this->renderTableRow('Parent Theme', $this->esc((string) ($themeData['parent_theme'] ?? '')));
        }

        $themeVersion = (string) ($themeData['version'] ?? '');
        if ($themeVersion !== '') {
            $html .= $this->renderTableRow('Version', $this->esc($themeVersion));
        }

        $html .= '</table>';
        $html .= '</div>';

        // Separate MU and regular plugins from plugin data
        /** @var array<string, array<string, mixed>> $allPlugins */
        $allPlugins = $pluginData['plugins'] ?? [];
        $muPlugins = [];
        $activePlugins = [];
        foreach ($allPlugins as $slug => $info) {
            if ($info['is_mu'] ?? false) {
                $muPlugins[$slug] = (string) ($info['name'] ?? $slug);
            } else {
                $activePlugins[$slug] = (string) ($info['name'] ?? $slug);
            }
        }

        if ($muPlugins !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Must-Use Plugins (' . $this->esc((string) count($muPlugins)) . ')</h4>';
            $html .= '<ul class="wpd-list">';
            foreach ($muPlugins as $muPlugin) {
                $html .= '<li>' . $this->esc($muPlugin) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        if ($activePlugins !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Active Plugins (' . $this->esc((string) count($activePlugins)) . ')</h4>';
            $html .= '<ul class="wpd-list">';
            foreach ($activePlugins as $plugin) {
                $html .= '<li>' . $this->esc($plugin) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        /** @var list<string> $extensions */
        $extensions = $envData['extensions'] ?? [];
        if ($extensions !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">PHP Extensions (' . $this->esc((string) count($extensions)) . ')</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($extensions as $ext) {
                $html .= '<span class="wpd-tag">' . $this->esc($ext) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }
}
