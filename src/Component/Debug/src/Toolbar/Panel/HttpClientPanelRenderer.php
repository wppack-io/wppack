<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

final class HttpClientPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'http_client';
    }

    public function render(array $data): string
    {
        $totalCount = (int) ($data['total_count'] ?? 0);
        $totalTime = (float) ($data['total_time'] ?? 0.0);
        $errorCount = (int) ($data['error_count'] ?? 0);
        $slowCount = (int) ($data['slow_count'] ?? 0);
        /** @var list<array<string, mixed>> $requests */
        $requests = $data['requests'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Requests', (string) $totalCount);
        $html .= $this->renderTableRow('Total Time', $this->formatMs($totalTime));
        $html .= $this->renderTableRow('Errors', (string) $errorCount, $errorCount > 0 ? 'wpd-text-red' : '');
        $html .= $this->renderTableRow('Slow Requests', (string) $slowCount, $slowCount > 0 ? 'wpd-text-yellow' : '');
        $html .= '</table>';
        $html .= '</div>';

        if ($requests !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Requests</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-num">#</th>';
            $html .= '<th class="wpd-col-right">Time</th>';
            $html .= '<th>Method</th>';
            $html .= '<th>URL</th>';
            $html .= '<th>Status</th>';
            $html .= '<th class="wpd-col-right">Duration</th>';
            $html .= '<th class="wpd-col-right">Size</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($requests as $index => $request) {
                $statusCode = (int) ($request['status_code'] ?? 0);
                $statusColor = match (true) {
                    $statusCode >= 200 && $statusCode < 300 => 'wpd-text-green',
                    $statusCode >= 300 && $statusCode < 400 => 'wpd-text-yellow',
                    $statusCode === 0 => 'wpd-text-dim',
                    default => 'wpd-text-red',
                };
                $error = ($request['error'] ?? '') !== '' ? '<br><small class="wpd-text-red">' . $this->esc($request['error']) . '</small>' : '';

                $startTime = (float) ($request['start'] ?? 0);
                $relTime = $this->formatRelativeTime($startTime);

                $html .= '<tr>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td class="wpd-col-reltime wpd-text-dim">' . $relTime . '</td>';
                $html .= '<td><span class="wpd-tag">' . $this->esc($request['method'] ?? 'GET') . '</span></td>';
                $html .= '<td><code>' . $this->esc($request['url'] ?? '') . '</code></td>';
                $html .= '<td class="' . $statusColor . '">' . ($statusCode > 0 ? $this->esc((string) $statusCode) : '-') . $error . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatMs((float) ($request['duration'] ?? 0.0)) . '</td>';
                $html .= '<td class="wpd-col-right">' . $this->formatBytes((int) ($request['response_size'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
