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
use WpPack\Component\Monitoring\MetricPoint;
use WpPack\Component\Monitoring\MetricProviderInterface;
use WpPack\Component\Monitoring\MetricResult;
use WpPack\Component\Monitoring\MetricTimeRange;
use WpPack\Component\Monitoring\MonitoringProvider;

/**
 * Cloudflare Analytics GraphQL API bridge.
 *
 * Uses httpRequestsAdaptiveGroups for zone analytics and
 * firewallEventsAdaptiveGroups for WAF metrics.
 */
final class CloudflareMetricProvider implements MetricProviderInterface
{
    private const API_URL = 'https://api.cloudflare.com/client/v4/graphql';

    public function getName(): string
    {
        return 'cloudflare';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @return list<MetricResult>
     */
    public function query(MonitoringProvider $provider, MetricTimeRange $range): array
    {
        if ($provider->metrics === [] || !$provider->settings instanceof CloudflareProviderSettings) {
            return [];
        }

        $zoneId = $this->extractZoneId($provider);
        if ($zoneId === '') {
            return [];
        }

        $apiToken = $provider->settings->apiToken;
        if ($apiToken === '') {
            return [];
        }

        $rangeSeconds = $range->end->getTimestamp() - $range->start->getTimestamp();
        $adaptiveMinutes = $this->resolveAdaptiveMinutes($rangeSeconds);

        // Determine which datasets are needed based on metric namespaces
        $needsZone = false;
        $needsWaf = false;
        foreach ($provider->metrics as $metric) {
            if ($metric->namespace === 'Cloudflare/WAF') {
                $needsWaf = true;
            } else {
                $needsZone = true;
            }
        }

        $zoneGroups = [];
        $wafGroups = [];

        if ($needsZone) {
            $dataset = $this->resolveZoneDataset($rangeSeconds);
            $zoneGroups = $this->fetchZoneAnalytics($apiToken, $zoneId, $range, $adaptiveMinutes, $dataset) ?? [];
        }

        if ($needsWaf) {
            $wafGroups = $this->fetchFirewallEvents($apiToken, $zoneId, $range, $adaptiveMinutes) ?? [];
        }

        return $this->mapResults($provider, $zoneGroups, $wafGroups);
    }

    private function extractZoneId(MonitoringProvider $provider): string
    {
        foreach ($provider->metrics as $metric) {
            $zoneId = $metric->dimensions['ZoneId'] ?? '';
            if ($zoneId !== '') {
                return $zoneId;
            }
        }

        return '';
    }

    private function resolveAdaptiveMinutes(int $rangeSeconds): int
    {
        return match (true) {
            $rangeSeconds <= 3_600 => 1,       // ≤ 1h: 1 min
            $rangeSeconds <= 21_600 => 5,      // ≤ 6h: 5 min
            $rangeSeconds <= 86_400 => 15,     // ≤ 1d: 15 min
            $rangeSeconds <= 259_200 => 60,    // ≤ 3d: 1 hour
            default => 360,                    // > 3d: 6 hours
        };
    }

    /**
     * Resolve the appropriate Cloudflare zone dataset based on time range.
     *
     * - httpRequests1mGroups: up to ~24h of data (1-minute granularity)
     * - httpRequests1hGroups: up to ~30 days (1-hour granularity)
     * - httpRequests1dGroups: longer periods (1-day granularity)
     */
    private function resolveZoneDataset(int $rangeSeconds): string
    {
        return match (true) {
            $rangeSeconds <= 43_200 => 'httpRequests1mGroups',  // ≤ 12h: 1m data
            $rangeSeconds <= 259_200 => 'httpRequests1hGroups', // ≤ 3d: 1h data
            default => 'httpRequests1dGroups',                  // > 3d: 1d data
        };
    }

    private function resolveDatetimeField(int $adaptiveMinutes, string $dataset): string
    {
        // 1d groups use "date"
        if ($dataset === 'httpRequests1dGroups') {
            return 'date';
        }

        // 1h groups use "datetime"
        if ($dataset === 'httpRequests1hGroups') {
            return 'datetime';
        }

        // 1m groups support datetimeMinute, datetimeFifteenMinutes, datetimeHour
        if ($dataset === 'httpRequests1mGroups') {
            return match ($adaptiveMinutes) {
                1, 5 => 'datetimeMinute',
                15 => 'datetimeFifteenMinutes',
                default => 'datetimeHour',
            };
        }

        // Adaptive groups (firewall etc.) support datetimeFiveMinutes, datetimeFifteenMinutes, datetimeHour
        return match ($adaptiveMinutes) {
            1, 5 => 'datetimeFiveMinutes',
            15 => 'datetimeFifteenMinutes',
            default => 'datetimeHour',
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function fetchZoneAnalytics(
        #[\SensitiveParameter]
        string $apiToken,
        string $zoneId,
        MetricTimeRange $range,
        int $adaptiveMinutes,
        string $dataset,
    ): ?array {
        $dtField = $this->resolveDatetimeField($adaptiveMinutes, $dataset);
        $isDaily = $dataset === 'httpRequests1dGroups';
        $varType = $isDaily ? 'Date' : 'Time';
        $filterField = $isDaily ? 'date' : 'datetime';

        $query = <<<GRAPHQL
query ZoneAnalytics(\$zoneTag: string!, \$since: {$varType}!, \$until: {$varType}!, \$limit: Int!) {
  viewer {
    zones(filter: { zoneTag: \$zoneTag }) {
      {$dataset}(
        filter: { {$filterField}_geq: \$since, {$filterField}_lt: \$until }
        limit: \$limit
        orderBy: [{$dtField}_ASC]
      ) {
        dimensions {
          {$dtField}
        }
        sum {
          requests
          cachedRequests
          bytes
          cachedBytes
          encryptedBytes
          encryptedRequests
          threats
          pageViews
          responseStatusMap {
            edgeResponseStatus
            requests
          }
        }
        uniq {
          uniques
        }
      }
    }
  }
}
GRAPHQL;

        $result = $this->executeQuery($apiToken, $query, $zoneId, $range, $adaptiveMinutes, $isDaily);

        return $result['data']['viewer']['zones'][0][$dataset] ?? null;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function fetchFirewallEvents(
        #[\SensitiveParameter]
        string $apiToken,
        string $zoneId,
        MetricTimeRange $range,
        int $adaptiveMinutes,
    ): ?array {
        $dtField = $this->resolveDatetimeField($adaptiveMinutes, 'firewallEventsAdaptiveGroups');

        $query = <<<GRAPHQL
query FirewallAnalytics(\$zoneTag: string!, \$since: Time!, \$until: Time!, \$limit: Int!) {
  viewer {
    zones(filter: { zoneTag: \$zoneTag }) {
      total: firewallEventsAdaptiveGroups(
        filter: { datetime_geq: \$since, datetime_lt: \$until }
        limit: \$limit
        orderBy: [{$dtField}_ASC]
      ) {
        dimensions { {$dtField} }
        count
      }
      blocked: firewallEventsAdaptiveGroups(
        filter: { datetime_geq: \$since, datetime_lt: \$until, action: "block" }
        limit: \$limit
        orderBy: [{$dtField}_ASC]
      ) {
        dimensions { {$dtField} }
        count
      }
      challenged: firewallEventsAdaptiveGroups(
        filter: { datetime_geq: \$since, datetime_lt: \$until, action: "js_challenge" }
        limit: \$limit
        orderBy: [{$dtField}_ASC]
      ) {
        dimensions { {$dtField} }
        count
      }
      managedChallenge: firewallEventsAdaptiveGroups(
        filter: { datetime_geq: \$since, datetime_lt: \$until, action: "managed_challenge" }
        limit: \$limit
        orderBy: [{$dtField}_ASC]
      ) {
        dimensions { {$dtField} }
        count
      }
    }
  }
}
GRAPHQL;

        $result = $this->executeQuery($apiToken, $query, $zoneId, $range, $adaptiveMinutes);
        $zones = $result['data']['viewer']['zones'][0] ?? null;

        if ($zones === null) {
            return null;
        }

        // Merge aliased results into a unified timeline
        return $this->mergeFirewallGroups($zones);
    }

    /**
     * @param array<string, mixed> $zones
     * @return list<array<string, mixed>>
     */
    private function mergeFirewallGroups(array $zones): array
    {
        /** @var array<string, array<string, float>> $timeline */
        $timeline = [];

        foreach (['total', 'blocked', 'challenged', 'managedChallenge'] as $alias) {
            $groups = $zones[$alias] ?? [];
            if (!\is_array($groups)) {
                continue;
            }
            foreach ($groups as $group) {
                $dims = $group['dimensions'] ?? [];
                $ts = $dims[array_key_first($dims)] ?? null;
                if (!\is_string($ts)) {
                    continue;
                }
                $timeline[$ts][$alias] = (float) ($group['count'] ?? 0);
            }
        }

        $merged = [];
        foreach ($timeline as $ts => $counts) {
            $merged[] = [
                'timestamp' => $ts,
                'wafTotal' => $counts['total'] ?? 0.0,
                'wafBlocked' => $counts['blocked'] ?? 0.0,
                'wafChallenged' => $counts['challenged'] ?? 0.0,
                'wafManagedChallenge' => $counts['managedChallenge'] ?? 0.0,
            ];
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function executeQuery(
        #[\SensitiveParameter]
        string $apiToken,
        string $query,
        string $zoneId,
        MetricTimeRange $range,
        int $adaptiveMinutes,
        bool $dateOnly = false,
    ): array {
        $maxPoints = (int) ceil(($range->end->getTimestamp() - $range->start->getTimestamp()) / ($adaptiveMinutes * 60));
        $dateFormat = $dateOnly ? 'Y-m-d' : \DateTimeInterface::ATOM;

        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'query' => $query,
                'variables' => [
                    'zoneTag' => $zoneId,
                    'since' => $range->start->format($dateFormat),
                    'until' => $range->end->format($dateFormat),
                    'limit' => min($maxPoints, 10000),
                ],
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('Cloudflare API request failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!\is_array($body)) {
            throw new \RuntimeException('Failed to parse Cloudflare API response.');
        }

        $errors = $body['errors'] ?? [];
        if (\is_array($errors) && $errors !== []) {
            $msg = $errors[0]['message'] ?? 'Unknown Cloudflare API error';
            throw new \RuntimeException('Cloudflare API error: ' . $msg);
        }

        return $body;
    }

    /**
     * @param list<array<string, mixed>> $zoneGroups
     * @param list<array<string, mixed>> $wafGroups
     * @return list<MetricResult>
     */
    private function mapResults(MonitoringProvider $provider, array $zoneGroups, array $wafGroups): array
    {
        /** @var array<string, list<MetricPoint>> $pointsByMetric */
        $pointsByMetric = [];

        // Map zone analytics
        foreach ($zoneGroups as $group) {
            $dimensions = $group['dimensions'] ?? [];
            $sum = $group['sum'] ?? [];
            $uniques = $group['uniq'] ?? [];
            $tsString = $dimensions[array_key_first($dimensions)] ?? null;

            if (!\is_string($tsString)) {
                continue;
            }

            $ts = new \DateTimeImmutable($tsString);

            $fieldMap = [
                'requests' => (float) ($sum['requests'] ?? 0),
                'cachedRequests' => (float) ($sum['cachedRequests'] ?? 0),
                'bandwidth' => (float) ($sum['bytes'] ?? 0),
                'cachedBandwidth' => (float) ($sum['cachedBytes'] ?? 0),
                'encryptedRequests' => (float) ($sum['encryptedRequests'] ?? 0),
                'encryptedBandwidth' => (float) ($sum['encryptedBytes'] ?? 0),
                'threats' => (float) ($sum['threats'] ?? 0),
                'pageViews' => (float) ($sum['pageViews'] ?? 0),
                'uniques' => (float) ($uniques['uniques'] ?? 0),
                'status2xx' => 0.0,
                'status4xx' => 0.0,
                'status5xx' => 0.0,
            ];

            $statusMap = $sum['responseStatusMap'] ?? [];
            if (\is_array($statusMap)) {
                foreach ($statusMap as $entry) {
                    $code = (int) ($entry['edgeResponseStatus'] ?? 0);
                    $count = (float) ($entry['requests'] ?? 0);
                    if ($code >= 200 && $code < 300) {
                        $fieldMap['status2xx'] += $count;
                    } elseif ($code >= 400 && $code < 500) {
                        $fieldMap['status4xx'] += $count;
                    } elseif ($code >= 500) {
                        $fieldMap['status5xx'] += $count;
                    }
                }
            }

            $this->addPoints($pointsByMetric, $provider, $fieldMap, $ts);
        }

        // Map WAF events
        foreach ($wafGroups as $group) {
            $tsString = $group['timestamp'] ?? null;

            if (!\is_string($tsString)) {
                continue;
            }

            $ts = new \DateTimeImmutable($tsString);

            $fieldMap = [
                'wafTotal' => (float) ($group['wafTotal'] ?? 0),
                'wafBlocked' => (float) ($group['wafBlocked'] ?? 0),
                'wafChallenged' => (float) ($group['wafChallenged'] ?? 0),
                'wafManagedChallenge' => (float) ($group['wafManagedChallenge'] ?? 0),
            ];

            $this->addPoints($pointsByMetric, $provider, $fieldMap, $ts);
        }

        $now = new \DateTimeImmutable();
        $results = [];

        foreach ($provider->metrics as $metric) {
            $points = $pointsByMetric[$metric->id] ?? [];
            usort($points, static fn(MetricPoint $a, MetricPoint $b): int => $a->timestamp <=> $b->timestamp);

            $results[] = new MetricResult(
                sourceId: $metric->id,
                label: $metric->label,
                unit: $metric->unit,
                group: $provider->id,
                datapoints: $points,
                fetchedAt: $now,
            );
        }

        return $results;
    }

    /**
     * @param array<string, list<MetricPoint>> $pointsByMetric
     * @param array<string, float> $fieldMap
     */
    private function addPoints(array &$pointsByMetric, MonitoringProvider $provider, array $fieldMap, \DateTimeImmutable $ts): void
    {
        foreach ($provider->metrics as $metric) {
            $value = $fieldMap[$metric->metricName] ?? null;
            if ($value === null) {
                continue;
            }

            $pointsByMetric[$metric->id][] = new MetricPoint(
                timestamp: $ts,
                value: $value,
                stat: $metric->stat,
            );
        }
    }
}
