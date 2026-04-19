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

#[AsDataCollector(name: 'database', priority: 200)]
final class DatabaseDataCollector extends AbstractDataCollector
{
    private const SLOW_QUERY_THRESHOLD_MS = 100.0;

    /** @var list<array{sql: string, params: list<mixed>, time: float, caller: string, start: float, data: array<string, mixed>}> */
    private array $realtimeQueries = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'database';
    }

    public function getLabel(): string
    {
        return 'Database';
    }

    public function collect(): void
    {
        $queries = $this->collectQueries();
        $totalCount = count($queries);
        $totalTime = 0.0;
        $duplicates = [];
        $slowCount = 0;
        $suggestions = [];

        foreach ($queries as $query) {
            $totalTime += $query['time'];

            // Duplicate detection keys on SQL + bound params so that the same
            // parameterized statement executed with different values is NOT
            // flagged as a duplicate.
            $key = self::dupKey($query['sql'], $query['params']);

            if (!isset($duplicates[$key])) {
                $duplicates[$key] = 0;
            }
            $duplicates[$key]++;

            if ($query['time'] > self::SLOW_QUERY_THRESHOLD_MS) {
                $slowCount++;
            }
        }

        $duplicateCount = 0;
        foreach ($duplicates as $count) {
            if ($count > 1) {
                $duplicateCount += $count - 1;
            }
        }

        if ($duplicateCount > 0) {
            $suggestions[] = sprintf(
                '%d duplicate queries detected. Consider caching or combining queries.',
                $duplicateCount,
            );
        }

        if ($slowCount > 0) {
            $suggestions[] = sprintf(
                '%d slow queries (>%dms) detected. Consider adding indexes or optimizing.',
                $slowCount,
                (int) self::SLOW_QUERY_THRESHOLD_MS,
            );
        }

        if ($totalCount > 50) {
            $suggestions[] = sprintf(
                'High query count (%d). Consider reducing database calls.',
                $totalCount,
            );
        }

        $this->data = [
            'queries' => $queries,
            'total_count' => $totalCount,
            'total_time' => $totalTime,
            'duplicate_count' => $duplicateCount,
            'slow_count' => $slowCount,
            'suggestions' => $suggestions,
        ];
    }

    public function getIndicatorValue(): string
    {
        $totalTimeMs = $this->data['total_time'] ?? 0.0;
        $totalTimeSec = $totalTimeMs / 1000;

        return number_format($totalTimeSec, 2) . ' s';
    }

    public function getIndicatorColor(): string
    {
        $totalTimeMs = $this->data['total_time'] ?? 0.0;
        $totalTimeSec = $totalTimeMs / 1000;

        if ($totalTimeSec >= 1.0) {
            return 'red';
        }

        if ($totalTimeSec >= 0.5) {
            return 'yellow';
        }

        return 'green';
    }

    /**
     * Capture query data in real-time via the log_query_custom_data filter.
     *
     * @param array<string, mixed> $queryData Custom query data
     * @param string               $sql       The SQL query
     * @param float                $time      Query execution time in seconds (converted to ms internally)
     * @param string               $caller    Calling function/method
     * @param float                $start     Start time
     * @return array<string, mixed>
     */
    public function captureQueryData(array $queryData, string $sql, float $time, string $caller, float $start): array
    {
        /** @var list<mixed> $params */
        $params = (isset($queryData['params']) && \is_array($queryData['params']))
            ? array_values($queryData['params'])
            : [];

        $this->realtimeQueries[] = [
            'sql' => $this->maskQueryValues($sql),
            'params' => $params,
            'time' => $time * 1000,
            'caller' => $caller,
            'start' => $start,
            'data' => $queryData,
        ];

        return $queryData;
    }

    public function reset(): void
    {
        parent::reset();
        $this->realtimeQueries = [];
    }

    private function registerHooks(): void
    {
        add_filter('log_query_custom_data', [$this, 'captureQueryData'], 10, 5);
    }

    /**
     * @return list<array{sql: string, params: list<mixed>, time: float, caller: string, start: float, data: array<string, mixed>}>
     */
    private function collectQueries(): array
    {
        // Prefer real-time collected queries
        if ($this->realtimeQueries !== []) {
            return $this->realtimeQueries;
        }

        // Fall back to $wpdb->queries (requires SAVEQUERIES constant)
        global $wpdb;

        if (!isset($wpdb) || !isset($wpdb->queries) || !is_array($wpdb->queries)) {
            return [];
        }

        $queries = [];
        foreach ($wpdb->queries as $query) {
            if (!is_array($query) || count($query) < 3) {
                continue;
            }

            $data = isset($query[4]) && is_array($query[4]) ? $query[4] : [];
            /** @var list<mixed> $params */
            $params = (isset($data['params']) && \is_array($data['params']))
                ? array_values($data['params'])
                : [];

            $queries[] = [
                'sql' => $this->maskQueryValues((string) $query[0]),
                'params' => $params,
                'time' => (float) $query[1] * 1000,
                'caller' => (string) $query[2],
                'start' => isset($query[3]) ? (float) $query[3] : 0.0,
                'data' => $data,
            ];
        }

        return $queries;
    }

    /**
     * Compute the duplicate-detection key for a (sql, params) pair. Same key
     * implies identical statement AND identical bound values.
     *
     * @param list<mixed> $params
     */
    public static function dupKey(string $sql, array $params): string
    {
        return $sql . "\x01" . serialize($params);
    }

    private function maskQueryValues(string $sql): string
    {
        // Strip leading/trailing horizontal whitespace from every line so
        // wpdb's '\t\t\t' per-line indentation doesn't push the display off.
        // Line breaks are preserved — multi-line SQL still renders across
        // multiple lines, just flush to the left edge of the panel.
        return trim((string) preg_replace('/^[ \t]+|[ \t]+$/m', '', $sql));
    }
}
