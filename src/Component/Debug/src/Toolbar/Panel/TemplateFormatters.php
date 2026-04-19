<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Debug\Toolbar\Panel;

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
     * Map HTTP method to badge color key.
     */
    public function methodColor(string $method): string
    {
        return match ($method) {
            'GET' => 'green',
            'POST' => 'primary',
            'PUT', 'PATCH' => 'yellow',
            'DELETE' => 'red',
            default => 'gray',
        };
    }

    /**
     * Map HTTP status code to text color CSS class.
     */
    public function statusColor(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 200 && $statusCode < 300 => 'wpd-text-green',
            $statusCode >= 300 && $statusCode < 400 => 'wpd-text-yellow',
            $statusCode === 0 => 'wpd-text-dim',
            default => 'wpd-text-red',
        };
    }

    /**
     * Format a float as percentage string.
     *
     * @return string e.g. "82.3%"
     */
    public function percentage(float $value): string
    {
        return sprintf('%.1f%%', $value);
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

    /**
     * Describe a bound-parameter value's type as a short string suitable for a
     * debug panel column, e.g. 'int', 'string(7)', 'object:DateTime'.
     */
    public function paramType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            \is_bool($value) => 'bool',
            \is_int($value) => 'int',
            \is_float($value) => 'float',
            \is_string($value) => 'string(' . \strlen($value) . ')',
            \is_array($value) => 'array(' . \count($value) . ')',
            \is_object($value) => 'object:' . $value::class,
            \is_resource($value) => 'resource',
            default => \gettype($value),
        };
    }

    /**
     * Format a bound-parameter value for display next to its type. Long
     * strings are truncated with a trailing ellipsis; non-scalars fall back
     * to a short JSON representation.
     */
    public function paramValue(mixed $value, int $maxLength = 80): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        if (\is_string($value)) {
            $quoted = "'" . $value . "'";

            if (\strlen($quoted) > $maxLength) {
                return substr($quoted, 0, $maxLength - 4) . "...'";
            }

            return $quoted;
        }

        if (\is_array($value) || \is_object($value)) {
            $json = json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

            if (!\is_string($json)) {
                return \is_object($value) ? $value::class : 'array';
            }

            if (\strlen($json) > $maxLength) {
                return substr($json, 0, $maxLength - 3) . '...';
            }

            return $json;
        }

        return \gettype($value);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
