<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

final class RouterPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'router';
    }

    public function render(array $data): string
    {
        $template = (string) ($data['template'] ?? '');
        $templatePath = (string) ($data['template_path'] ?? '');
        $matchedRule = (string) ($data['matched_rule'] ?? '');
        $matchedQuery = (string) ($data['matched_query'] ?? '');
        $queryType = (string) ($data['query_type'] ?? '');
        $is404 = (bool) ($data['is_404'] ?? false);
        $rewriteRulesCount = (int) ($data['rewrite_rules_count'] ?? 0);
        $isBlockTheme = (bool) ($data['is_block_theme'] ?? false);

        $html = '';

        // Template section — FSE vs Classic
        if ($isBlockTheme) {
            /** @var array<string, mixed> $blockTemplate */
            $blockTemplate = $data['block_template'] ?? [];
            $slug = (string) ($blockTemplate['slug'] ?? '');
            $templateId = (string) ($blockTemplate['id'] ?? '');
            $source = (string) ($blockTemplate['source'] ?? '');
            $hasThemeFile = (bool) ($blockTemplate['has_theme_file'] ?? false);
            $filePath = (string) ($blockTemplate['file_path'] ?? '');

            $sourceLabel = $source === 'theme' ? 'Theme file' : ($source !== '' ? 'User customized (DB)' : '-');

            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Block Template (FSE)</h4>';
            $html .= '<table class="wpd-table wpd-table-kv">';
            $html .= $this->renderTableRow('Template Slug', $this->esc($slug ?: '-'));
            $html .= $this->renderTableRow('Template ID', $templateId !== '' ? '<code>' . $this->esc($templateId) . '</code>' : '-');
            $html .= $this->renderTableRow('Source', $this->esc($sourceLabel));
            $html .= $this->renderTableRow('Has Theme File', $this->formatValue($hasThemeFile));
            $html .= $this->renderTableRow('File Path', $this->esc($filePath ?: '-'));
            $html .= '</table>';
            $html .= '</div>';

            /** @var list<array<string, mixed>> $parts */
            $parts = $blockTemplate['parts'] ?? [];
            if ($parts !== []) {
                $html .= '<div class="wpd-section">';
                $html .= '<h4 class="wpd-section-title">Template Parts</h4>';
                $html .= '<table class="wpd-table wpd-table-full">';
                $html .= '<thead><tr><th>Slug</th><th>Area</th><th>Source</th></tr></thead>';
                $html .= '<tbody>';
                foreach ($parts as $part) {
                    $partSource = (string) ($part['source'] ?? '');
                    $partSourceLabel = $partSource === 'theme' ? 'Theme file' : ($partSource !== '' ? 'User customized (DB)' : '-');
                    $html .= '<tr>';
                    $html .= '<td><code>' . $this->esc((string) ($part['slug'] ?? '')) . '</code></td>';
                    $html .= '<td>' . $this->esc((string) ($part['area'] ?? '')) . '</td>';
                    $html .= '<td>' . $this->esc($partSourceLabel) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
                $html .= '</div>';
            }
        } else {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Template (Classic)</h4>';
            $html .= '<table class="wpd-table wpd-table-kv">';
            $html .= $this->renderTableRow('Template', $this->esc($template ?: '-'));
            $html .= $this->renderTableRow('Template Path', $this->esc($templatePath ?: '-'));
            $html .= '</table>';
            $html .= '</div>';
        }

        // Route section
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Route</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Query Type', $this->esc($queryType ?: '-'));
        $html .= $this->renderTableRow('Matched Rule', $matchedRule !== '' ? '<code>' . $this->esc($matchedRule) . '</code>' : '-');
        $html .= $this->renderTableRow('Matched Query', $this->esc($matchedQuery ?: '-'));
        $html .= $this->renderTableRow('404', $this->formatValue($is404));
        $html .= $this->renderTableRow('Rewrite Rules', (string) $rewriteRulesCount);
        $html .= '</table>';
        $html .= '</div>';

        /** @var array<string, string> $queryVars */
        $queryVars = $data['query_vars'] ?? [];
        if ($queryVars !== []) {
            $html .= $this->renderKeyValueSection('Query Variables', $queryVars);
        }

        // Conditional tags
        $conditionals = [];
        if ($data['is_front_page'] ?? false) {
            $conditionals[] = 'is_front_page';
        }
        if ($data['is_singular'] ?? false) {
            $conditionals[] = 'is_singular';
        }
        if ($data['is_archive'] ?? false) {
            $conditionals[] = 'is_archive';
        }
        if ($is404) {
            $conditionals[] = 'is_404';
        }
        if ($conditionals !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Conditional Tags</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($conditionals as $tag) {
                $html .= '<span class="wpd-tag wpd-text-green">' . $this->esc($tag) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }
}
