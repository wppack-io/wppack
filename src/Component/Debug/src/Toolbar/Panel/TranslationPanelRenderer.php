<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'translation')]
final class TranslationPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'translation';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        $totalLookups = (int) ($data['total_lookups'] ?? 0);
        $missingCount = (int) ($data['missing_count'] ?? 0);
        /** @var list<string> $loadedDomains */
        $loadedDomains = $data['loaded_domains'] ?? [];
        /** @var array<string, int> $domainUsage */
        $domainUsage = $data['domain_usage'] ?? [];
        /** @var list<array<string, string>> $missing */
        $missing = $data['missing_translations'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Lookups', (string) $totalLookups);
        $html .= $this->renderTableRow('Loaded Domains', (string) count($loadedDomains));
        $html .= $this->renderTableRow('Missing Translations', (string) $missingCount, $missingCount > 0 ? 'wpd-text-yellow' : '');
        $html .= '</table>';
        $html .= '</div>';

        if ($loadedDomains !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Loaded Domains</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($loadedDomains as $domain) {
                $html .= '<span class="wpd-tag">' . $this->esc($domain) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        if ($domainUsage !== []) {
            arsort($domainUsage);
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Domain Usage</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr><th>Domain</th><th class="wpd-col-right">Lookups</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($domainUsage as $domain => $count) {
                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc((string) $domain) . '</code></td>';
                $html .= '<td class="wpd-col-right">' . $this->esc((string) $count) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        if ($missing !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Missing Translations</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-num">#</th>';
            $html .= '<th>Original</th>';
            $html .= '<th>Domain</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';
            foreach ($missing as $index => $entry) {
                $html .= '<tr>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td><code>' . $this->esc($entry['original'] ?? '') . '</code></td>';
                $html .= '<td>' . $this->esc($entry['domain'] ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
