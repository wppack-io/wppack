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

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\DataCollector\DatabaseDataCollector;

#[AsPanelRenderer(name: 'database')]
final class DatabasePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'database';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();
        $queries = $data['queries'] ?? [];

        // Caller grouping — sorted by total time descending
        $callerStats = [];
        foreach ($queries as $query) {
            $caller = $query['caller'];
            $callerStats[$caller] ??= ['count' => 0, 'total_time' => 0.0];
            $callerStats[$caller]['count']++;
            $callerStats[$caller]['total_time'] += $query['time'];
        }
        uasort($callerStats, static fn(array $a, array $b): int => $b['total_time'] <=> $a['total_time']);

        // Short caller extraction
        $shortCallers = [];
        foreach ($callerStats as $caller => $stats) {
            $parts = preg_split('/,\s*/', $caller);
            $shortCallers[$caller] = ($parts !== false && count($parts) > 1) ? end($parts) : $caller;
        }

        // Duplicate counts key on SQL + bound params — the same parameterized
        // statement executed with different values is not a duplicate.
        $sqlCounts = [];
        foreach ($queries as $query) {
            $key = DatabaseDataCollector::dupKey($query['sql'], $query['params'] ?? []);
            $sqlCounts[$key] = ($sqlCounts[$key] ?? 0) + 1;
        }

        return $this->getPhpRenderer()->render('toolbar/panels/database', [
            'totalCount' => (int) ($data['total_count'] ?? 0),
            'totalTime' => (float) ($data['total_time'] ?? 0.0),
            'duplicateCount' => (int) ($data['duplicate_count'] ?? 0),
            'slowCount' => (int) ($data['slow_count'] ?? 0),
            'suggestions' => $data['suggestions'] ?? [],
            'queries' => $queries,
            'callerStats' => $callerStats,
            'shortCallers' => $shortCallers,
            'sqlCounts' => $sqlCounts,
            'fmt' => $this->getFormatters(),
            'requestTimeFloat' => $this->requestTimeFloat,
        ]);
    }
}
