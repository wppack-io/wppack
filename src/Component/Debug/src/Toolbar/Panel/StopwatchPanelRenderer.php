<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'stopwatch')]
final class StopwatchPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'stopwatch';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        $totalTime = (float) ($data['total_time'] ?? 0.0);
        /** @var array<string, float> $phases */
        $phases = $data['phases'] ?? [];
        /** @var array<string, array{name: string, category: string, duration: float, memory: int, start_time: float, end_time: float}> $events */
        $events = $data['events'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Time', $this->formatMs($totalTime));
        $html .= '</table>';
        $html .= '</div>';

        if ($events !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Stopwatch Events</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-reltime">Time</th>';
            $html .= '<th>Event</th>';
            $html .= '<th>Category</th>';
            $html .= '<th class="wpd-col-right">Duration</th>';
            $html .= '<th class="wpd-col-right">Memory</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($events as $event) {
                $startMs = (float) $event['start_time'];
                $relTime = $this->esc('+' . number_format(max(0, $startMs), 0) . ' ms');

                $html .= '<tr>';
                $html .= '<td class="wpd-col-reltime wpd-text-dim">' . $relTime . '</td>';
                $html .= '<td>' . $this->esc($event['name']) . '</td>';
                $html .= '<td><span class="wpd-tag">' . $this->esc($event['category']) . '</span></td>';
                $html .= '<td class="wpd-col-right">' . $this->formatMs($event['duration']) . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatBytes($event['memory']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
