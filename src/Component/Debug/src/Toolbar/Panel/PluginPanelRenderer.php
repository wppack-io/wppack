<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'plugin')]
final class PluginPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'plugin';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        $totalPlugins = (int) ($data['total_plugins'] ?? 0);
        $totalHookTime = (float) ($data['total_hook_time'] ?? 0.0);
        $slowestPlugin = (string) ($data['slowest_plugin'] ?? '');
        /** @var array<string, array<string, mixed>> $plugins */
        $plugins = $data['plugins'] ?? [];
        /** @var list<string> $muPlugins */
        $muPlugins = $data['mu_plugins'] ?? [];
        /** @var list<string> $dropins */
        $dropins = $data['dropins'] ?? [];

        // === List view ===
        $html = '<div class="wpd-plugin-list">';

        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Active Plugins', (string) $totalPlugins);
        $html .= $this->renderTableRow('Total Hook Time', $this->formatMs($totalHookTime));
        $html .= '</table>';
        $html .= '</div>';

        // Split plugins into MU and regular
        $muPluginsList = array_filter($plugins, static fn(array $info): bool => (bool) ($info['is_mu'] ?? false));
        $regularPluginsList = array_filter($plugins, static fn(array $info): bool => !((bool) ($info['is_mu'] ?? false)));

        if ($muPluginsList !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Must-Use Plugins</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Plugin</th>';
            $html .= '<th>Version</th>';
            $html .= '<th class="wpd-col-right">Load</th>';
            $html .= '<th class="wpd-col-right">Hook Time</th>';
            $html .= '<th class="wpd-col-right">Queries</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($muPluginsList as $slug => $info) {
                $name = (string) ($info['name'] ?? $slug);
                $version = (string) ($info['version'] ?? '');
                $loadTime = (float) ($info['load_time'] ?? 0.0);
                $hookTime = (float) ($info['hook_time'] ?? 0.0);
                $queryCount = (int) ($info['query_count'] ?? 0);

                $html .= '<tr>';
                $slowTag = ($slug === $slowestPlugin) ? ' <span class="wpd-query-tag" style="background:rgba(153,104,0,0.08);color:#996800">Slow</span>' : '';
                $html .= '<td><span class="wpd-plugin-detail-link" data-plugin="' . $this->esc($slug) . '">' . $this->esc($name) . '</span>' . $slowTag . '</td>';
                $html .= '<td>' . ($version !== '' ? $this->esc($version) : '-') . '</td>';
                $html .= '<td class="wpd-col-right">' . ($loadTime > 0 ? $this->formatMs($loadTime) : '-') . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatMs($hookTime) . '</td>';
                $html .= '<td class="wpd-col-right">' . ($queryCount > 0 ? $this->esc((string) $queryCount) : '-') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        if ($regularPluginsList !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Plugins</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Plugin</th>';
            $html .= '<th>Version</th>';
            $html .= '<th class="wpd-col-right">Load</th>';
            $html .= '<th class="wpd-col-right">Hook Time</th>';
            $html .= '<th class="wpd-col-right">Queries</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($regularPluginsList as $slug => $info) {
                $name = (string) ($info['name'] ?? $slug);
                $version = (string) ($info['version'] ?? '');
                $loadTime = (float) ($info['load_time'] ?? 0.0);
                $hookTime = (float) ($info['hook_time'] ?? 0.0);
                $queryCount = (int) ($info['query_count'] ?? 0);

                $html .= '<tr>';
                $slowTag = ($slug === $slowestPlugin) ? ' <span class="wpd-query-tag" style="background:rgba(153,104,0,0.08);color:#996800">Slow</span>' : '';
                $html .= '<td><span class="wpd-plugin-detail-link" data-plugin="' . $this->esc($slug) . '">' . $this->esc($name) . '</span>' . $slowTag . '</td>';
                $html .= '<td>' . ($version !== '' ? $this->esc($version) : '-') . '</td>';
                $html .= '<td class="wpd-col-right">' . ($loadTime > 0 ? $this->formatMs($loadTime) : '-') . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatMs($hookTime) . '</td>';
                $html .= '<td class="wpd-col-right">' . ($queryCount > 0 ? $this->esc((string) $queryCount) : '-') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        if ($dropins !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Drop-ins</h4>';
            $html .= '<ul class="wpd-list">';
            foreach ($dropins as $dropin) {
                $html .= '<li><code>' . $this->esc($dropin) . '</code></li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .wpd-plugin-list

        // === Detail views (one per plugin, hidden by default) ===
        foreach ($plugins as $slug => $info) {
            $html .= $this->renderPluginDetailView($slug, $info);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function renderPluginDetailView(string $slug, array $info): string
    {
        $name = (string) ($info['name'] ?? $slug);
        $version = (string) ($info['version'] ?? '');
        $loadTime = (float) ($info['load_time'] ?? 0.0);
        $hookTime = (float) ($info['hook_time'] ?? 0.0);
        $queryTime = (float) ($info['query_time'] ?? 0.0);
        $hookCount = (int) ($info['hook_count'] ?? 0);
        $listenerCount = (int) ($info['listener_count'] ?? 0);
        /** @var list<array{hook: string, listeners: int, time: float}> $hooks */
        $hooks = $info['hooks'] ?? [];
        /** @var list<string> $enqueuedStyles */
        $enqueuedStyles = $info['enqueued_styles'] ?? [];
        /** @var list<string> $enqueuedScripts */
        $enqueuedScripts = $info['enqueued_scripts'] ?? [];

        $html = '<div class="wpd-plugin-detail" data-plugin="' . $this->esc($slug) . '" style="display:none">';

        // Back button
        $html .= '<div style="margin-bottom:12px">';
        $html .= '<button class="wpd-plugin-back" data-action="plugin-back">&larr; Back to Plugins</button>';
        $html .= '</div>';

        // Plugin Info
        $isMu = (bool) ($info['is_mu'] ?? false);
        $infoTitle = $isMu ? 'MU Plugin Info' : 'Plugin Info';
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">' . $infoTitle . '</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Name', $this->esc($name));
        if ($version !== '') {
            $html .= $this->renderTableRow('Version', $this->esc($version));
        }
        $html .= '</table>';
        $html .= '</div>';

        // Timing cards
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Timing</h4>';
        $html .= '<div class="wpd-perf-cards">';
        if ($loadTime > 0) {
            [$lv, $lu] = $this->formatMsCard($loadTime);
            $html .= $this->renderPerfCard('Load Time', $lv, $lu, '');
        } else {
            $html .= $this->renderPerfCard('Load Time', '-', '', '');
        }
        [$hv, $hu] = $this->formatMsCard($hookTime);
        $html .= $this->renderPerfCard('Hook Time', $hv, $hu, $this->esc((string) $hookCount) . ' hooks, ' . $this->esc((string) $listenerCount) . ' listeners');
        if ($queryTime > 0) {
            [$qv, $qu] = $this->formatMsCard($queryTime);
            $html .= $this->renderPerfCard('Query Time', $qv, $qu, '');
        } else {
            $html .= $this->renderPerfCard('Query Time', '-', '', '');
        }
        $html .= '</div>';
        $html .= '</div>';

        // Hook Breakdown
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
                $html .= '<td class="wpd-col-right">' . $this->formatMs((float) $hookInfo['time']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Enqueued Assets
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

        $html .= '</div>'; // .wpd-plugin-detail

        return $html;
    }
}
