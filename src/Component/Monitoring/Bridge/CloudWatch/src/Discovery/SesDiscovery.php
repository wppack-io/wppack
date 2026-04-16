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

use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringProviderInterface;
use WpPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;

final class SesDiscovery implements MonitoringProviderInterface
{
    public function getProviders(): array
    {
        $dsn = $this->resolveMailerDsn();

        if ($dsn === '') {
            return [];
        }

        $scheme = parse_url($dsn, \PHP_URL_SCHEME);

        if (!\is_string($scheme) || ($scheme !== 'ses' && !str_starts_with($scheme, 'ses+'))) {
            return [];
        }

        $region = $this->extractRegionFromDsn($dsn);

        return [
            new MonitoringProvider(
                id: 'ses',
                label: 'SES (Email)',
                bridge: 'cloudwatch',
                settings: new AwsProviderSettings(region: $region),
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

    private function resolveMailerDsn(): string
    {
        if (\defined('MAILER_DSN')) {
            return (string) \constant('MAILER_DSN');
        }

        return $_ENV['MAILER_DSN'] ?? '';
    }

    /**
     * Extract region from SES DSN host (email.{region}.amazonaws.com).
     *
     * Falls back to AWS_DEFAULT_REGION or us-east-1.
     */
    private function extractRegionFromDsn(string $dsn): string
    {
        $host = parse_url($dsn, \PHP_URL_HOST);

        if (\is_string($host) && preg_match('/^email\.([a-z0-9-]+)\.amazonaws\.com$/', $host, $matches) === 1) {
            return $matches[1];
        }

        return $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
    }
}
