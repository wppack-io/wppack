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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery;

use WPPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringProviderInterface;

/**
 * Auto-discover Aurora DSQL clusters from DATABASE_DSN or DB_HOST.
 *
 * Detects DSQL endpoints ({cluster-id}.dsql.{region}.on.aws) and
 * registers CloudWatch metrics for DPU (Distributed Processing Units).
 */
class DsqlDiscovery implements MonitoringProviderInterface
{
    /**
     * DSQL endpoint pattern: {cluster-id}.dsql.{region}.on.aws
     */
    private const DSQL_ENDPOINT_PATTERN = '/^([a-z0-9]+)\.dsql\.([a-z0-9-]+)\.on\.aws$/';

    public function getProviders(): array
    {
        $endpoint = $this->parseEndpoint();

        if ($endpoint === null) {
            return [];
        }

        return [
            new MonitoringProvider(
                id: 'dsql',
                label: 'Aurora DSQL',
                bridge: 'cloudwatch',
                settings: new AwsProviderSettings(region: $endpoint['region']),
                metrics: $this->buildMetrics($endpoint['dimensions']),
                locked: true,
            ),
        ];
    }

    /**
     * @return array{region: string, dimensions: array<string, string>}|null
     */
    private function parseEndpoint(): ?array
    {
        // Check DATABASE_DSN first (dsql:// scheme)
        if (\defined('DATABASE_DSN')) {
            $dsn = (string) \constant('DATABASE_DSN');

            if (str_starts_with($dsn, 'dsql://')) {
                return $this->parseDsqlDsn($dsn);
            }
        }

        // Fall back to DB_HOST
        if (\defined('DB_HOST')) {
            $host = (string) \constant('DB_HOST');

            // Strip port
            if (str_contains($host, ':') && !str_contains($host, '[')) {
                $host = explode(':', $host, 2)[0];
            }

            return $this->parseDsqlHost($host);
        }

        return null;
    }

    /**
     * @return array{region: string, dimensions: array<string, string>}|null
     */
    private function parseDsqlDsn(string $dsn): ?array
    {
        $parts = parse_url($dsn);

        if ($parts === false || !isset($parts['host'])) {
            return null;
        }

        return $this->parseDsqlHost($parts['host']);
    }

    /**
     * @return array{region: string, dimensions: array<string, string>}|null
     */
    private function parseDsqlHost(string $host): ?array
    {
        if (preg_match(self::DSQL_ENDPOINT_PATTERN, $host, $matches) !== 1) {
            return null;
        }

        return [
            'region' => $matches[2],
            'dimensions' => ['ClusterIdentifier' => $matches[1]],
        ];
    }

    /**
     * @param array<string, string> $dimensions
     *
     * @return list<MetricDefinition>
     */
    private function buildMetrics(array $dimensions): array
    {
        return [
            new MetricDefinition(
                id: 'dsql.read_dpu',
                label: 'Read DPU',
                description: 'Distributed Processing Units consumed by read operations',
                namespace: 'AWS/DSQL',
                metricName: 'ReadDPU',
                unit: 'Count',
                stat: 'Sum',
                dimensions: $dimensions,
                locked: true,
            ),
            new MetricDefinition(
                id: 'dsql.write_dpu',
                label: 'Write DPU',
                description: 'Distributed Processing Units consumed by write operations',
                namespace: 'AWS/DSQL',
                metricName: 'WriteDPU',
                unit: 'Count',
                stat: 'Sum',
                dimensions: $dimensions,
                locked: true,
            ),
            new MetricDefinition(
                id: 'dsql.compute_dpu',
                label: 'Compute DPU',
                description: 'Distributed Processing Units consumed by compute operations',
                namespace: 'AWS/DSQL',
                metricName: 'ComputeDPU',
                unit: 'Count',
                stat: 'Sum',
                dimensions: $dimensions,
                locked: true,
            ),
            new MetricDefinition(
                id: 'dsql.total_dpu',
                label: 'Total DPU',
                description: 'Total Distributed Processing Units consumed',
                namespace: 'AWS/DSQL',
                metricName: 'TotalDPU',
                unit: 'Count',
                stat: 'Sum',
                dimensions: $dimensions,
                locked: true,
            ),
        ];
    }
}
