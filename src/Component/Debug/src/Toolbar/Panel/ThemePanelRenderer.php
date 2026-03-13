<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'theme')]
final class ThemePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'theme';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        $name = (string) ($data['name'] ?? '');
        $version = (string) ($data['version'] ?? '');
        $isChildTheme = (bool) ($data['is_child_theme'] ?? false);
        $isBlockTheme = (bool) ($data['is_block_theme'] ?? false);
        $setupTime = (float) ($data['setup_time'] ?? 0.0);
        $renderTime = (float) ($data['render_time'] ?? 0.0);
        $hookTime = (float) ($data['hook_time'] ?? 0.0);
        $hookCount = (int) ($data['hook_count'] ?? 0);
        $listenerCount = (int) ($data['listener_count'] ?? 0);
        $templateFile = (string) ($data['template_file'] ?? '');
        /** @var list<string> $templateParts */
        $templateParts = $data['template_parts'] ?? [];
        /** @var list<string> $bodyClasses */
        $bodyClasses = $data['body_classes'] ?? [];
        /** @var array<string, bool> $conditionalTags */
        $conditionalTags = $data['conditional_tags'] ?? [];
        /** @var list<string> $enqueuedStyles */
        $enqueuedStyles = $data['enqueued_styles'] ?? [];
        /** @var list<string> $enqueuedScripts */
        $enqueuedScripts = $data['enqueued_scripts'] ?? [];
        /** @var list<array{hook: string, listeners: int, time: float}> $hooks */
        $hooks = $data['hooks'] ?? [];

        // Theme Info
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Info</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Name', $this->esc($name ?: '-'));
        if ($version !== '') {
            $html .= $this->renderTableRow('Version', $this->esc($version));
        }
        $html .= $this->renderTableRow('Child Theme', $this->formatValue($isChildTheme));
        if ($isChildTheme) {
            $html .= $this->renderTableRow('Child', $this->esc((string) ($data['child_theme'] ?? '')));
            $html .= $this->renderTableRow('Parent', $this->esc((string) ($data['parent_theme'] ?? '')));
        }
        $html .= $this->renderTableRow('Block Theme', $this->formatValue($isBlockTheme));
        $html .= '</table>';
        $html .= '</div>';

        // Timing cards
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Timing</h4>';
        $html .= '<div class="wpd-perf-cards">';
        [$sv, $su] = $this->formatMsCard($setupTime);
        $html .= $this->renderPerfCard('Setup Time', $sv, $su, '');
        [$rv, $ru] = $this->formatMsCard($renderTime);
        $html .= $this->renderPerfCard('Render Time', $rv, $ru, '');
        [$hv, $hu] = $this->formatMsCard($hookTime);
        $html .= $this->renderPerfCard('Hook Time', $hv, $hu, $this->esc((string) $hookCount) . ' hooks, ' . $this->esc((string) $listenerCount) . ' listeners');
        $html .= '</div>';
        $html .= '</div>';

        // Hook breakdown
        if ($hooks !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Hook Breakdown</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr><th>Hook</th><th class="wpd-col-right">Listeners</th><th class="wpd-col-right">Time</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($hooks as $hookInfo) {
                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($hookInfo['hook']) . '</code></td>';
                $html .= '<td class="wpd-col-right">' . $this->esc((string) $hookInfo['listeners']) . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatMs($hookInfo['time']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Template
        if ($templateFile !== '' || $templateParts !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Template</h4>';
            $html .= '<table class="wpd-table wpd-table-kv">';
            if ($templateFile !== '') {
                $html .= $this->renderTableRow('Template File', '<code>' . $this->esc($templateFile) . '</code>');
            }
            if ($templateParts !== []) {
                $html .= $this->renderTableRow('Template Parts', '<code>' . $this->esc(implode(', ', $templateParts)) . '</code>');
            }
            $html .= '</table>';
            $html .= '</div>';
        }

        // Conditional Tags
        if ($conditionalTags !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Conditional Tags</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($conditionalTags as $tag => $value) {
                $color = $value ? 'wpd-text-green' : 'wpd-text-dim';
                $html .= '<span class="wpd-tag ' . $color . '">' . $this->esc($tag) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        // Assets
        if ($enqueuedStyles !== [] || $enqueuedScripts !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Enqueued Assets</h4>';
            if ($enqueuedStyles !== []) {
                $html .= '<div style="margin-bottom:4px"><strong style="color:#757575;font-size:11px">Styles</strong></div>';
                $html .= '<div class="wpd-tag-list" style="margin-bottom:8px">';
                foreach ($enqueuedStyles as $style) {
                    $html .= '<span class="wpd-tag">' . $this->esc($style) . '</span>';
                }
                $html .= '</div>';
            }
            if ($enqueuedScripts !== []) {
                $html .= '<div style="margin-bottom:4px"><strong style="color:#757575;font-size:11px">Scripts</strong></div>';
                $html .= '<div class="wpd-tag-list">';
                foreach ($enqueuedScripts as $script) {
                    $html .= '<span class="wpd-tag">' . $this->esc($script) . '</span>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Body classes
        if ($bodyClasses !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Body Classes</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($bodyClasses as $class) {
                $html .= '<span class="wpd-tag">' . $this->esc($class) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }
}
