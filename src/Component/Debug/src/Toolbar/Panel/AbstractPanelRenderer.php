<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

abstract class AbstractPanelRenderer
{
    /** @var array<string, array{bg: string, fg: string}> */
    private const BADGE_COLORS = [
        'green' => ['bg' => 'rgba(0,138,32,0.12)', 'fg' => '#008a20'],
        'yellow' => ['bg' => 'rgba(153,104,0,0.12)', 'fg' => '#996800'],
        'red' => ['bg' => 'rgba(204,24,24,0.12)', 'fg' => '#cc1818'],
        'default' => ['bg' => 'transparent', 'fg' => '#50575e'],
    ];

    protected float $requestTimeFloat = 0.0;

    public function setRequestTimeFloat(float $requestTimeFloat): void
    {
        $this->requestTimeFloat = $requestTimeFloat;
    }

    protected function renderTableRow(string $key, string $value, string $valueClass = ''): string
    {
        $classAttr = $valueClass !== '' ? ' class="wpd-kv-val ' . $this->esc($valueClass) . '"' : ' class="wpd-kv-val"';

        return '<tr><td class="wpd-kv-key">' . $this->esc($key) . '</td><td' . $classAttr . '>' . $value . '</td></tr>';
    }

    /**
     * @param array<string, mixed> $items
     */
    protected function renderKeyValueSection(string $title, array $items): string
    {
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">' . $this->esc($title) . '</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';

        foreach ($items as $key => $value) {
            $html .= $this->renderTableRow($key, $this->formatValue($value));
        }

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    protected function renderPerfCard(string $label, string $value, string $unit, string $sub): string
    {
        $html = '<div class="wpd-perf-card">';
        $html .= '<div class="wpd-perf-card-value">' . $value;
        if ($unit !== '') {
            $html .= '<span class="wpd-perf-card-unit">' . $this->esc($unit) . '</span>';
        }
        $html .= '</div>';
        $html .= '<div class="wpd-perf-card-label">' . $this->esc($label) . '</div>';
        if ($sub !== '') {
            $html .= '<div class="wpd-perf-card-sub">' . $sub . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @return array{string, string}
     */
    protected function formatMsCard(float $ms): array
    {
        if ($ms >= 1000) {
            return [sprintf('%.2f', $ms / 1000), 's'];
        }

        return [sprintf('%.1f', $ms), 'ms'];
    }

    /**
     * @return array{string, string}
     */
    protected function formatBytesCard(int $bytes): array
    {
        if ($bytes === 0) {
            return ['0', 'B'];
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return [sprintf('%.1f', round($value, 1)), $units[$power]];
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function renderTimelineRow(array $entry, string $color, float $totalTime): string
    {
        $html = '<div class="wpd-perf-wf-row">';
        $html .= '<div class="wpd-perf-wf-label">' . $this->esc((string) ($entry['name'] ?? '')) . '</div>';
        $html .= '<div class="wpd-perf-wf-track">';

        /** @var non-empty-list<array{start: float, duration: float, title?: string}>|null $bars */
        $bars = $entry['bars'] ?? null;
        if ($bars !== null) {
            foreach ($bars as $bar) {
                $left = $totalTime > 0 ? ($bar['start'] / $totalTime) * 100 : 0;
                $width = $totalTime > 0 ? ($bar['duration'] / $totalTime) * 100 : 0;
                $width = max($width, 0.3);
                $tooltipAttr = isset($bar['title']) ? ' data-tooltip="' . $this->esc($bar['title']) . '"' : '';
                $html .= '<div class="wpd-perf-wf-bar"' . $tooltipAttr . ' style="left:' . $this->esc(sprintf('%.2f', $left)) . '%;width:' . $this->esc(sprintf('%.2f', $width)) . '%;background:' . $this->esc($color) . '"></div>';
            }
        } else {
            $left = $totalTime > 0 ? ((float) ($entry['start'] ?? 0.0) / $totalTime) * 100 : 0;
            $width = $totalTime > 0 ? ((float) ($entry['duration'] ?? 0.0) / $totalTime) * 100 : 0;
            $width = max($width, 0.3);
            $barTitle = (string) ($entry['title'] ?? '');
            $tooltipAttr = $barTitle !== '' ? ' data-tooltip="' . $this->esc($barTitle) . '"' : '';
            $html .= '<div class="wpd-perf-wf-bar"' . $tooltipAttr . ' style="left:' . $this->esc(sprintf('%.2f', $left)) . '%;width:' . $this->esc(sprintf('%.2f', $width)) . '%;background:' . $this->esc($color) . '"></div>';
        }

        $html .= '</div>';
        $displayValue = (float) ($entry['value'] ?? $entry['duration'] ?? 0.0);
        $html .= '<div class="wpd-perf-wf-value">' . $this->formatMs($displayValue) . '</div>';
        $html .= '</div>';

        return $html;
    }

    protected function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value
                ? '<span class="wpd-text-green">true</span>'
                : '<span class="wpd-text-red">false</span>';
        }

        if ($value === null) {
            return '<span class="wpd-text-dim">null</span>';
        }

        if (is_array($value)) {
            return '<code>' . $this->esc(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]') . '</code>';
        }

        return $this->esc((string) $value);
    }

    protected function formatMs(float $ms): string
    {
        if ($ms >= 1000) {
            return $this->esc(sprintf('%.2f s', $ms / 1000));
        }

        return $this->esc(sprintf('%.1f ms', $ms));
    }

    protected function formatRelativeTime(float $absoluteTimestamp): string
    {
        if ($absoluteTimestamp <= 0 || $this->requestTimeFloat <= 0) {
            return '';
        }

        $relativeMs = ($absoluteTimestamp - $this->requestTimeFloat) * 1000;

        return $this->esc('+' . number_format(max(0, $relativeMs), 0) . ' ms');
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return $this->esc(sprintf('%.1f %s', round($value, 1), $units[$power]));
    }

    protected function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @return array<string, string>
     */
    protected static function getBadgeColors(): array
    {
        return self::BADGE_COLORS;
    }
}
