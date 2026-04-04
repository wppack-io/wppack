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

namespace WpPack\Component\Monitoring\Bridge\Cloudflare;

use WpPack\Component\Monitoring\CloudflareProviderSettings;
use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringProviderInterface;

/**
 * Provides sample Cloudflare monitoring providers with mock data for local development.
 */
final class MockCloudflareMonitoringProvider implements MonitoringProviderInterface
{
    public function getProviders(): array
    {
        return [
            new MonitoringProvider(
                id: 'mock-cf-zone',
                label: 'Cloudflare Zone (Mock)',
                bridge: 'mock-cloudflare',
                settings: new CloudflareProviderSettings(apiToken: 'mock-token'),
                metrics: [
                    new MetricDefinition(id: 'mock.cf.requests', label: 'Requests', description: 'Total HTTP requests', namespace: 'Cloudflare/Analytics', metricName: 'requests', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.cached_requests', label: 'Cached Requests', description: 'Requests served from cache', namespace: 'Cloudflare/Analytics', metricName: 'cachedRequests', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.cache_rate', label: 'Cache Rate', description: 'Percentage of requests served from cache', namespace: 'Cloudflare/Analytics', metricName: 'cacheRate', unit: 'Percent', stat: 'Average', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.bandwidth', label: 'Data Transfer', description: 'Total data transfer', namespace: 'Cloudflare/Analytics', metricName: 'bandwidth', unit: 'Bytes', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.cached_bandwidth', label: 'Cached Transfer', description: 'Data transfer served from cache', namespace: 'Cloudflare/Analytics', metricName: 'cachedBandwidth', unit: 'Bytes', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.threats', label: 'Threats', description: 'Total threats blocked', namespace: 'Cloudflare/Analytics', metricName: 'threats', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.page_views', label: 'Page Views', description: 'Total page views', namespace: 'Cloudflare/Analytics', metricName: 'pageViews', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.uniques', label: 'Unique Visitors', description: 'Unique visitors', namespace: 'Cloudflare/Analytics', metricName: 'uniques', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.status_2xx', label: '2xx Responses', description: 'Successful responses', namespace: 'Cloudflare/Analytics', metricName: 'status2xx', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.status_3xx', label: '3xx Redirects', description: 'Redirect responses', namespace: 'Cloudflare/Analytics', metricName: 'status3xx', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.status_4xx', label: '4xx Errors', description: 'Client error responses', namespace: 'Cloudflare/Analytics', metricName: 'status4xx', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.status_5xx', label: '5xx Errors', description: 'Server error responses', namespace: 'Cloudflare/Analytics', metricName: 'status5xx', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                ],
                locked: true,
            ),
            new MonitoringProvider(
                id: 'mock-cf-waf',
                label: 'Cloudflare WAF (Mock)',
                bridge: 'mock-cloudflare',
                settings: new CloudflareProviderSettings(apiToken: 'mock-token'),
                metrics: [
                    new MetricDefinition(id: 'mock.cf.waf_total', label: 'WAF Events', description: 'Total firewall events', namespace: 'Cloudflare/WAF', metricName: 'wafTotal', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.waf_blocked', label: 'WAF Blocked', description: 'Requests blocked by WAF', namespace: 'Cloudflare/WAF', metricName: 'wafBlocked', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.waf_challenged', label: 'JS Challenged', description: 'Requests given JS challenge', namespace: 'Cloudflare/WAF', metricName: 'wafChallenged', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                    new MetricDefinition(id: 'mock.cf.waf_managed', label: 'Managed Challenge', description: 'Requests given managed challenge', namespace: 'Cloudflare/WAF', metricName: 'wafManagedChallenge', unit: 'Count', stat: 'Sum', dimensions: ['ZoneId' => 'mock-zone'], locked: true),
                ],
                locked: true,
            ),
        ];
    }
}
