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

use WpPack\Component\Monitoring\AwsProviderSettings;
use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringProviderInterface;

/**
 * Provides sample AWS monitoring providers with mock data for local development.
 */
final class MockCloudWatchMonitoringProvider implements MonitoringProviderInterface
{
    public function getProviders(): array
    {
        return [
            // Compute
            new MonitoringProvider(
                id: 'mock-ec2',
                label: 'EC2 (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.ec2.cpu', label: 'CPU Utilization', description: 'CPU usage percentage', namespace: 'AWS/EC2', metricName: 'CPUUtilization', unit: 'Percent', stat: 'Average', dimensions: ['InstanceId' => 'i-0123456789abcdef0'], locked: true),
                    new MetricDefinition(id: 'mock.ec2.network_in', label: 'Network In', description: 'Network bytes received', namespace: 'AWS/EC2', metricName: 'NetworkIn', unit: 'Bytes', stat: 'Sum', dimensions: ['InstanceId' => 'i-0123456789abcdef0'], locked: true),
                    new MetricDefinition(id: 'mock.ec2.network_out', label: 'Network Out', description: 'Network bytes sent', namespace: 'AWS/EC2', metricName: 'NetworkOut', unit: 'Bytes', stat: 'Sum', dimensions: ['InstanceId' => 'i-0123456789abcdef0'], locked: true),
                    new MetricDefinition(id: 'mock.ec2.status_check', label: 'Status Check', description: 'Instance status check failures', namespace: 'AWS/EC2', metricName: 'StatusCheckFailed', unit: 'Count', stat: 'Maximum', dimensions: ['InstanceId' => 'i-0123456789abcdef0'], locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-ecs',
                label: 'ECS (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.ecs.cpu', label: 'CPU Utilization', description: 'Cluster CPU utilization', namespace: 'AWS/ECS', metricName: 'CPUUtilization', unit: 'Percent', stat: 'Average', dimensions: ['ClusterName' => 'my-ecs-cluster'], locked: true),
                    new MetricDefinition(id: 'mock.ecs.memory', label: 'Memory Utilization', description: 'Cluster memory utilization', namespace: 'AWS/ECS', metricName: 'MemoryUtilization', unit: 'Percent', stat: 'Average', dimensions: ['ClusterName' => 'my-ecs-cluster'], locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-lambda',
                label: 'Lambda (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.lambda.invocations', label: 'Invocations', description: 'Function invocations', namespace: 'AWS/Lambda', metricName: 'Invocations', unit: 'Count', stat: 'Sum', dimensions: ['FunctionName' => 'my-wordpress-function'], locked: true),
                    new MetricDefinition(id: 'mock.lambda.duration', label: 'Duration', description: 'Execution time', namespace: 'AWS/Lambda', metricName: 'Duration', unit: 'Milliseconds', stat: 'Average', dimensions: ['FunctionName' => 'my-wordpress-function'], locked: true),
                    new MetricDefinition(id: 'mock.lambda.errors', label: 'Errors', description: 'Function errors', namespace: 'AWS/Lambda', metricName: 'Errors', unit: 'Count', stat: 'Sum', dimensions: ['FunctionName' => 'my-wordpress-function'], locked: true),
                    new MetricDefinition(id: 'mock.lambda.throttles', label: 'Throttles', description: 'Throttled invocations', namespace: 'AWS/Lambda', metricName: 'Throttles', unit: 'Count', stat: 'Sum', dimensions: ['FunctionName' => 'my-wordpress-function'], locked: true),
                ],
                locked: true,
            ),
            // Database
            new MonitoringProvider(
                id: 'mock-rds',
                label: 'RDS MySQL (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.rds.cpu', label: 'CPU Utilization', description: 'CPU usage percentage', namespace: 'AWS/RDS', metricName: 'CPUUtilization', unit: 'Percent', stat: 'Average', locked: true),
                    new MetricDefinition(id: 'mock.rds.connections', label: 'DB Connections', description: 'Active database connections', namespace: 'AWS/RDS', metricName: 'DatabaseConnections', unit: 'Count', stat: 'Average', locked: true),
                    new MetricDefinition(id: 'mock.rds.memory', label: 'Freeable Memory', description: 'Available RAM', namespace: 'AWS/RDS', metricName: 'FreeableMemory', unit: 'Bytes', stat: 'Average', locked: true),
                    new MetricDefinition(id: 'mock.rds.storage', label: 'Free Storage', description: 'Available storage space', namespace: 'AWS/RDS', metricName: 'FreeStorageSpace', unit: 'Bytes', stat: 'Average', locked: true),
                    new MetricDefinition(id: 'mock.rds.read_iops', label: 'Read IOPS', description: 'Read I/O operations per second', namespace: 'AWS/RDS', metricName: 'ReadIOPS', unit: 'Count', stat: 'Average', locked: true),
                    new MetricDefinition(id: 'mock.rds.write_iops', label: 'Write IOPS', description: 'Write I/O operations per second', namespace: 'AWS/RDS', metricName: 'WriteIOPS', unit: 'Count', stat: 'Average', locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-aurora',
                label: 'Aurora Cluster (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.aurora.cpu', label: 'CPU Utilization', description: 'CPU usage percentage', namespace: 'AWS/RDS', metricName: 'CPUUtilization', unit: 'Percent', stat: 'Average', dimensions: ['DBClusterIdentifier' => 'prod-aurora-cluster'], locked: true),
                    new MetricDefinition(id: 'mock.aurora.connections', label: 'DB Connections', description: 'Active database connections', namespace: 'AWS/RDS', metricName: 'DatabaseConnections', unit: 'Count', stat: 'Average', dimensions: ['DBClusterIdentifier' => 'prod-aurora-cluster'], locked: true),
                    new MetricDefinition(id: 'mock.aurora.memory', label: 'Freeable Memory', description: 'Available RAM', namespace: 'AWS/RDS', metricName: 'FreeableMemory', unit: 'Bytes', stat: 'Average', dimensions: ['DBClusterIdentifier' => 'prod-aurora-cluster'], locked: true),
                    new MetricDefinition(id: 'mock.aurora.acu', label: 'ACU', description: 'Current Aurora Capacity Units (Serverless)', namespace: 'AWS/RDS', metricName: 'ServerlessDatabaseCapacity', unit: 'Count', stat: 'Average', dimensions: ['DBClusterIdentifier' => 'prod-aurora-cluster'], locked: true),
                    new MetricDefinition(id: 'mock.aurora.acu_util', label: 'ACU Utilization', description: 'ACU usage percentage (Serverless)', namespace: 'AWS/RDS', metricName: 'ACUUtilization', unit: 'Percent', stat: 'Average', dimensions: ['DBClusterIdentifier' => 'prod-aurora-cluster'], locked: true),
                    new MetricDefinition(id: 'mock.aurora.storage', label: 'Storage Used', description: 'Cluster storage volume size', namespace: 'AWS/RDS', metricName: 'VolumeBytesUsed', unit: 'Bytes', stat: 'Average', dimensions: ['DBClusterIdentifier' => 'prod-aurora-cluster'], locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-elasticache',
                label: 'ElastiCache (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.elasticache.hits', label: 'Cache Hits', description: 'Successful cache lookups', namespace: 'AWS/ElastiCache', metricName: 'CacheHits', unit: 'Count', stat: 'Sum', dimensions: ['CacheClusterId' => 'prod-redis-001'], locked: true),
                    new MetricDefinition(id: 'mock.elasticache.misses', label: 'Cache Misses', description: 'Unsuccessful cache lookups', namespace: 'AWS/ElastiCache', metricName: 'CacheMisses', unit: 'Count', stat: 'Sum', dimensions: ['CacheClusterId' => 'prod-redis-001'], locked: true),
                    new MetricDefinition(id: 'mock.elasticache.connections', label: 'Connections', description: 'Current client connections', namespace: 'AWS/ElastiCache', metricName: 'CurrConnections', unit: 'Count', stat: 'Average', dimensions: ['CacheClusterId' => 'prod-redis-001'], locked: true),
                    new MetricDefinition(id: 'mock.elasticache.cpu', label: 'Engine CPU', description: 'Engine thread CPU utilization', namespace: 'AWS/ElastiCache', metricName: 'EngineCPUUtilization', unit: 'Percent', stat: 'Average', dimensions: ['CacheClusterId' => 'prod-redis-001'], locked: true),
                    new MetricDefinition(id: 'mock.elasticache.memory', label: 'Memory Usage', description: 'Memory usage percentage', namespace: 'AWS/ElastiCache', metricName: 'DatabaseMemoryUsagePercentage', unit: 'Percent', stat: 'Average', dimensions: ['CacheClusterId' => 'prod-redis-001'], locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-dynamodb',
                label: 'DynamoDB (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.dynamodb.read_capacity', label: 'Read Capacity', description: 'Read capacity units consumed', namespace: 'AWS/DynamoDB', metricName: 'ConsumedReadCapacityUnits', unit: 'Count', stat: 'Sum', dimensions: ['TableName' => 'cache'], locked: true),
                    new MetricDefinition(id: 'mock.dynamodb.write_capacity', label: 'Write Capacity', description: 'Write capacity units consumed', namespace: 'AWS/DynamoDB', metricName: 'ConsumedWriteCapacityUnits', unit: 'Count', stat: 'Sum', dimensions: ['TableName' => 'cache'], locked: true),
                    new MetricDefinition(id: 'mock.dynamodb.read_throttles', label: 'Read Throttles', description: 'Read throttle events', namespace: 'AWS/DynamoDB', metricName: 'ReadThrottleEvents', unit: 'Count', stat: 'Sum', dimensions: ['TableName' => 'cache'], locked: true),
                    new MetricDefinition(id: 'mock.dynamodb.write_throttles', label: 'Write Throttles', description: 'Write throttle events', namespace: 'AWS/DynamoDB', metricName: 'WriteThrottleEvents', unit: 'Count', stat: 'Sum', dimensions: ['TableName' => 'cache'], locked: true),
                    new MetricDefinition(id: 'mock.dynamodb.latency', label: 'Request Latency', description: 'Successful request latency', namespace: 'AWS/DynamoDB', metricName: 'SuccessfulRequestLatency', unit: 'Milliseconds', stat: 'Average', dimensions: ['TableName' => 'cache'], locked: true),
                    new MetricDefinition(id: 'mock.dynamodb.user_errors', label: 'User Errors', description: 'Client-side errors', namespace: 'AWS/DynamoDB', metricName: 'UserErrors', unit: 'Count', stat: 'Sum', dimensions: ['TableName' => 'cache'], locked: true),
                ],
                locked: true,
            ),
            // Network
            new MonitoringProvider(
                id: 'mock-alb',
                label: 'ALB (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.alb.response_time', label: 'Response Time', description: 'Target response time', namespace: 'AWS/ApplicationELB', metricName: 'TargetResponseTime', unit: 'Seconds', stat: 'Average', dimensions: ['LoadBalancer' => 'app/my-alb/1234567890abcdef'], locked: true),
                    new MetricDefinition(id: 'mock.alb.request_count', label: 'Request Count', description: 'Total requests', namespace: 'AWS/ApplicationELB', metricName: 'RequestCount', unit: 'Count', stat: 'Sum', dimensions: ['LoadBalancer' => 'app/my-alb/1234567890abcdef'], locked: true),
                    new MetricDefinition(id: 'mock.alb.2xx', label: '2xx Responses', description: 'Successful target responses', namespace: 'AWS/ApplicationELB', metricName: 'HTTPCode_Target_2XX_Count', unit: 'Count', stat: 'Sum', dimensions: ['LoadBalancer' => 'app/my-alb/1234567890abcdef'], locked: true),
                    new MetricDefinition(id: 'mock.alb.4xx', label: '4xx Errors', description: 'Client error target responses', namespace: 'AWS/ApplicationELB', metricName: 'HTTPCode_Target_4XX_Count', unit: 'Count', stat: 'Sum', dimensions: ['LoadBalancer' => 'app/my-alb/1234567890abcdef'], locked: true),
                    new MetricDefinition(id: 'mock.alb.5xx', label: '5xx Errors', description: 'Server error target responses', namespace: 'AWS/ApplicationELB', metricName: 'HTTPCode_Target_5XX_Count', unit: 'Count', stat: 'Sum', dimensions: ['LoadBalancer' => 'app/my-alb/1234567890abcdef'], locked: true),
                    new MetricDefinition(id: 'mock.alb.healthy', label: 'Healthy Hosts', description: 'Healthy target count', namespace: 'AWS/ApplicationELB', metricName: 'HealthyHostCount', unit: 'Count', stat: 'Average', dimensions: ['LoadBalancer' => 'app/my-alb/1234567890abcdef'], locked: true),
                    new MetricDefinition(id: 'mock.alb.unhealthy', label: 'Unhealthy Hosts', description: 'Unhealthy target count', namespace: 'AWS/ApplicationELB', metricName: 'UnHealthyHostCount', unit: 'Count', stat: 'Average', dimensions: ['LoadBalancer' => 'app/my-alb/1234567890abcdef'], locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-cloudfront',
                label: 'CloudFront (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'us-east-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.cloudfront.requests', label: 'Requests', description: 'Total requests', namespace: 'AWS/CloudFront', metricName: 'Requests', unit: 'Count', stat: 'Sum', dimensions: ['DistributionId' => 'E1A2B3C4D5E6F7'], locked: true),
                    new MetricDefinition(id: 'mock.cloudfront.downloaded', label: 'Downloaded', description: 'Bytes downloaded', namespace: 'AWS/CloudFront', metricName: 'BytesDownloaded', unit: 'Bytes', stat: 'Sum', dimensions: ['DistributionId' => 'E1A2B3C4D5E6F7'], locked: true),
                    new MetricDefinition(id: 'mock.cloudfront.4xx_error_rate', label: '4xx Error Rate', description: 'Client error rate', namespace: 'AWS/CloudFront', metricName: '4xxErrorRate', unit: 'Percent', stat: 'Average', dimensions: ['DistributionId' => 'E1A2B3C4D5E6F7'], locked: true),
                    new MetricDefinition(id: 'mock.cloudfront.5xx_error_rate', label: '5xx Error Rate', description: 'Server error rate', namespace: 'AWS/CloudFront', metricName: '5xxErrorRate', unit: 'Percent', stat: 'Average', dimensions: ['DistributionId' => 'E1A2B3C4D5E6F7'], locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-aws-waf',
                label: 'AWS WAF (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.waf.allowed', label: 'Allowed Requests', description: 'Requests allowed by WAF', namespace: 'AWS/WAFV2', metricName: 'AllowedRequests', unit: 'Count', stat: 'Sum', dimensions: ['WebACL' => 'my-web-acl', 'Rule' => 'ALL'], locked: true),
                    new MetricDefinition(id: 'mock.waf.blocked', label: 'Blocked Requests', description: 'Requests blocked by WAF', namespace: 'AWS/WAFV2', metricName: 'BlockedRequests', unit: 'Count', stat: 'Sum', dimensions: ['WebACL' => 'my-web-acl', 'Rule' => 'ALL'], locked: true),
                    new MetricDefinition(id: 'mock.waf.counted', label: 'Counted Requests', description: 'Requests in count mode', namespace: 'AWS/WAFV2', metricName: 'CountedRequests', unit: 'Count', stat: 'Sum', dimensions: ['WebACL' => 'my-web-acl', 'Rule' => 'ALL'], locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-natgw',
                label: 'NAT Gateway (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.natgw.bytes_out', label: 'Bytes Out', description: 'Outbound bytes to destination', namespace: 'AWS/NATGateway', metricName: 'BytesOutToDestination', unit: 'Bytes', stat: 'Sum', dimensions: ['NatGatewayId' => 'nat-0123456789abcdef0'], locked: true),
                    new MetricDefinition(id: 'mock.natgw.bytes_in', label: 'Bytes In', description: 'Inbound bytes from destination', namespace: 'AWS/NATGateway', metricName: 'BytesInFromDestination', unit: 'Bytes', stat: 'Sum', dimensions: ['NatGatewayId' => 'nat-0123456789abcdef0'], locked: true),
                    new MetricDefinition(id: 'mock.natgw.packets_drop', label: 'Dropped Packets', description: 'Packets dropped', namespace: 'AWS/NATGateway', metricName: 'PacketsDropCount', unit: 'Count', stat: 'Sum', dimensions: ['NatGatewayId' => 'nat-0123456789abcdef0'], locked: true),
                    new MetricDefinition(id: 'mock.natgw.port_errors', label: 'Port Errors', description: 'Port allocation errors', namespace: 'AWS/NATGateway', metricName: 'ErrorPortAllocation', unit: 'Count', stat: 'Sum', dimensions: ['NatGatewayId' => 'nat-0123456789abcdef0'], locked: true),
                    new MetricDefinition(id: 'mock.natgw.connections', label: 'Active Connections', description: 'Active connections', namespace: 'AWS/NATGateway', metricName: 'ActiveConnectionCount', unit: 'Count', stat: 'Maximum', dimensions: ['NatGatewayId' => 'nat-0123456789abcdef0'], locked: true),
                ],
                locked: true,
            ),
            // Storage
            new MonitoringProvider(
                id: 'mock-s3',
                label: 'S3 Storage (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.s3.bucket_size', label: 'Bucket Size', description: 'Total bucket size', namespace: 'AWS/S3', metricName: 'BucketSizeBytes', unit: 'Bytes', stat: 'Average', dimensions: ['BucketName' => 'my-bucket', 'StorageType' => 'StandardStorage'], locked: true),
                    new MetricDefinition(id: 'mock.s3.object_count', label: 'Object Count', description: 'Total number of objects', namespace: 'AWS/S3', metricName: 'NumberOfObjects', unit: 'Count', stat: 'Average', dimensions: ['BucketName' => 'my-bucket', 'StorageType' => 'AllStorageTypes'], locked: true),
                ],
                locked: true,
            ),
            // Messaging
            new MonitoringProvider(
                id: 'mock-sqs',
                label: 'SQS (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.sqs.sent', label: 'Messages Sent', description: 'Messages sent to queue', namespace: 'AWS/SQS', metricName: 'NumberOfMessagesSent', unit: 'Count', stat: 'Sum', dimensions: ['QueueName' => 'wordpress-queue'], locked: true),
                    new MetricDefinition(id: 'mock.sqs.depth', label: 'Queue Depth', description: 'Messages in queue', namespace: 'AWS/SQS', metricName: 'ApproximateNumberOfMessagesVisible', unit: 'Count', stat: 'Average', dimensions: ['QueueName' => 'wordpress-queue'], locked: true),
                    new MetricDefinition(id: 'mock.sqs.oldest', label: 'Oldest Message', description: 'Age of oldest message', namespace: 'AWS/SQS', metricName: 'ApproximateAgeOfOldestMessage', unit: 'Seconds', stat: 'Maximum', dimensions: ['QueueName' => 'wordpress-queue'], locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-ses',
                label: 'SES (Mock)',
                bridge: 'mock-aws',
                settings: new AwsProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.ses.send', label: 'Emails Sent', description: 'Total number of emails sent', namespace: 'AWS/SES', metricName: 'Send', unit: 'Count', stat: 'Sum', locked: true),
                    new MetricDefinition(id: 'mock.ses.delivery', label: 'Delivered', description: 'Emails successfully delivered to recipient mail server', namespace: 'AWS/SES', metricName: 'Delivery', unit: 'Count', stat: 'Sum', locked: true),
                    new MetricDefinition(id: 'mock.ses.bounce', label: 'Bounces', description: 'Hard and soft bounces from recipient mail servers', namespace: 'AWS/SES', metricName: 'Bounce', unit: 'Count', stat: 'Sum', locked: true),
                    new MetricDefinition(id: 'mock.ses.complaint', label: 'Complaints', description: 'Emails marked as spam by recipients', namespace: 'AWS/SES', metricName: 'Complaint', unit: 'Count', stat: 'Sum', locked: true),
                ],
                locked: true,
            ),
        ];
    }
}
