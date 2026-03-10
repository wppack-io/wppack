<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'database', priority: 90)]
final class DatabaseDataCollector extends AbstractDataCollector
{
    private const SLOW_QUERY_THRESHOLD_MS = 100.0;

    /** @var list<array{sql: string, time: float, caller: string, start: float, data: array<string, mixed>}> */
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

            $sql = $query['sql'];
            if (!isset($duplicates[$sql])) {
                $duplicates[$sql] = 0;
            }
            $duplicates[$sql]++;

            if ($query['time'] * 1000 > self::SLOW_QUERY_THRESHOLD_MS) {
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

    public function getBadgeValue(): string
    {
        return (string) ($this->data['total_count'] ?? 0);
    }

    public function getBadgeColor(): string
    {
        $count = $this->data['total_count'] ?? 0;

        return match (true) {
            $count < 20 => 'green',
            $count < 50 => 'yellow',
            default => 'red',
        };
    }

    /**
     * Capture query data in real-time via the log_query_custom_data filter.
     *
     * @param array<string, mixed> $queryData Custom query data
     * @param string               $sql       The SQL query
     * @param float                $time      Query execution time in seconds
     * @param string               $caller    Calling function/method
     * @param float                $start     Start time
     * @return array<string, mixed>
     */
    public function captureQueryData(array $queryData, string $sql, float $time, string $caller, float $start): array
    {
        $this->realtimeQueries[] = [
            'sql' => $sql,
            'time' => $time,
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
        if (function_exists('add_filter')) {
            add_filter('log_query_custom_data', [$this, 'captureQueryData'], 10, 5);
        }
    }

    /**
     * @return list<array{sql: string, time: float, caller: string, start: float, data: array<string, mixed>}>
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

            $queries[] = [
                'sql' => (string) $query[0],
                'time' => (float) $query[1],
                'caller' => (string) $query[2],
                'start' => isset($query[3]) ? (float) $query[3] : 0.0,
                'data' => isset($query[4]) && is_array($query[4]) ? $query[4] : [],
            ];
        }

        return $queries;
    }
}
