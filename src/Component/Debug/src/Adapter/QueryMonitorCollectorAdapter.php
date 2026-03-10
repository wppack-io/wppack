<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Adapter;

use WpPack\Component\Debug\Attribute\AsDataCollector;
use WpPack\Component\Debug\DataCollector\AbstractDataCollector;

#[AsDataCollector(name: 'query_monitor', priority: -100)]
final class QueryMonitorCollectorAdapter extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'query_monitor';
    }

    public function getLabel(): string
    {
        return 'Query Monitor';
    }

    public function collect(): void
    {
        // Guard: only run if Query Monitor plugin is active
        if (!class_exists('QM_Collector')) {
            return;
        }

        if (!function_exists('apply_filters')) {
            return;
        }

        /** @var array<string, object> $collectors */
        $collectors = apply_filters('qm/collectors', []);

        $collectorData = [];
        foreach ($collectors as $id => $collector) {
            if (!method_exists($collector, 'get_data')) {
                continue;
            }

            $data = $collector->get_data();
            $collectorData[$id] = [
                'id' => $id,
                'data' => is_array($data) ? $data : (is_object($data) ? (array) $data : []),
            ];
        }

        $this->data = [
            'collectors' => $collectorData,
            'collector_count' => count($collectorData),
        ];
    }

    public function getBadgeValue(): string
    {
        $count = $this->data['collector_count'] ?? 0;

        return $count > 0 ? (string) $count : '';
    }
}
