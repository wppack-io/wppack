<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'feed')]
final class FeedPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'feed';
    }

    public function render(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        $totalCount = (int) ($data['total_count'] ?? 0);
        $customCount = (int) ($data['custom_count'] ?? 0);
        $feedDiscovery = $data['feed_discovery'] ?? true;
        /** @var list<array{type: string, url: string, is_custom: bool}> $feeds */
        $feeds = $data['feeds'] ?? [];

        // Summary
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Feeds', (string) $totalCount);
        $html .= $this->renderTableRow('Custom Feeds', (string) $customCount);
        $html .= $this->renderTableRow('Feed Discovery', $this->formatValue($feedDiscovery));
        $html .= '</table>';
        $html .= '</div>';

        // Feeds table
        if ($feeds !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Feeds</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Type</th>';
            $html .= '<th>URL</th>';
            $html .= '<th>Custom</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($feeds as $feed) {
                $customHtml = $feed['is_custom']
                    ? '<span class="wpd-text-yellow">Yes</span>'
                    : '<span class="wpd-text-dim">No</span>';

                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($feed['type']) . '</code></td>';
                $html .= '<td class="wpd-text-dim" style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . $this->esc($feed['url']) . '</td>';
                $html .= '<td>' . $customHtml . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
