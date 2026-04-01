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

namespace WpPack\Plugin\AmazonMailerPlugin\Monitoring;

use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringProviderInterface;
use WpPack\Component\Monitoring\ProviderSettings;

final class SesMonitoringProvider implements MonitoringProviderInterface
{
    public function getProviders(): array
    {
        $region = $this->resolveRegion();

        return [
            new MonitoringProvider(
                id: 'ses',
                label: 'SES (Email)',
                bridge: 'cloudwatch',
                settings: new ProviderSettings(region: $region),
                metrics: [
                    new MetricDefinition(
                        id: 'ses.send',
                        label: 'Emails Sent',
                        description: 'Total number of emails sent',
                        namespace: 'AWS/SES',
                        metricName: 'Send',
                        unit: 'Count',
                        stat: 'Sum',
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'ses.delivery',
                        label: 'Delivered',
                        description: 'Emails successfully delivered to recipient mail server',
                        namespace: 'AWS/SES',
                        metricName: 'Delivery',
                        unit: 'Count',
                        stat: 'Sum',
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'ses.bounce',
                        label: 'Bounces',
                        description: 'Hard and soft bounces from recipient mail servers',
                        namespace: 'AWS/SES',
                        metricName: 'Bounce',
                        unit: 'Count',
                        stat: 'Sum',
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'ses.complaint',
                        label: 'Complaints',
                        description: 'Emails marked as spam by recipients',
                        namespace: 'AWS/SES',
                        metricName: 'Complaint',
                        unit: 'Count',
                        stat: 'Sum',
                        locked: true,
                    ),
                    new MetricDefinition(
                        id: 'ses.reject',
                        label: 'Rejected',
                        description: 'Emails rejected by SES (policy or virus)',
                        namespace: 'AWS/SES',
                        metricName: 'Reject',
                        unit: 'Count',
                        stat: 'Sum',
                        locked: true,
                    ),
                ],
                locked: true,
            ),
        ];
    }

    private function resolveRegion(): string
    {
        if (\defined('WPPACK_MONITORING_SES_REGION')) {
            return (string) \constant('WPPACK_MONITORING_SES_REGION');
        }

        return $_ENV['WPPACK_MONITORING_SES_REGION'] ?? $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
    }
}
