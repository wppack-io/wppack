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

use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Dsn\Exception\InvalidDsnException;
use WPPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringProviderInterface;

final class SesDiscovery implements MonitoringProviderInterface
{
    public function getProviders(): array
    {
        $dsnString = $this->resolveMailerDsn();

        if ($dsnString === '') {
            return [];
        }

        try {
            $dsn = Dsn::fromString($dsnString);
        } catch (InvalidDsnException) {
            return [];
        }

        $scheme = $dsn->getScheme();

        if ($scheme !== 'ses' && !str_starts_with($scheme, 'ses+')) {
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
    private function extractRegionFromDsn(Dsn $dsn): string
    {
        $host = $dsn->getHost();

        if ($host !== null && preg_match('/^email\.([a-z0-9-]+)\.amazonaws\.com$/', $host, $matches) === 1) {
            return $matches[1];
        }

        return $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
    }
}
