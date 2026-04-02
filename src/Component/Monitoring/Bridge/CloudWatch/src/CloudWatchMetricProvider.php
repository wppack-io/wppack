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
use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MetricPoint;
use WpPack\Component\Monitoring\MetricProviderInterface;
use WpPack\Component\Monitoring\MetricResult;
use WpPack\Component\Monitoring\MetricTimeRange;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\AwsProviderSettings;

final class CloudWatchMetricProvider implements MetricProviderInterface
{
    /** @var array<string, CloudWatchClient> */
    private array $clients = [];

    public function getName(): string
    {
        return 'cloudwatch';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @return list<MetricResult>
     */
    public function query(MonitoringProvider $provider, MetricTimeRange $range): array
    {
        if ($provider->metrics === [] || !$provider->settings instanceof AwsProviderSettings) {
            return [];
        }

        $client = $this->getClient($provider->settings);

        return $this->queryMetrics($client, $provider, $range);
    }

    private function getClient(AwsProviderSettings $settings): CloudWatchClient
    {
        $config = ['region' => $settings->region !== '' ? $settings->region : 'us-east-1'];

        if ($settings->accessKeyId !== '' && $settings->secretAccessKey !== '') {
            $config['accessKeyId'] = $settings->accessKeyId;
            $config['accessKeySecret'] = $settings->secretAccessKey;
        }

        $key = $config['region'] . ':' . ($settings->accessKeyId !== '' ? $settings->accessKeyId : 'iam');

        return $this->clients[$key] ??= new CloudWatchClient($config);
    }

    /**
     * @return list<MetricResult>
     */
    private function queryMetrics(CloudWatchClient $client, MonitoringProvider $provider, MetricTimeRange $range): array
    {
        $queries = [];
        /** @var array<string, MetricDefinition> $metricMap */
        $metricMap = [];

        $rangeSeconds = $range->end->getTimestamp() - $range->start->getTimestamp();

        foreach ($provider->metrics as $i => $metric) {
            $queryId = 'q' . $i;
            $metricMap[$queryId] = $metric;

            $dimensions = [];
            foreach ($metric->dimensions as $name => $value) {
                $dimensions[] = new Dimension(['Name' => $name, 'Value' => $value]);
            }

            $metricStat = [
                'Metric' => new Metric([
                    'Namespace' => $metric->namespace,
                    'MetricName' => $metric->metricName,
                    'Dimensions' => $dimensions,
                ]),
                'Period' => $this->resolvePeriod($metric->periodSeconds, $rangeSeconds),
                'Stat' => $metric->stat,
            ];

            if ($metric->unit !== '') {
                $metricStat['Unit'] = $metric->unit;
            }

            $queries[] = new MetricDataQuery([
                'Id' => $queryId,
                'MetricStat' => new MetricStat($metricStat),
            ]);
        }

        $response = $client->getMetricData(new GetMetricDataInput([
            'MetricDataQueries' => $queries,
            'StartTime' => $range->start,
            'EndTime' => $range->end,
        ]));

        $results = [];
        $now = new \DateTimeImmutable();

        foreach ($response->getMetricDataResults() as $data) {
            $queryId = $data->getId();
            $metric = $metricMap[$queryId] ?? null;

            if ($metric === null) {
                continue;
            }

            $datapoints = [];
            $timestamps = $data->getTimestamps();
            $values = $data->getValues();

            foreach ($timestamps as $j => $ts) {
                $datapoints[] = new MetricPoint(
                    timestamp: \DateTimeImmutable::createFromInterface($ts),
                    value: $values[$j] ?? 0.0,
                    stat: $metric->stat,
                );
            }

            usort($datapoints, static fn(MetricPoint $a, MetricPoint $b): int => $a->timestamp <=> $b->timestamp);

            $results[] = new MetricResult(
                sourceId: $metric->id,
                label: $metric->label,
                unit: $metric->unit,
                group: $provider->id,
                datapoints: $datapoints,
                fetchedAt: $now,
            );
        }

        return $results;
    }

    /**
     * Adjust the metric period based on the requested time range
     * to keep data point counts reasonable.
     */
    private function resolvePeriod(int $metricPeriod, int $rangeSeconds): int
    {
        $minPeriod = match (true) {
            $rangeSeconds <= 21_600 => 60,      // ≤ 6h: metric default
            $rangeSeconds <= 86_400 => 300,      // ≤ 1d: 5 min
            $rangeSeconds <= 259_200 => 900,     // ≤ 3d: 15 min
            default => 3600,                     // > 3d: 1 hour
        };

        return max($metricPeriod, $minPeriod);
    }
}
