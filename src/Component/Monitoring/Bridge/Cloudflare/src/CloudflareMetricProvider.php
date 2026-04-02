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

        $data = $this->fetchZoneAnalytics($apiToken, $zoneId, $range, $adaptiveMinutes);

        if ($data === null) {
            throw new \RuntimeException('Failed to fetch Cloudflare analytics.');
        }

        return $this->mapResults($provider, $data);
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

    /**
     * Determine the adaptive time group interval in minutes.
     */
    private function resolveAdaptiveMinutes(int $rangeSeconds): int
    {
        return match (true) {
            $rangeSeconds <= 21_600 => 5,     // ≤ 6h: 5 min
            $rangeSeconds <= 86_400 => 15,    // ≤ 1d: 15 min
            $rangeSeconds <= 259_200 => 60,   // ≤ 3d: 1 hour
            default => 360,                   // > 3d: 6 hours
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
    ): ?array {
        $query = <<<'GRAPHQL'
query ZoneAnalytics($zoneTag: string!, $since: Time!, $until: Time!, $limit: Int!) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      httpRequestsAdaptiveGroups(
        filter: { datetime_geq: $since, datetime_lt: $until }
        limit: $limit
        orderBy: [datetimeFiveMinutes_ASC]
      ) {
        dimensions {
          datetimeFiveMinutes
        }
        sum {
          requests
          cachedRequests
          bytes
          cachedBytes
          threats
          pageViews
        }
        count
      }
    }
  }
}
GRAPHQL;

        // Adjust datetime field based on adaptive interval
        $datetimeField = match ($adaptiveMinutes) {
            5 => 'datetimeFiveMinutes',
            15 => 'datetimeFifteenMinutes',
            60 => 'datetimeHour',
            default => 'datetimeHour',
        };

        $query = str_replace('datetimeFiveMinutes', $datetimeField, $query);

        // Adjust orderBy field
        $orderByField = match ($adaptiveMinutes) {
            5 => 'datetimeFiveMinutes_ASC',
            15 => 'datetimeFifteenMinutes_ASC',
            60 => 'datetimeHour_ASC',
            default => 'datetimeHour_ASC',
        };

        $query = str_replace('datetimeFiveMinutes_ASC', $orderByField, $query);

        $maxPoints = (int) ceil(($range->end->getTimestamp() - $range->start->getTimestamp()) / ($adaptiveMinutes * 60));

        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'query' => $query,
                'variables' => [
                    'zoneTag' => $zoneId,
                    'since' => $range->start->format(\DateTimeInterface::ATOM),
                    'until' => $range->end->format(\DateTimeInterface::ATOM),
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
            return null;
        }

        $errors = $body['errors'] ?? [];
        if (\is_array($errors) && $errors !== []) {
            $msg = $errors[0]['message'] ?? 'Unknown Cloudflare API error';
            throw new \RuntimeException('Cloudflare API error: ' . $msg);
        }

        return $body['data']['viewer']['zones'][0]['httpRequestsAdaptiveGroups'] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $groups
     * @return list<MetricResult>
     */
    private function mapResults(MonitoringProvider $provider, array $groups): array
    {
        /** @var array<string, list<MetricPoint>> $pointsByMetric */
        $pointsByMetric = [];

        foreach ($groups as $group) {
            $dimensions = $group['dimensions'] ?? [];
            $sum = $group['sum'] ?? [];
            $tsString = $dimensions[array_key_first($dimensions)] ?? null;

            if (!\is_string($tsString)) {
                continue;
            }

            $ts = new \DateTimeImmutable($tsString);

            // Map Cloudflare fields to metric names
            $fieldMap = [
                'requests' => (float) ($sum['requests'] ?? 0),
                'cachedRequests' => (float) ($sum['cachedRequests'] ?? 0),
                'bandwidth' => (float) ($sum['bytes'] ?? 0),
                'cachedBandwidth' => (float) ($sum['cachedBytes'] ?? 0),
                'threats' => (float) ($sum['threats'] ?? 0),
                'pageViews' => (float) ($sum['pageViews'] ?? 0),
                'status2xx' => 0.0,
                'status4xx' => 0.0,
                'status5xx' => 0.0,
                'wafBlocked' => 0.0,
            ];

            // HTTP status codes from responseStatusMap if available
            $statusMap = $group['sum']['responseStatusMap'] ?? [];
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
}
