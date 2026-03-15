<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Compat\EscapeFunctions;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Templating\PhpRenderer;

abstract class AbstractPanelRenderer
{
    /** @var array<string, array{bg: string, fg: string}> */
    private const INDICATOR_COLORS = [
        'green' => ['bg' => 'var(--wpd-green-a12)', 'fg' => 'var(--wpd-green)'],
        'yellow' => ['bg' => 'var(--wpd-yellow-a12)', 'fg' => 'var(--wpd-yellow)'],
        'red' => ['bg' => 'var(--wpd-red-a12)', 'fg' => 'var(--wpd-red)'],
        'default' => ['bg' => 'transparent', 'fg' => 'var(--wpd-gray-800)'],
    ];

    protected float $requestTimeFloat = 0.0;

    private ?PhpRenderer $lazyPhpRenderer = null;
    private ?TemplateFormatters $lazyFormatters = null;

    public function __construct(
        protected readonly Profile $profile,
        private readonly ?PhpRenderer $phpRenderer = null,
        private readonly ?TemplateFormatters $templateFormatters = null,
    ) {}

    abstract public function getName(): string;

    public function renderIndicator(): string
    {
        try {
            $collector = $this->profile->getCollector($this->getName());
        } catch (\Throwable) {
            return '';
        }

        return $this->getPhpRenderer()->render('toolbar/indicators/default', [
            'name' => $collector->getName(),
            'label' => $collector->getLabel(),
            'value' => $collector->getIndicatorValue(),
            'colorKey' => $collector->getIndicatorColor(),
            'colors' => self::INDICATOR_COLORS[$collector->getIndicatorColor()] ?? self::INDICATOR_COLORS['default'],
            'icon' => ToolbarIcons::svg($collector->getName()),
        ]);
    }

    public function setRequestTimeFloat(float $requestTimeFloat): void
    {
        $this->requestTimeFloat = $requestTimeFloat;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCollectorData(?string $name = null): array
    {
        try {
            return $this->profile->getCollector($name ?? $this->getName())->getData();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function getPhpRenderer(): PhpRenderer
    {
        if ($this->phpRenderer !== null) {
            return $this->phpRenderer;
        }

        EscapeFunctions::ensure();

        return $this->lazyPhpRenderer ??= new PhpRenderer([
            dirname(__DIR__, 3) . '/templates',
        ]);
    }

    protected function getFormatters(): TemplateFormatters
    {
        if ($this->templateFormatters !== null) {
            return $this->templateFormatters;
        }

        return $this->lazyFormatters ??= new TemplateFormatters();
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

    protected function formatMs(float $ms): string
    {
        if ($ms >= 1000) {
            return $this->esc(sprintf('%.2f s', $ms / 1000));
        }

        return $this->esc(sprintf('%.1f ms', $ms));
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
     * @return array<string, array{bg: string, fg: string}>
     */
    public static function getIndicatorColors(): array
    {
        return self::INDICATOR_COLORS;
    }
}
