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

namespace WpPack\Plugin\MonitoringPlugin\Template;

/**
 * PHP-side metric template definitions.
 *
 * Must be kept in sync with js/src/dashboard/data/templates.js.
 */
final class MetricTemplateRegistry
{
    /** @var array<string, MetricTemplate>|null */
    private ?array $templates = null;

    /**
     * @return array<string, MetricTemplate>
     */
    public function all(): array
    {
        return $this->templates ??= $this->build();
    }

    public function get(string $templateId): ?MetricTemplate
    {
        return $this->all()[$templateId] ?? null;
    }

    /**
     * @return array<string, MetricTemplate>
     */
    private function build(): array
    {
        $templates = [];

        foreach ($this->definitions() as $def) {
            $templates[$def['id']] = new MetricTemplate(
                id: $def['id'],
                bridge: $def['bridge'],
                namespace: $def['namespace'],
                dimensionKey: $def['dimensionKey'],
                metrics: $def['metrics'],
            );
        }

        return $templates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            // Compute
            [
                'id' => 'ec2',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/EC2',
                'dimensionKey' => 'InstanceId',
                'metrics' => [
                    ['metricName' => 'CPUUtilization', 'label' => 'CPU Utilization', 'description' => 'CPU usage percentage', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => 'NetworkIn', 'label' => 'Network In', 'description' => 'Network bytes received', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => 'NetworkOut', 'label' => 'Network Out', 'description' => 'Network bytes sent', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => 'StatusCheckFailed', 'label' => 'Status Check', 'description' => 'Instance status check failures', 'stat' => 'Maximum', 'unit' => 'Count'],
                ],
            ],
            [
                'id' => 'ecs',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/ECS',
                'dimensionKey' => 'ClusterName',
                'metrics' => [
                    ['metricName' => 'CPUUtilization', 'label' => 'CPU Utilization', 'description' => 'Cluster CPU utilization', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => 'MemoryUtilization', 'label' => 'Memory Utilization', 'description' => 'Cluster memory utilization', 'stat' => 'Average', 'unit' => 'Percent'],
                ],
            ],
            [
                'id' => 'lambda',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/Lambda',
                'dimensionKey' => 'FunctionName',
                'metrics' => [
                    ['metricName' => 'Invocations', 'label' => 'Invocations', 'description' => 'Function invocations', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'Duration', 'label' => 'Duration', 'description' => 'Execution time', 'stat' => 'Average', 'unit' => 'Milliseconds'],
                    ['metricName' => 'Errors', 'label' => 'Errors', 'description' => 'Function errors', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'Throttles', 'label' => 'Throttles', 'description' => 'Throttled invocations', 'stat' => 'Sum', 'unit' => 'Count'],
                ],
            ],
            // Database
            [
                'id' => 'rds',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/RDS',
                'dimensionKey' => 'DBInstanceIdentifier',
                'metrics' => [
                    ['metricName' => 'CPUUtilization', 'label' => 'CPU Utilization', 'description' => 'CPU usage percentage', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => 'DatabaseConnections', 'label' => 'DB Connections', 'description' => 'Active database connections', 'stat' => 'Average', 'unit' => 'Count'],
                    ['metricName' => 'FreeableMemory', 'label' => 'Freeable Memory', 'description' => 'Available RAM', 'stat' => 'Average', 'unit' => 'Bytes'],
                    ['metricName' => 'FreeStorageSpace', 'label' => 'Free Storage', 'description' => 'Available storage space', 'stat' => 'Average', 'unit' => 'Bytes'],
                    ['metricName' => 'ReadIOPS', 'label' => 'Read IOPS', 'description' => 'Read I/O operations per second', 'stat' => 'Average', 'unit' => 'Count'],
                    ['metricName' => 'WriteIOPS', 'label' => 'Write IOPS', 'description' => 'Write I/O operations per second', 'stat' => 'Average', 'unit' => 'Count'],
                ],
            ],
            [
                'id' => 'aurora-cluster',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/RDS',
                'dimensionKey' => 'DBClusterIdentifier',
                'metrics' => [
                    ['metricName' => 'CPUUtilization', 'label' => 'CPU Utilization', 'description' => 'CPU usage percentage', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => 'DatabaseConnections', 'label' => 'DB Connections', 'description' => 'Active database connections', 'stat' => 'Average', 'unit' => 'Count'],
                    ['metricName' => 'FreeableMemory', 'label' => 'Freeable Memory', 'description' => 'Available RAM', 'stat' => 'Average', 'unit' => 'Bytes'],
                    ['metricName' => 'ServerlessDatabaseCapacity', 'label' => 'ACU', 'description' => 'Current Aurora Capacity Units (Serverless)', 'stat' => 'Average', 'unit' => 'Count'],
                    ['metricName' => 'ACUUtilization', 'label' => 'ACU Utilization', 'description' => 'ACU usage percentage (Serverless)', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => 'VolumeBytesUsed', 'label' => 'Storage Used', 'description' => 'Cluster storage volume size', 'stat' => 'Average', 'unit' => 'Bytes'],
                ],
            ],
            [
                'id' => 'elasticache',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/ElastiCache',
                'dimensionKey' => 'CacheClusterId',
                'metrics' => [
                    ['metricName' => 'CacheHits', 'label' => 'Cache Hits', 'description' => 'Successful cache lookups', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'CacheMisses', 'label' => 'Cache Misses', 'description' => 'Unsuccessful cache lookups', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'CurrConnections', 'label' => 'Connections', 'description' => 'Current client connections', 'stat' => 'Average', 'unit' => 'Count'],
                    ['metricName' => 'EngineCPUUtilization', 'label' => 'Engine CPU', 'description' => 'Engine thread CPU utilization', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => 'DatabaseMemoryUsagePercentage', 'label' => 'Memory Usage', 'description' => 'Memory usage percentage', 'stat' => 'Average', 'unit' => 'Percent'],
                ],
            ],
            [
                'id' => 'dynamodb',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/DynamoDB',
                'dimensionKey' => 'TableName',
                'metrics' => [
                    ['metricName' => 'ConsumedReadCapacityUnits', 'label' => 'Read Capacity', 'description' => 'Read capacity units consumed', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'ConsumedWriteCapacityUnits', 'label' => 'Write Capacity', 'description' => 'Write capacity units consumed', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'ReadThrottleEvents', 'label' => 'Read Throttles', 'description' => 'Read throttle events', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'WriteThrottleEvents', 'label' => 'Write Throttles', 'description' => 'Write throttle events', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'SuccessfulRequestLatency', 'label' => 'Request Latency', 'description' => 'Successful request latency', 'stat' => 'Average', 'unit' => 'Milliseconds'],
                    ['metricName' => 'UserErrors', 'label' => 'User Errors', 'description' => 'Client-side errors', 'stat' => 'Sum', 'unit' => 'Count'],
                ],
            ],
            // Network
            [
                'id' => 'alb',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/ApplicationELB',
                'dimensionKey' => 'LoadBalancer',
                'metrics' => [
                    ['metricName' => 'TargetResponseTime', 'label' => 'Response Time', 'description' => 'Target response time', 'stat' => 'Average', 'unit' => 'Seconds'],
                    ['metricName' => 'RequestCount', 'label' => 'Request Count', 'description' => 'Total requests', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'HTTPCode_Target_2XX_Count', 'label' => '2xx Responses', 'description' => 'Successful target responses', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'HTTPCode_Target_4XX_Count', 'label' => '4xx Errors', 'description' => 'Client error target responses', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'HTTPCode_Target_5XX_Count', 'label' => '5xx Errors', 'description' => 'Server error target responses', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'HealthyHostCount', 'label' => 'Healthy Hosts', 'description' => 'Healthy target count', 'stat' => 'Average', 'unit' => 'Count'],
                    ['metricName' => 'UnHealthyHostCount', 'label' => 'Unhealthy Hosts', 'description' => 'Unhealthy target count', 'stat' => 'Average', 'unit' => 'Count'],
                ],
            ],
            [
                'id' => 'cloudfront',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/CloudFront',
                'dimensionKey' => 'DistributionId',
                'metrics' => [
                    ['metricName' => 'Requests', 'label' => 'Requests', 'description' => 'Total requests', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'BytesDownloaded', 'label' => 'Downloaded', 'description' => 'Bytes downloaded', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => '4xxErrorRate', 'label' => '4xx Error Rate', 'description' => 'Client error rate', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => '5xxErrorRate', 'label' => '5xx Error Rate', 'description' => 'Server error rate', 'stat' => 'Average', 'unit' => 'Percent'],
                ],
            ],
            [
                'id' => 'natgw',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/NATGateway',
                'dimensionKey' => 'NatGatewayId',
                'metrics' => [
                    ['metricName' => 'BytesOutToDestination', 'label' => 'Bytes Out', 'description' => 'Outbound bytes to destination', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => 'BytesInFromDestination', 'label' => 'Bytes In', 'description' => 'Inbound bytes from destination', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => 'PacketsDropCount', 'label' => 'Dropped Packets', 'description' => 'Packets dropped', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'ErrorPortAllocation', 'label' => 'Port Errors', 'description' => 'Port allocation errors', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'ActiveConnectionCount', 'label' => 'Active Connections', 'description' => 'Active connections', 'stat' => 'Maximum', 'unit' => 'Count'],
                ],
            ],
            // Storage
            [
                'id' => 's3',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/S3',
                'dimensionKey' => 'BucketName',
                'metrics' => [
                    ['metricName' => 'BucketSizeBytes', 'label' => 'Bucket Size', 'description' => 'Total bucket size', 'stat' => 'Average', 'unit' => 'Bytes', 'periodSeconds' => 86400, 'extraDimensions' => ['StorageType' => 'StandardStorage']],
                    ['metricName' => 'NumberOfObjects', 'label' => 'Object Count', 'description' => 'Total number of objects', 'stat' => 'Average', 'unit' => 'Count', 'periodSeconds' => 86400, 'extraDimensions' => ['StorageType' => 'AllStorageTypes']],
                ],
            ],
            // Messaging
            [
                'id' => 'sqs',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/SQS',
                'dimensionKey' => 'QueueName',
                'metrics' => [
                    ['metricName' => 'NumberOfMessagesSent', 'label' => 'Messages Sent', 'description' => 'Messages sent to queue', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'ApproximateNumberOfMessagesVisible', 'label' => 'Queue Depth', 'description' => 'Messages in queue', 'stat' => 'Average', 'unit' => 'Count'],
                    ['metricName' => 'ApproximateAgeOfOldestMessage', 'label' => 'Oldest Message', 'description' => 'Age of oldest message', 'stat' => 'Maximum', 'unit' => 'Seconds'],
                ],
            ],
            // Security
            [
                'id' => 'aws-waf',
                'bridge' => 'cloudwatch',
                'namespace' => 'AWS/WAFV2',
                'dimensionKey' => 'WebACL',
                'metrics' => [
                    ['metricName' => 'AllowedRequests', 'label' => 'Allowed Requests', 'description' => 'Requests allowed by WAF', 'stat' => 'Sum', 'unit' => 'Count', 'extraDimensions' => ['Rule' => 'ALL']],
                    ['metricName' => 'BlockedRequests', 'label' => 'Blocked Requests', 'description' => 'Requests blocked by WAF', 'stat' => 'Sum', 'unit' => 'Count', 'extraDimensions' => ['Rule' => 'ALL']],
                    ['metricName' => 'CountedRequests', 'label' => 'Counted Requests', 'description' => 'Requests in count mode', 'stat' => 'Sum', 'unit' => 'Count', 'extraDimensions' => ['Rule' => 'ALL']],
                ],
            ],
            // Cloudflare
            [
                'id' => 'cloudflare-zone',
                'bridge' => 'cloudflare',
                'namespace' => 'Cloudflare/Analytics',
                'dimensionKey' => 'ZoneId',
                'metrics' => [
                    ['metricName' => 'requests', 'label' => 'Requests', 'description' => 'Total HTTP requests', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'cachedRequests', 'label' => 'Cached Requests', 'description' => 'Requests served from cache', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'cacheRate', 'label' => 'Cache Rate', 'description' => 'Percentage of requests served from cache', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => 'bandwidth', 'label' => 'Data Transfer', 'description' => 'Total data transfer', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => 'cachedBandwidth', 'label' => 'Cached Transfer', 'description' => 'Data transfer served from cache', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => 'threats', 'label' => 'Threats', 'description' => 'Total threats blocked', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'pageViews', 'label' => 'Page Views', 'description' => 'Total page views', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'uniques', 'label' => 'Unique Visitors', 'description' => 'Unique visitors', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'status2xx', 'label' => '2xx Responses', 'description' => 'Successful responses', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'status3xx', 'label' => '3xx Redirects', 'description' => 'Redirect responses', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'status4xx', 'label' => '4xx Errors', 'description' => 'Client error responses', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'status5xx', 'label' => '5xx Errors', 'description' => 'Server error responses', 'stat' => 'Sum', 'unit' => 'Count'],
                ],
            ],
            [
                'id' => 'cloudflare-waf',
                'bridge' => 'cloudflare',
                'namespace' => 'Cloudflare/WAF',
                'dimensionKey' => 'ZoneId',
                'metrics' => [
                    ['metricName' => 'wafTotal', 'label' => 'WAF Events', 'description' => 'Total firewall events', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'wafBlocked', 'label' => 'WAF Blocked', 'description' => 'Requests blocked by WAF', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'wafChallenged', 'label' => 'JS Challenged', 'description' => 'Requests given JS challenge', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'wafManagedChallenge', 'label' => 'Managed Challenge', 'description' => 'Requests given managed challenge', 'stat' => 'Sum', 'unit' => 'Count'],
                ],
            ],
        ];
    }
}
