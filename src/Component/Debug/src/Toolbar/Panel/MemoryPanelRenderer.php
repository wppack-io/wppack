<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'memory')]
final class MemoryPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'memory';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        $current = (int) ($data['current'] ?? 0);
        $peak = (int) ($data['peak'] ?? 0);
        $limit = (int) ($data['limit'] ?? 0);
        $usagePercentage = (float) ($data['usage_percentage'] ?? 0.0);
        /** @var array<string, int> $snapshots */
        $snapshots = $data['snapshots'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Current Usage', $this->formatBytes($current));
        $html .= $this->renderTableRow('Peak Usage', $this->formatBytes($peak));
        $html .= $this->renderTableRow('Memory Limit', $limit > 0 ? $this->formatBytes($limit) : 'Unlimited');
        $usageColor = match (true) {
            $usagePercentage >= 90 => 'wpd-text-red',
            $usagePercentage >= 70 => 'wpd-text-yellow',
            default => 'wpd-text-green',
        };
        $barColor = match (true) {
            $usagePercentage >= 90 => '#cc1818',
            $usagePercentage >= 70 => '#996800',
            default => '#008a20',
        };
        $usageValue = '<span class="wpd-inline-bar"><span class="wpd-inline-bar-fill" style="width:' . $this->esc(sprintf('%.1f', min($usagePercentage, 100))) . '%;background:' . $this->esc($barColor) . '"></span></span>'
            . '<span class="' . $usageColor . '">' . $this->esc(sprintf('%.1f%%', $usagePercentage)) . '</span>';
        $html .= $this->renderTableRow('Usage', $usageValue);
        $html .= '</table>';
        $html .= '</div>';

        if ($snapshots !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Memory Snapshots</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Checkpoint</th>';
            $html .= '<th class="wpd-col-right">Memory</th>';
            $html .= '<th class="wpd-col-right">Delta</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            $previousMemory = 0;
            foreach ($snapshots as $snapshotLabel => $snapshotMemory) {
                $delta = $previousMemory > 0 ? $snapshotMemory - $previousMemory : 0;
                $deltaSign = $delta >= 0 ? '+' : '';
                $deltaClass = $delta > 1024 * 1024 ? ' wpd-text-yellow' : '';

                $html .= '<tr>';
                $html .= '<td>' . $this->esc($snapshotLabel) . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatBytes($snapshotMemory) . '</td>';
                $html .= '<td class="wpd-col-right' . $deltaClass . '">' . $deltaSign . $this->formatBytes(abs($delta)) . '</td>';
                $html .= '</tr>';

                $previousMemory = $snapshotMemory;
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
