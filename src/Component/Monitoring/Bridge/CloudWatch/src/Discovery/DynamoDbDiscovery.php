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

namespace WpPack\Component\Monitoring\Bridge\CloudWatch\Discovery;

use WpPack\Component\Monitoring\AwsProviderSettings;
use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringProviderInterface;

final class DynamoDbDiscovery implements MonitoringProviderInterface
{
    public function getProviders(): array
    {
        $dsn = \defined('WPPACK_CACHE_DSN') ? (string) \constant('WPPACK_CACHE_DSN') : ($_ENV['WPPACK_CACHE_DSN'] ?? '');

        if ($dsn === '') {
            return [];
        }

        $scheme = parse_url($dsn, \PHP_URL_SCHEME);

        if ($scheme !== 'dynamodb') {
            return [];
        }

        $host = parse_url($dsn, \PHP_URL_HOST);

        if (!\is_string($host)) {
            return [];
        }

        // Extract region from host (e.g., "us-east-1")
        $region = $host;

        if (!preg_match('/^[a-z]{2,4}-[a-z]+-\d+$/', $region)) {
            return [];
        }

        $path = parse_url($dsn, \PHP_URL_PATH);
        $tableName = \is_string($path) ? ltrim($path, '/') : 'cache';

        if ($tableName === '') {
            $tableName = 'cache';
        }

        $dimensions = ['TableName' => $tableName];

        return [
            new MonitoringProvider(
                id: 'dynamodb',
                label: \sprintf('DynamoDB (%s)', $tableName),
                bridge: 'cloudwatch',
                settings: new AwsProviderSettings(region: $region),
                metrics: [
                    new MetricDefinition(
                        id: 'dynamodb.consumed_read_capacity_units',
                        label: 'Read Capacity',
                        namespace: 'AWS/DynamoDB',
                        metricName: 'ConsumedReadCapacityUnits',
                        unit: 'Count',
                        stat: 'Sum',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'dynamodb.consumed_write_capacity_units',
                        label: 'Write Capacity',
                        namespace: 'AWS/DynamoDB',
                        metricName: 'ConsumedWriteCapacityUnits',
                        unit: 'Count',
                        stat: 'Sum',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'dynamodb.read_throttle_events',
                        label: 'Read Throttles',
                        namespace: 'AWS/DynamoDB',
                        metricName: 'ReadThrottleEvents',
                        unit: 'Count',
                        stat: 'Sum',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'dynamodb.write_throttle_events',
                        label: 'Write Throttles',
                        namespace: 'AWS/DynamoDB',
                        metricName: 'WriteThrottleEvents',
                        unit: 'Count',
                        stat: 'Sum',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'dynamodb.successful_request_latency',
                        label: 'Request Latency',
                        namespace: 'AWS/DynamoDB',
                        metricName: 'SuccessfulRequestLatency',
                        unit: 'Milliseconds',
                        stat: 'Average',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'dynamodb.user_errors',
                        label: 'User Errors',
                        namespace: 'AWS/DynamoDB',
                        metricName: 'UserErrors',
                        unit: 'Count',
                        stat: 'Sum',
                        dimensions: $dimensions,
                        locked: true,
                    ),
                ],
                locked: true,
            ),
        ];
    }
}
