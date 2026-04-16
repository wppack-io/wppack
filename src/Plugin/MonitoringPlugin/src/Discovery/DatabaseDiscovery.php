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

namespace WpPack\Plugin\MonitoringPlugin\Discovery;

use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringProviderInterface;
use WpPack\Component\Monitoring\AwsProviderSettings;

final class DatabaseDiscovery implements MonitoringProviderInterface
{
    /**
     * Aurora Cluster endpoint pattern:
     * {cluster-id}.cluster-{hash}.{region}.rds.amazonaws.com
     */
    private const AURORA_CLUSTER_PATTERN = '/^([^.]+)\.cluster-[^.]+\.([a-z0-9-]+)\.rds\.amazonaws\.com$/';

    /**
     * RDS instance endpoint pattern:
     * {instance-id}.{hash}.{region}.rds.amazonaws.com
     */
    private const RDS_INSTANCE_PATTERN = '/^([^.]+)\.[^.]+\.([a-z0-9-]+)\.rds\.amazonaws\.com$/';

    public function getProviders(): array
    {
        $endpoint = $this->parseEndpoint();

        if ($endpoint === null) {
            return [];
        }

        $engine = $this->detectEngine();
        $metrics = $this->buildMetrics($endpoint['type'], $endpoint['dimensions'], $engine);

        $label = match (true) {
            $endpoint['type'] === 'aurora' && $engine === 'postgres' => 'Aurora PostgreSQL Cluster',
            $endpoint['type'] === 'aurora' => 'Aurora MySQL Cluster',
            default => 'RDS Instance',
        };

        return [
            new MonitoringProvider(
                id: 'rds',
                label: $label,
                bridge: 'cloudwatch',
                settings: new AwsProviderSettings(region: $endpoint['region']),
                metrics: $metrics,
                locked: true,
            ),
        ];
    }

    /**
     * @return array{type: 'aurora'|'rds', region: string, dimensions: array<string, string>}|null
     */
    private function parseEndpoint(): ?array
    {
        if (!\defined('DB_HOST')) {
            return null;
        }

        $host = (string) \constant('DB_HOST');

        // Strip port suffix (e.g., ":3306")
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        if (!str_contains($host, '.rds.amazonaws.com')) {
            return null;
        }

        // Aurora Cluster: {cluster-id}.cluster-{hash}.{region}.rds.amazonaws.com
        if (preg_match(self::AURORA_CLUSTER_PATTERN, $host, $matches) === 1) {
            return [
                'type' => 'aurora',
                'region' => $matches[2],
                'dimensions' => ['DBClusterIdentifier' => $matches[1]],
            ];
        }

        // RDS Instance: {instance-id}.{hash}.{region}.rds.amazonaws.com
        if (preg_match(self::RDS_INSTANCE_PATTERN, $host, $matches) === 1) {
            return [
                'type' => 'rds',
                'region' => $matches[2],
                'dimensions' => ['DBInstanceIdentifier' => $matches[1]],
            ];
        }

        return null;
    }

    /**
     * Detect database engine from DATABASE_DSN scheme.
     *
     * @return 'mysql'|'postgres'
     */
    private function detectEngine(): string
    {
        if (\defined('DATABASE_DSN')) {
            $dsn = (string) \constant('DATABASE_DSN');

            if (str_starts_with($dsn, 'pgsql://') || str_starts_with($dsn, 'pgsql+dataapi://')) {
                return 'postgres';
            }
        }

        return 'mysql';
    }

    /**
     * @param 'aurora'|'rds' $type
     * @param array<string, string> $dimensions
     * @param 'mysql'|'postgres' $engine
     *
     * @return list<MetricDefinition>
     */
    private function buildMetrics(string $type, array $dimensions, string $engine = 'mysql'): array
    {
        $metrics = [
            new MetricDefinition(
                id: 'rds.cpu_utilization',
                label: 'CPU Utilization',
                namespace: 'AWS/RDS',
                metricName: 'CPUUtilization',
                unit: 'Percent',
                stat: 'Average',
                dimensions: $dimensions,
                locked: true,
            ),
            new MetricDefinition(
                id: 'rds.database_connections',
                label: 'Database Connections',
                namespace: 'AWS/RDS',
                metricName: 'DatabaseConnections',
                unit: 'Count',
                stat: 'Average',
                dimensions: $dimensions,
                locked: true,
            ),
            new MetricDefinition(
                id: 'rds.freeable_memory',
                label: 'Freeable Memory',
                namespace: 'AWS/RDS',
                metricName: 'FreeableMemory',
                unit: 'Bytes',
                stat: 'Average',
                dimensions: $dimensions,
                locked: true,
            ),
            new MetricDefinition(
                id: 'rds.free_storage_space',
                label: 'Free Storage Space',
                namespace: 'AWS/RDS',
                metricName: 'FreeStorageSpace',
                unit: 'Bytes',
                stat: 'Average',
                dimensions: $dimensions,
                locked: true,
            ),
            new MetricDefinition(
                id: 'rds.read_iops',
                label: 'Read IOPS',
                namespace: 'AWS/RDS',
                metricName: 'ReadIOPS',
                unit: 'Count/Second',
                stat: 'Average',
                dimensions: $dimensions,
                locked: true,
            ),
            new MetricDefinition(
                id: 'rds.write_iops',
                label: 'Write IOPS',
                namespace: 'AWS/RDS',
                metricName: 'WriteIOPS',
                unit: 'Count/Second',
                stat: 'Average',
                dimensions: $dimensions,
                locked: true,
            ),
        ];

        if ($type === 'aurora') {
            $metrics[] = new MetricDefinition(
                id: 'rds.serverless_database_capacity',
                label: 'Serverless Database Capacity',
                description: 'Aurora Serverless ACU capacity',
                namespace: 'AWS/RDS',
                metricName: 'ServerlessDatabaseCapacity',
                unit: 'Count',
                stat: 'Average',
                dimensions: $dimensions,
                locked: true,
            );
            $metrics[] = new MetricDefinition(
                id: 'rds.acu_utilization',
                label: 'ACU Utilization',
                description: 'Aurora Serverless ACU utilization percentage',
                namespace: 'AWS/RDS',
                metricName: 'ACUUtilization',
                unit: 'Percent',
                stat: 'Average',
                dimensions: $dimensions,
                locked: true,
            );
        }

        // Aurora PostgreSQL-specific metrics
        if ($type === 'aurora' && $engine === 'postgres') {
            $metrics[] = new MetricDefinition(
                id: 'rds.aurora_replica_lag',
                label: 'Replica Lag',
                description: 'Aurora PostgreSQL replica lag in milliseconds',
                namespace: 'AWS/RDS',
                metricName: 'AuroraReplicaLag',
                unit: 'Milliseconds',
                stat: 'Average',
                dimensions: $dimensions,
                locked: true,
            );
            $metrics[] = new MetricDefinition(
                id: 'rds.maximum_used_transaction_ids',
                label: 'Max Used Transaction IDs',
                description: 'Maximum transaction IDs used (TXID wraparound risk)',
                namespace: 'AWS/RDS',
                metricName: 'MaximumUsedTransactionIDs',
                unit: 'Count',
                stat: 'Maximum',
                dimensions: $dimensions,
                locked: true,
            );
        }

        return $metrics;
    }
}
