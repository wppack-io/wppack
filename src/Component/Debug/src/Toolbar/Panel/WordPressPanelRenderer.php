<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'wordpress')]
final class WordPressPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'wordpress';
    }

    public function render(array $data): string
    {
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Environment</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('WordPress Version', (string) ($data['wp_version'] ?? 'N/A'));
        $html .= $this->renderTableRow('PHP Version', (string) ($data['php_version'] ?? 'N/A'));
        $html .= $this->renderTableRow('Environment', (string) ($data['environment_type'] ?? 'N/A'));
        $html .= $this->renderTableRow('Theme', (string) ($data['theme'] ?? 'N/A'));

        $isBlockTheme = (bool) ($data['is_block_theme'] ?? false);
        $themeTypeLabel = $isBlockTheme ? 'Block (FSE)' : 'Classic';
        $html .= $this->renderTableRow('Theme Type', '<span class="wpd-tag">' . $this->esc($themeTypeLabel) . '</span>');

        $isChildTheme = (bool) ($data['is_child_theme'] ?? false);
        if ($isChildTheme) {
            $html .= $this->renderTableRow('Child Theme', $this->esc((string) ($data['child_theme'] ?? '')));
            $html .= $this->renderTableRow('Parent Theme', $this->esc((string) ($data['parent_theme'] ?? '')));
        }

        $themeVersion = (string) ($data['theme_version'] ?? '');
        if ($themeVersion !== '') {
            $html .= $this->renderTableRow('Theme Version', $this->esc($themeVersion));
        }

        $html .= $this->renderTableRow('Multisite', ($data['is_multisite'] ?? false) ? 'Yes' : 'No');
        $html .= '</table>';
        $html .= '</div>';

        /** @var array<string, bool|null> $constants */
        $constants = $data['constants'] ?? [];
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

        /** @var list<string> $plugins */
        $plugins = $data['active_plugins'] ?? [];
        if ($plugins !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Active Plugins (' . $this->esc((string) count($plugins)) . ')</h4>';
            $html .= '<ul class="wpd-list">';
            foreach ($plugins as $plugin) {
                $html .= '<li><code>' . $this->esc($plugin) . '</code></li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        /** @var list<string> $extensions */
        $extensions = $data['extensions'] ?? [];
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
