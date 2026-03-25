<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'memory', priority: 245)]
final class MemoryDataCollector extends AbstractDataCollector
{
    /** @var array<string, int> */
    private array $snapshots = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'memory';
    }

    public function getLabel(): string
    {
        return 'Memory';
    }

    public function takeSnapshot(string $label): void
    {
        $this->snapshots[$label] = memory_get_usage(true);
    }

    public function collect(): void
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->getMemoryLimit();
        $usagePercentage = $limit > 0 ? ($peak / $limit) * 100 : 0.0;

        $this->data = [
            'current' => $current,
            'peak' => $peak,
            'limit' => $limit,
            'usage_percentage' => round($usagePercentage, 2),
            'snapshots' => $this->snapshots,
        ];
    }

    public function getIndicatorValue(): string
    {
        $peak = $this->data['peak'] ?? 0;

        return $this->formatBytes($peak);
    }

    public function getIndicatorColor(): string
    {
        $usagePercentage = $this->data['usage_percentage'] ?? 0.0;

        if ($usagePercentage >= 90.0) {
            return 'red';
        }

        if ($usagePercentage >= 70.0) {
            return 'yellow';
        }

        return 'green';
    }

    public function reset(): void
    {
        parent::reset();
        $this->snapshots = [];
    }

    /**
     * Hook callback for wp_loaded.
     */
    public function onWpLoaded(): void
    {
        $this->takeSnapshot('wp_loaded');
    }

    /**
     * Hook callback for template_redirect.
     */
    public function onTemplateRedirect(): void
    {
        $this->takeSnapshot('template_redirect');
    }

    /**
     * Hook callback for wp_footer.
     */
    public function onWpFooter(): void
    {
        $this->takeSnapshot('wp_footer');
    }

    /**
     * Hook callback for shutdown.
     */
    public function onShutdown(): void
    {
        $this->takeSnapshot('shutdown');
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $value = $bytes / (1024 ** $power);

        return sprintf('%s %s', round($value, 1), $units[$power]);
    }

    private function registerHooks(): void
    {
        add_action('wp_loaded', [$this, 'onWpLoaded'], PHP_INT_MAX);
        add_action('template_redirect', [$this, 'onTemplateRedirect'], PHP_INT_MAX);
        add_action('wp_footer', [$this, 'onWpFooter'], PHP_INT_MAX);
        add_action('shutdown', [$this, 'onShutdown'], PHP_INT_MAX);
    }

    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit === '' || $limit === '-1') {
            return 0;
        }

        return $this->parseMemoryValue($limit);
    }

    private function parseMemoryValue(string $value): int
    {
        $value = trim($value);
        $last = strtolower(substr($value, -1));
        $numericValue = (int) $value;

        return match ($last) {
            'g' => $numericValue * 1024 * 1024 * 1024,
            'm' => $numericValue * 1024 * 1024,
            'k' => $numericValue * 1024,
            default => $numericValue,
        };
    }
}
