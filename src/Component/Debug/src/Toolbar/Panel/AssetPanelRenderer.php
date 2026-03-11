<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'asset')]
final class AssetPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'asset';
    }

    public function render(array $data): string
    {
        $enqueuedScripts = (int) ($data['enqueued_scripts'] ?? 0);
        $enqueuedStyles = (int) ($data['enqueued_styles'] ?? 0);
        $registeredScripts = (int) ($data['registered_scripts'] ?? 0);
        $registeredStyles = (int) ($data['registered_styles'] ?? 0);
        /** @var array<string, array<string, mixed>> $scripts */
        $scripts = $data['scripts'] ?? [];
        /** @var array<string, array<string, mixed>> $styles */
        $styles = $data['styles'] ?? [];

        // Summary
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Enqueued Scripts', (string) $enqueuedScripts);
        $html .= $this->renderTableRow('Enqueued Styles', (string) $enqueuedStyles);
        $html .= $this->renderTableRow('Registered Scripts', (string) $registeredScripts);
        $html .= $this->renderTableRow('Registered Styles', (string) $registeredStyles);
        $html .= '</table>';
        $html .= '</div>';

        // Enqueued Scripts
        $enqueuedScriptsList = array_filter($scripts, static fn(array $s): bool => (bool) ($s['enqueued'] ?? false));
        if ($enqueuedScriptsList !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Enqueued Scripts (' . count($enqueuedScriptsList) . ')</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Handle</th>';
            $html .= '<th>Source</th>';
            $html .= '<th>Version</th>';
            $html .= '<th>Footer</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($enqueuedScriptsList as $handle => $info) {
                $src = (string) ($info['src'] ?? '');
                $version = (string) ($info['version'] ?? '');
                $inFooter = (bool) ($info['in_footer'] ?? false);

                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($handle) . '</code></td>';
                $html .= '<td class="wpd-text-dim" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . ($src !== '' ? $this->esc($src) : '-') . '</td>';
                $html .= '<td>' . ($version !== '' ? $this->esc($version) : '-') . '</td>';
                $html .= '<td>' . $this->formatValue($inFooter) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Enqueued Styles
        $enqueuedStylesList = array_filter($styles, static fn(array $s): bool => (bool) ($s['enqueued'] ?? false));
        if ($enqueuedStylesList !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Enqueued Styles (' . count($enqueuedStylesList) . ')</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Handle</th>';
            $html .= '<th>Source</th>';
            $html .= '<th>Version</th>';
            $html .= '<th>Media</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($enqueuedStylesList as $handle => $info) {
                $src = (string) ($info['src'] ?? '');
                $version = (string) ($info['version'] ?? '');
                $media = (string) ($info['media'] ?? 'all');

                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($handle) . '</code></td>';
                $html .= '<td class="wpd-text-dim" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . ($src !== '' ? $this->esc($src) : '-') . '</td>';
                $html .= '<td>' . ($version !== '' ? $this->esc($version) : '-') . '</td>';
                $html .= '<td>' . $this->esc($media) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
