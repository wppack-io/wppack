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

namespace WpPack\Component\Monitoring\Bridge\Mock;

use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringProviderInterface;
use WpPack\Component\Monitoring\ProviderSettings;

/**
 * Provides sample monitoring providers with mock data for local development.
 */
final class MockMonitoringProvider implements MonitoringProviderInterface
{
    public function getProviders(): array
    {
        return [
            new MonitoringProvider(
                id: 'mock-ses',
                label: 'SES (Mock)',
                bridge: 'mock',
                settings: new ProviderSettings(region: 'ap-northeast-1'),
                metrics: [
                    new MetricDefinition(id: 'mock.ses.send', label: 'Emails Sent', description: 'Total number of emails sent', namespace: 'AWS/SES', metricName: 'Send', unit: 'Count', stat: 'Sum', locked: true),
                    new MetricDefinition(id: 'mock.ses.delivery', label: 'Delivered', description: 'Emails successfully delivered to recipient mail server', namespace: 'AWS/SES', metricName: 'Delivery', unit: 'Count', stat: 'Sum', locked: true),
                    new MetricDefinition(id: 'mock.ses.bounce', label: 'Bounces', description: 'Hard and soft bounces from recipient mail servers', namespace: 'AWS/SES', metricName: 'Bounce', unit: 'Count', stat: 'Sum', locked: true),
                    new MetricDefinition(id: 'mock.ses.complaint', label: 'Complaints', description: 'Emails marked as spam by recipients', namespace: 'AWS/SES', metricName: 'Complaint', unit: 'Count', stat: 'Sum', locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-rds',
                label: 'RDS MySQL (Mock)',
                bridge: 'mock',
                settings: new ProviderSettings(region: 'ap-northeast-1'),
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
        ];
    }
}
