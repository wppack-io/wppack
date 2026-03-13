<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'logger')]
final class LoggerPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'logger';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        $totalCount = (int) ($data['total_count'] ?? 0);
        $errorCount = (int) ($data['error_count'] ?? 0);
        $deprecationCount = (int) ($data['deprecation_count'] ?? 0);
        /** @var array<string, int> $levelCounts */
        $levelCounts = $data['level_counts'] ?? [];
        /** @var list<array<string, mixed>> $logs */
        $logs = $data['logs'] ?? [];

        $warningCount = (int) ($levelCounts['warning'] ?? 0);
        /** @var array<string, int> $channelCounts */
        $channelCounts = $data['channel_counts'] ?? [];

        // Summary section
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Entries', (string) $totalCount);
        $html .= $this->renderTableRow('Errors', (string) $errorCount, $errorCount > 0 ? 'wpd-text-red' : '');
        $html .= $this->renderTableRow('Deprecations', (string) $deprecationCount, $deprecationCount > 0 ? 'wpd-text-orange' : '');
        $html .= $this->renderTableRow('Warnings', (string) $warningCount, $warningCount > 0 ? 'wpd-text-yellow' : '');
        $html .= '</table>';
        $html .= '</div>';

        if ($channelCounts !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Channels</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($channelCounts as $ch => $count) {
                $html .= '<span class="wpd-tag">' . $this->esc($ch) . ' (' . $count . ')</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        if ($logs !== []) {
            // Count entries per tab
            $errorTabCount = 0;
            $deprecationTabCount = 0;
            $warningTabCount = 0;
            $noticeTabCount = 0;
            $infoTabCount = 0;
            $debugTabCount = 0;
            foreach ($logs as $log) {
                $lvl = $log['level'] ?? 'debug';
                if (in_array($lvl, ['emergency', 'alert', 'critical', 'error'], true)) {
                    $errorTabCount++;
                } elseif ($lvl === 'deprecation') {
                    $deprecationTabCount++;
                } elseif ($lvl === 'warning') {
                    $warningTabCount++;
                } elseif ($lvl === 'notice') {
                    $noticeTabCount++;
                } elseif ($lvl === 'info') {
                    $infoTabCount++;
                } else {
                    $debugTabCount++;
                }
            }

            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Log Entries</h4>';

            // Filter tabs
            $html .= '<div class="wpd-log-tabs">';
            $html .= '<button class="wpd-log-tab wpd-active" data-log-filter="all">All (' . $this->esc((string) count($logs)) . ')</button>';
            $html .= $this->renderLogTab('error', 'Errors', $errorTabCount);
            $html .= $this->renderLogTab('warning', 'Warnings', $warningTabCount);
            $html .= $this->renderLogTab('notice', 'Notices', $noticeTabCount);
            $html .= $this->renderLogTab('info', 'Info', $infoTabCount);
            $html .= $this->renderLogTab('debug', 'Debug', $debugTabCount);
            $html .= $this->renderLogTab('deprecation', 'Deprecations', $deprecationTabCount);
            $html .= '</div>';

            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-num">#</th>';
            $html .= '<th class="wpd-col-reltime">Time</th>';
            $html .= '<th>Level</th>';
            $html .= '<th>Channel</th>';
            $html .= '<th>Message</th>';
            $html .= '<th>File</th>';
            $html .= '<th></th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($logs as $index => $log) {
                $level = $log['level'] ?? 'debug';
                $levelColor = match ($level) {
                    'emergency' => 'wpd-log-critical',
                    'alert' => 'wpd-log-critical',
                    'critical' => 'wpd-log-critical',
                    'error' => 'wpd-log-error',
                    'warning' => 'wpd-log-warning',
                    'notice' => 'wpd-log-notice',
                    'info' => 'wpd-log-info',
                    'debug' => 'wpd-log-debug',
                    'deprecation' => 'wpd-log-deprecation',
                    default => 'wpd-log-debug',
                };
                $file = (string) ($log['file'] ?? '');
                $line = (int) ($log['line'] ?? 0);
                $fileDisplay = '';
                if ($file !== '') {
                    $basename = basename($file);
                    $fileDisplay = $line > 0 ? $basename . ':' . $line : $basename;
                }

                $timestamp = (float) ($log['timestamp'] ?? 0);
                $timeDisplay = $this->formatRelativeTime($timestamp);

                $context = $log['context'] ?? [];
                $hasContext = is_array($context) && $context !== [];
                $rowClass = $hasContext ? ' class="wpd-log-toggle"' : '';

                $toggleIcon = $hasContext ? '<span class="wpd-log-indicator">+</span>' : '';
                $html .= '<tr data-log-level="' . $this->esc($level) . '"' . $rowClass . '>';
                $html .= '<td class="wpd-col-num">' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td class="wpd-col-reltime wpd-text-dim">' . $this->esc($timeDisplay) . '</td>';
                $html .= '<td><span class="wpd-tag ' . $levelColor . '">' . $this->esc($level) . '</span></td>';
                $html .= '<td><span class="wpd-tag">' . $this->esc($log['channel'] ?? 'app') . '</span></td>';
                $html .= '<td><code>' . $this->esc($log['message'] ?? '') . '</code></td>';
                $html .= '<td title="' . $this->esc($file) . '">' . $this->esc($fileDisplay) . '</td>';
                $html .= '<td class="wpd-col-toggle">' . $toggleIcon . '</td>';
                $html .= '</tr>';

                if ($hasContext) {
                    $html .= '<tr class="wpd-log-context" style="display:none" data-log-level="' . $this->esc($level) . '">';
                    $html .= '<td colspan="7"><pre>' . $this->esc(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') . '</pre></td>';
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    private function renderLogTab(string $filter, string $label, int $count): string
    {
        $disabled = $count === 0 ? ' disabled' : '';

        return '<button class="wpd-log-tab" data-log-filter="' . $this->esc($filter) . '"' . $disabled . '>'
            . $this->esc($label) . ' (' . $this->esc((string) $count) . ')</button>';
    }
}
