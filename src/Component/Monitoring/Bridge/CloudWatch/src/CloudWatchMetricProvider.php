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

namespace WpPack\Component\Monitoring\Bridge\CloudWatch;

use AsyncAws\CloudWatch\CloudWatchClient;
use AsyncAws\CloudWatch\Input\GetMetricDataInput;
use AsyncAws\CloudWatch\ValueObject\Dimension;
use AsyncAws\CloudWatch\ValueObject\Metric;
use AsyncAws\CloudWatch\ValueObject\MetricDataQuery;
use AsyncAws\CloudWatch\ValueObject\MetricStat;
use WpPack\Component\Monitoring\MetricPoint;
use WpPack\Component\Monitoring\MetricProviderInterface;
use WpPack\Component\Monitoring\MetricResult;
use WpPack\Component\Monitoring\MetricSource;
use WpPack\Component\Monitoring\MetricTimeRange;

class CloudWatchMetricProvider implements MetricProviderInterface
{
    public function __construct(
        private readonly CloudWatchClient $client,
    ) {}

    public function getName(): string
    {
        return 'cloudwatch';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Uses GetMetricData (batch API) — up to 500 queries per call.
     *
     * @param list<MetricSource> $sources
     * @return list<MetricResult>
     */
    public function query(array $sources, MetricTimeRange $range): array
    {
        if ($sources === []) {
            return [];
        }

        $queries = [];
        /** @var array<string, MetricSource> $sourceMap */
        $sourceMap = [];

        foreach ($sources as $i => $source) {
            $queryId = 'q' . $i;
            $sourceMap[$queryId] = $source;

            $dimensions = [];
            foreach ($source->dimensions as $name => $value) {
                $dimensions[] = new Dimension(['Name' => $name, 'Value' => $value]);
            }

            $metricStat = [
                'Metric' => new Metric([
                    'Namespace' => $source->namespace,
                    'MetricName' => $source->metricName,
                    'Dimensions' => $dimensions,
                ]),
                'Period' => $source->periodSeconds,
                'Stat' => $source->stat,
            ];

            if ($source->unit !== '') {
                $metricStat['Unit'] = $source->unit;
            }

            $queries[] = new MetricDataQuery([
                'Id' => $queryId,
                'MetricStat' => new MetricStat($metricStat),
            ]);
        }

        $response = $this->client->getMetricData(new GetMetricDataInput([
            'MetricDataQueries' => $queries,
            'StartTime' => $range->start,
            'EndTime' => $range->end,
        ]));

        $results = [];
        $now = new \DateTimeImmutable();

        foreach ($response->getMetricDataResults() as $data) {
            $queryId = $data->getId();
            $source = $sourceMap[$queryId] ?? null;

            if ($source === null) {
                continue;
            }

            $datapoints = [];
            $timestamps = $data->getTimestamps();
            $values = $data->getValues();

            foreach ($timestamps as $j => $ts) {
                $datapoints[] = new MetricPoint(
                    timestamp: \DateTimeImmutable::createFromInterface($ts),
                    value: $values[$j] ?? 0.0,
                    stat: $source->stat,
                );
            }

            usort($datapoints, static fn(MetricPoint $a, MetricPoint $b): int => $a->timestamp <=> $b->timestamp);

            $results[] = new MetricResult(
                sourceId: $source->id,
                label: $source->label,
                unit: $source->unit,
                group: $source->group,
                datapoints: $datapoints,
                fetchedAt: $now,
            );
        }

        return $results;
    }
}
