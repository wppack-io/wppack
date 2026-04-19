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

namespace WPPack\Component\Debug\DataCollector;

use WPPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'dump', priority: 95)]
final class DumpDataCollector extends AbstractDataCollector
{
    /** @var list<array{data: string, file: string, line: int, timestamp: float}> */
    private array $dumps = [];

    public function getName(): string
    {
        return 'dump';
    }

    public function getLabel(): string
    {
        return 'Dump';
    }

    /**
     * Capture a variable dump.
     */
    public function capture(mixed ...$vars): void
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        // Find the calling frame (skip this method and any wrapper function)
        $frame = $backtrace[1] ?? $backtrace[0];

        $output = '';
        foreach ($vars as $var) {
            $output .= $this->formatVar($var);
        }

        $this->dumps[] = [
            'data' => $output,
            'file' => $frame['file'] ?? 'unknown',
            'line' => $frame['line'] ?? 0,
            'timestamp' => microtime(true),
        ];
    }

    public function collect(): void
    {
        $this->data = [
            'dumps' => $this->dumps,
            'total_count' => count($this->dumps),
        ];
    }

    public function getIndicatorValue(): string
    {
        $totalCount = $this->data['total_count'] ?? 0;

        return $totalCount > 0 ? (string) $totalCount : '';
    }

    public function getIndicatorColor(): string
    {
        $totalCount = $this->data['total_count'] ?? 0;

        return $totalCount > 0 ? 'yellow' : 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->dumps = [];
    }

    private function formatVar(mixed $var): string
    {
        if ($var === null) {
            return 'null';
        }

        if (is_bool($var)) {
            return $var ? 'true' : 'false';
        }

        if (is_string($var)) {
            return sprintf('"%s"', strlen($var) > 500 ? substr($var, 0, 500) . "\u{2026}" : $var);
        }

        if (is_int($var) || is_float($var)) {
            return (string) $var;
        }

        $output = print_r($var, true);
        if (strlen($output) > 10000) {
            $output = substr($output, 0, 10000) . "\n... (truncated)";
        }

        return $output;
    }
}
