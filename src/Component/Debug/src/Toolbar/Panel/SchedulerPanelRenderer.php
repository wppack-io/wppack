<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'scheduler')]
final class SchedulerPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'scheduler';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();
        $cronTotal = (int) ($data['cron_total'] ?? 0);
        $cronOverdue = (int) ($data['cron_overdue'] ?? 0);
        $asAvailable = (bool) ($data['action_scheduler_available'] ?? false);
        $asVersion = (string) ($data['action_scheduler_version'] ?? '');
        $asPending = (int) ($data['as_pending'] ?? 0);
        $asFailed = (int) ($data['as_failed'] ?? 0);
        $asComplete = (int) ($data['as_complete'] ?? 0);
        $cronDisabled = (bool) ($data['cron_disabled'] ?? false);
        $alternateCron = (bool) ($data['alternate_cron'] ?? false);
        /** @var list<array<string, mixed>> $cronEvents */
        $cronEvents = $data['cron_events'] ?? [];

        // Summary
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('WP-Cron Events', (string) $cronTotal);
        $html .= $this->renderTableRow('Overdue', (string) $cronOverdue, $cronOverdue > 0 ? 'wpd-text-red' : '');
        $html .= $this->renderTableRow('Action Scheduler', $asAvailable ? 'Available' . ($asVersion !== '' ? ' (v' . $this->esc($asVersion) . ')' : '') : 'Not available');
        if ($asAvailable) {
            $html .= $this->renderTableRow('AS Pending', (string) $asPending, $asPending > 0 ? 'wpd-text-yellow' : '');
            $html .= $this->renderTableRow('AS Failed', (string) $asFailed, $asFailed > 0 ? 'wpd-text-red' : '');
            $html .= $this->renderTableRow('AS Complete', (string) $asComplete);
        }
        $html .= '</table>';
        $html .= '</div>';

        // Configuration
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Configuration</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('DISABLE_WP_CRON', $this->formatValue($cronDisabled));
        $html .= $this->renderTableRow('ALTERNATE_WP_CRON', $this->formatValue($alternateCron));
        $html .= '</table>';
        $html .= '</div>';

        // WP-Cron Events table
        if ($cronEvents !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">WP-Cron Events</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Hook</th>';
            $html .= '<th>Schedule</th>';
            $html .= '<th>Next Run</th>';
            $html .= '<th class="wpd-col-right">Callbacks</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($cronEvents as $event) {
                $isOverdue = (bool) ($event['is_overdue'] ?? false);
                $rowClass = $isOverdue ? 'wpd-row-slow' : '';

                $html .= '<tr class="' . $rowClass . '">';
                $html .= '<td><code>' . $this->esc((string) ($event['hook'] ?? '')) . '</code></td>';
                $html .= '<td><span class="wpd-tag">' . $this->esc((string) ($event['schedule'] ?? '')) . '</span></td>';
                $html .= '<td>' . $this->esc((string) ($event['next_run_relative'] ?? ''));
                if ($isOverdue) {
                    $html .= ' ' . $this->badge('OVERDUE', 'red');
                }
                $html .= '</td>';
                $html .= '<td class="wpd-col-right">' . $this->esc((string) ($event['callbacks'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
