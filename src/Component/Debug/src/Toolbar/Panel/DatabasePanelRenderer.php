<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

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

        // SQL duplicate counts
        $sqlCounts = [];
        foreach ($queries as $query) {
            $sql = $query['sql'];
            $sqlCounts[$sql] = ($sqlCounts[$sql] ?? 0) + 1;
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
