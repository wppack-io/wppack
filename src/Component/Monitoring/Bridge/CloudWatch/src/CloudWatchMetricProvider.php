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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch;

use AsyncAws\CloudWatch\CloudWatchClient;
use AsyncAws\CloudWatch\Input\GetMetricDataInput;
use AsyncAws\CloudWatch\ValueObject\Dimension;
use AsyncAws\CloudWatch\ValueObject\Metric;
use AsyncAws\CloudWatch\ValueObject\MetricDataQuery;
use AsyncAws\CloudWatch\ValueObject\MetricStat;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MetricPoint;
use WPPack\Component\Monitoring\MetricProviderInterface;
use WPPack\Component\Monitoring\MetricResult;
use WPPack\Component\Monitoring\MetricTimeRange;
use WPPack\Component\Monitoring\MonitoringProvider;
use Psr\Log\LoggerInterface;
use WPPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;

final class CloudWatchMetricProvider implements MetricProviderInterface
{
    /** @var list<array{value: string, label: string}> */
    private const AWS_REGIONS = [
        ['value' => 'us-east-1', 'label' => 'us-east-1 — US East (N. Virginia)'],
        ['value' => 'us-east-2', 'label' => 'us-east-2 — US East (Ohio)'],
        ['value' => 'us-west-1', 'label' => 'us-west-1 — US West (N. California)'],
        ['value' => 'us-west-2', 'label' => 'us-west-2 — US West (Oregon)'],
        ['value' => 'us-gov-east-1', 'label' => 'us-gov-east-1 — AWS GovCloud (US-East)'],
        ['value' => 'us-gov-west-1', 'label' => 'us-gov-west-1 — AWS GovCloud (US-West)'],
        ['value' => 'af-south-1', 'label' => 'af-south-1 — Africa (Cape Town)'],
        ['value' => 'ap-east-1', 'label' => 'ap-east-1 — Asia Pacific (Hong Kong)'],
        ['value' => 'ap-east-2', 'label' => 'ap-east-2 — Asia Pacific (Taipei)'],
        ['value' => 'ap-south-1', 'label' => 'ap-south-1 — Asia Pacific (Mumbai)'],
        ['value' => 'ap-south-2', 'label' => 'ap-south-2 — Asia Pacific (Hyderabad)'],
        ['value' => 'ap-southeast-1', 'label' => 'ap-southeast-1 — Asia Pacific (Singapore)'],
        ['value' => 'ap-southeast-2', 'label' => 'ap-southeast-2 — Asia Pacific (Sydney)'],
        ['value' => 'ap-southeast-3', 'label' => 'ap-southeast-3 — Asia Pacific (Jakarta)'],
        ['value' => 'ap-southeast-4', 'label' => 'ap-southeast-4 — Asia Pacific (Melbourne)'],
        ['value' => 'ap-southeast-5', 'label' => 'ap-southeast-5 — Asia Pacific (Malaysia)'],
        ['value' => 'ap-southeast-6', 'label' => 'ap-southeast-6 — Asia Pacific (New Zealand)'],
        ['value' => 'ap-southeast-7', 'label' => 'ap-southeast-7 — Asia Pacific (Thailand)'],
        ['value' => 'ap-northeast-1', 'label' => 'ap-northeast-1 — Asia Pacific (Tokyo)'],
        ['value' => 'ap-northeast-2', 'label' => 'ap-northeast-2 — Asia Pacific (Seoul)'],
        ['value' => 'ap-northeast-3', 'label' => 'ap-northeast-3 — Asia Pacific (Osaka)'],
        ['value' => 'ca-central-1', 'label' => 'ca-central-1 — Canada (Central)'],
        ['value' => 'ca-west-1', 'label' => 'ca-west-1 — Canada West (Calgary)'],
        ['value' => 'cn-north-1', 'label' => 'cn-north-1 — China (Beijing)'],
        ['value' => 'cn-northwest-1', 'label' => 'cn-northwest-1 — China (Ningxia)'],
        ['value' => 'eu-central-1', 'label' => 'eu-central-1 — Europe (Frankfurt)'],
        ['value' => 'eu-central-2', 'label' => 'eu-central-2 — Europe (Zurich)'],
        ['value' => 'eu-west-1', 'label' => 'eu-west-1 — Europe (Ireland)'],
        ['value' => 'eu-west-2', 'label' => 'eu-west-2 — Europe (London)'],
        ['value' => 'eu-west-3', 'label' => 'eu-west-3 — Europe (Paris)'],
        ['value' => 'eu-south-1', 'label' => 'eu-south-1 — Europe (Milan)'],
        ['value' => 'eu-south-2', 'label' => 'eu-south-2 — Europe (Spain)'],
        ['value' => 'eu-north-1', 'label' => 'eu-north-1 — Europe (Stockholm)'],
        ['value' => 'eusc-de-east-1', 'label' => 'eusc-de-east-1 — European Sovereign Cloud (Germany)'],
        ['value' => 'il-central-1', 'label' => 'il-central-1 — Israel (Tel Aviv)'],
        ['value' => 'mx-central-1', 'label' => 'mx-central-1 — Mexico (Central)'],
        ['value' => 'me-south-1', 'label' => 'me-south-1 — Middle East (Bahrain)'],
        ['value' => 'me-central-1', 'label' => 'me-central-1 — Middle East (UAE)'],
        ['value' => 'sa-east-1', 'label' => 'sa-east-1 — South America (São Paulo)'],
    ];

    private const IAM_POLICY_JSON = '{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "WPPackMonitoring",
      "Effect": "Allow",
      "Action": "cloudwatch:GetMetricData",
      "Resource": "*"
    }
  ]
}';

    /** @var array<string, CloudWatchClient> */
    private array $clients = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function getName(): string
    {
        return 'cloudwatch';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getLabel(): string
    {
        return 'AWS CloudWatch';
    }

    public function getFormFields(): array
    {
        return [
            [
                'id' => 'settings.region',
                'label' => 'Region',
                'type' => 'select',
                'elements' => self::AWS_REGIONS,
            ],
            [
                'id' => 'settings.accessKeyId',
                'label' => 'Access Key ID',
                'type' => 'text',
                'description' => 'Optional — falls back to IAM role',
            ],
            [
                'id' => 'settings.secretAccessKey',
                'label' => 'Secret Access Key',
                'type' => 'password',
                'description' => 'Optional — falls back to IAM role',
            ],
        ];
    }

    public function getTemplates(): array
    {
        return [
            // Compute
            [
                'id' => 'ec2',
                'label' => 'EC2',
                'namespace' => 'AWS/EC2',
                'dimensionKey' => 'InstanceId',
                'dimensionLabel' => 'Instance ID',
                'dimensionPlaceholder' => 'i-0123456789abcdef0',
                'metrics' => [
                    ['metricName' => 'CPUUtilization', 'label' => 'CPU Utilization', 'description' => 'CPU usage percentage', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => 'NetworkIn', 'label' => 'Network In', 'description' => 'Network bytes received', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => 'NetworkOut', 'label' => 'Network Out', 'description' => 'Network bytes sent', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => 'StatusCheckFailed', 'label' => 'Status Check', 'description' => 'Instance status check failures', 'stat' => 'Maximum', 'unit' => 'Count'],
                ],
            ],
            [
                'id' => 'ecs',
                'label' => 'ECS',
                'namespace' => 'AWS/ECS',
                'dimensionKey' => 'ClusterName',
                'dimensionLabel' => 'Cluster Name',
                'dimensionPlaceholder' => 'my-ecs-cluster',
                'metrics' => [
                    ['metricName' => 'CPUUtilization', 'label' => 'CPU Utilization', 'description' => 'Cluster CPU utilization', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => 'MemoryUtilization', 'label' => 'Memory Utilization', 'description' => 'Cluster memory utilization', 'stat' => 'Average', 'unit' => 'Percent'],
                ],
            ],
            [
                'id' => 'lambda',
                'label' => 'Lambda',
                'namespace' => 'AWS/Lambda',
                'dimensionKey' => 'FunctionName',
                'dimensionLabel' => 'Function Name',
                'dimensionPlaceholder' => 'my-wordpress-function',
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
                'label' => 'RDS / Aurora',
                'namespace' => 'AWS/RDS',
                'dimensionKey' => 'DBInstanceIdentifier',
                'dimensionLabel' => 'DB Instance ID',
                'dimensionPlaceholder' => 'prod-db-001',
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
                'label' => 'Aurora Cluster',
                'namespace' => 'AWS/RDS',
                'dimensionKey' => 'DBClusterIdentifier',
                'dimensionLabel' => 'DB Cluster ID',
                'dimensionPlaceholder' => 'prod-aurora-cluster',
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
                'label' => 'ElastiCache (Redis)',
                'namespace' => 'AWS/ElastiCache',
                'dimensionKey' => 'CacheClusterId',
                'dimensionLabel' => 'Cache Cluster ID',
                'dimensionPlaceholder' => 'prod-redis-001',
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
                'label' => 'DynamoDB',
                'namespace' => 'AWS/DynamoDB',
                'dimensionKey' => 'TableName',
                'dimensionLabel' => 'Table Name',
                'dimensionPlaceholder' => 'my-cache-table',
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
                'label' => 'ALB',
                'namespace' => 'AWS/ApplicationELB',
                'dimensionKey' => 'LoadBalancer',
                'dimensionLabel' => 'Load Balancer',
                'dimensionPlaceholder' => 'app/my-alb/1234567890abcdef',
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
                'label' => 'CloudFront',
                'namespace' => 'AWS/CloudFront',
                'dimensionKey' => 'DistributionId',
                'dimensionLabel' => 'Distribution ID',
                'dimensionPlaceholder' => 'E1A2B3C4D5E6F7',
                'metrics' => [
                    ['metricName' => 'Requests', 'label' => 'Requests', 'description' => 'Total requests', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'BytesDownloaded', 'label' => 'Downloaded', 'description' => 'Bytes downloaded', 'stat' => 'Sum', 'unit' => 'Bytes'],
                    ['metricName' => '4xxErrorRate', 'label' => '4xx Error Rate', 'description' => 'Client error rate', 'stat' => 'Average', 'unit' => 'Percent'],
                    ['metricName' => '5xxErrorRate', 'label' => '5xx Error Rate', 'description' => 'Server error rate', 'stat' => 'Average', 'unit' => 'Percent'],
                ],
            ],
            [
                'id' => 'natgw',
                'label' => 'NAT Gateway',
                'namespace' => 'AWS/NATGateway',
                'dimensionKey' => 'NatGatewayId',
                'dimensionLabel' => 'NAT Gateway ID',
                'dimensionPlaceholder' => 'nat-0123456789abcdef0',
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
                'label' => 'S3',
                'namespace' => 'AWS/S3',
                'dimensionKey' => 'BucketName',
                'dimensionLabel' => 'Bucket Name',
                'dimensionPlaceholder' => 'my-bucket',
                'metrics' => [
                    ['metricName' => 'BucketSizeBytes', 'label' => 'Bucket Size', 'description' => 'Total bucket size', 'stat' => 'Average', 'unit' => 'Bytes', 'periodSeconds' => 86400, 'extraDimensions' => ['StorageType' => 'StandardStorage']],
                    ['metricName' => 'NumberOfObjects', 'label' => 'Object Count', 'description' => 'Total number of objects', 'stat' => 'Average', 'unit' => 'Count', 'periodSeconds' => 86400, 'extraDimensions' => ['StorageType' => 'AllStorageTypes']],
                ],
            ],
            // Messaging
            [
                'id' => 'sqs',
                'label' => 'SQS',
                'namespace' => 'AWS/SQS',
                'dimensionKey' => 'QueueName',
                'dimensionLabel' => 'Queue Name',
                'dimensionPlaceholder' => 'wordpress-queue',
                'metrics' => [
                    ['metricName' => 'NumberOfMessagesSent', 'label' => 'Messages Sent', 'description' => 'Messages sent to queue', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'ApproximateNumberOfMessagesVisible', 'label' => 'Queue Depth', 'description' => 'Messages in queue', 'stat' => 'Average', 'unit' => 'Count'],
                    ['metricName' => 'ApproximateAgeOfOldestMessage', 'label' => 'Oldest Message', 'description' => 'Age of oldest message', 'stat' => 'Maximum', 'unit' => 'Seconds'],
                ],
            ],
            // Security
            [
                'id' => 'aws-waf',
                'label' => 'AWS WAF',
                'namespace' => 'AWS/WAFV2',
                'dimensionKey' => 'WebACL',
                'dimensionLabel' => 'Web ACL Name',
                'dimensionPlaceholder' => 'my-web-acl',
                'metrics' => [
                    ['metricName' => 'AllowedRequests', 'label' => 'Allowed Requests', 'description' => 'Requests allowed by WAF', 'stat' => 'Sum', 'unit' => 'Count', 'extraDimensions' => ['Rule' => 'ALL']],
                    ['metricName' => 'BlockedRequests', 'label' => 'Blocked Requests', 'description' => 'Requests blocked by WAF', 'stat' => 'Sum', 'unit' => 'Count', 'extraDimensions' => ['Rule' => 'ALL']],
                    ['metricName' => 'CountedRequests', 'label' => 'Counted Requests', 'description' => 'Requests in count mode', 'stat' => 'Sum', 'unit' => 'Count', 'extraDimensions' => ['Rule' => 'ALL']],
                ],
            ],
        ];
    }

    public function getDimensionLabels(): array
    {
        return [
            'DBInstanceIdentifier' => 'DB Instance ID',
            'DBClusterIdentifier' => 'DB Cluster ID',
            'CacheClusterId' => 'Cache Cluster ID',
            'DistributionId' => 'Distribution ID',
            'FunctionName' => 'Function Name',
            'QueueName' => 'Queue Name',
            'BucketName' => 'Bucket Name',
            'InstanceId' => 'Instance ID',
            'StorageType' => 'Storage Type',
            'TableName' => 'Table Name',
            'LoadBalancer' => 'Load Balancer',
            'NatGatewayId' => 'NAT Gateway ID',
            'ClusterName' => 'Cluster Name',
            'WebACL' => 'Web ACL Name',
            'Rule' => 'Rule',
        ];
    }

    public function getDefaultSettings(): array
    {
        return ['region' => '', 'accessKeyId' => '', 'secretAccessKey' => ''];
    }

    public function getSetupGuide(): array
    {
        return [
            'buttonLabel' => 'AWS IAM Policy',
            'title' => 'IAM Policy',
            'content' => [
                ['type' => 'paragraph', 'text' => 'The following IAM permission is required for metric data retrieval.'],
                ['type' => 'note', 'text' => 'Note: cloudwatch:GetMetricData does not support resource-level restrictions. Resource must be "*" per AWS specification.'],
                ['type' => 'code', 'code' => self::IAM_POLICY_JSON, 'copyable' => true],
            ],
        ];
    }

    public function validateSettings(array $settings): bool
    {
        return !empty($settings['region']);
    }

    /**
     * @return list<MetricResult>
     */
    public function query(MonitoringProvider $provider, MetricTimeRange $range): array
    {
        if ($provider->metrics === [] || !$provider->settings instanceof AwsProviderSettings) {
            $this->logger?->warning('CloudWatch provider "{id}": invalid settings type, skipping.', ['id' => $provider->id]);

            return [];
        }

        $client = $this->getClient($provider->settings);
        $results = $this->queryMetrics($client, $provider, $range);

        $this->logger?->debug('CloudWatch region "{region}": fetched {count} metrics for provider "{id}".', [
            'region' => $provider->settings->region,
            'count' => \count($results),
            'id' => $provider->id,
        ]);

        return $results;
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

    public function getSettingsClass(): string
    {
        return AwsProviderSettings::class;
    }
}
