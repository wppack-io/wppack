<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

/**
 * Pure formatting utilities for use in Debug templates.
 *
 * Injected as `$fmt` into templates. Methods return plain strings (no HTML)
 * unless noted otherwise — templates are responsible for escaping.
 */
final class TemplateFormatters
{
    /**
     * Format milliseconds as human-readable string.
     *
     * @return string e.g. "123.4 ms" or "1.23 s"
     */
    public function ms(float $ms): string
    {
        if ($ms >= 1000) {
            return sprintf('%.2f s', $ms / 1000);
        }

        return sprintf('%.1f ms', $ms);
    }

    /**
     * Format bytes as human-readable string.
     *
     * @return string e.g. "12.5 MB"
     */
    public function bytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return sprintf('%.1f %s', round($value, 1), $units[$power]);
    }

    /**
     * Format an absolute timestamp as relative time from request start.
     *
     * @return string e.g. "+123 ms" or "" if invalid
     */
    public function relativeTime(float $absoluteTimestamp, float $requestTimeFloat): string
    {
        if ($absoluteTimestamp <= 0 || $requestTimeFloat <= 0) {
            return '';
        }

        $relativeMs = ($absoluteTimestamp - $requestTimeFloat) * 1000;

        return '+' . number_format(max(0, $relativeMs), 0) . ' ms';
    }

    /**
     * Format milliseconds as [value, unit] pair for perf cards.
     *
     * @return array{string, string} e.g. ["123.4", "ms"] or ["1.23", "s"]
     */
    public function msCard(float $ms): array
    {
        if ($ms >= 1000) {
            return [sprintf('%.2f', $ms / 1000), 's'];
        }

        return [sprintf('%.1f', $ms), 'ms'];
    }

    /**
     * Format bytes as [value, unit] pair for perf cards.
     *
     * @return array{string, string} e.g. ["12.5", "MB"]
     */
    public function bytesCard(int $bytes): array
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
     * Format a mixed value as HTML string (bool/null/array/string).
     *
     * Note: This returns raw HTML — use $this->raw() in templates.
     */
    public function value(mixed $value): string
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

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
