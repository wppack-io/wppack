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

namespace WPPack\Component\Monitoring\Bridge\Cloudflare;

use Psr\Log\LoggerInterface;
use WPPack\Component\Monitoring\Bridge\Cloudflare\CloudflareProviderSettings;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MetricPoint;
use WPPack\Component\Monitoring\MetricProviderInterface;
use WPPack\Component\Monitoring\MetricResult;
use WPPack\Component\Monitoring\MetricTimeRange;
use WPPack\Component\Monitoring\MonitoringProvider;

/**
 * Cloudflare Analytics GraphQL API bridge.
 *
 * Builds GraphQL queries dynamically based on the provider's metric definitions.
 * Only fields required by configured metrics are requested.
 */
final class CloudflareMetricProvider implements MetricProviderInterface
{
    private const API_URL = 'https://api.cloudflare.com/client/v4/graphql';

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Maps metricName → [source, field/config].
     *
     * Sources:
     * - 'sum'       → direct field in sum { ... }
     * - 'uniq'      → field in uniq { ... }
     * - 'computed'   → derived from other sum fields
     * - 'statusMap'  → aggregated from responseStatusMap
     * - 'waf'        → firewallEventsAdaptiveGroups alias (null = no action filter)
     *
     * @var array<string, array{0: string, 1: mixed}>
     */
    private const FIELD_MAP = [
        'requests'            => ['sum', 'requests'],
        'cachedRequests'      => ['sum', 'cachedRequests'],
        'bandwidth'           => ['sum', 'bytes'],
        'cachedBandwidth'     => ['sum', 'cachedBytes'],
        'threats'             => ['sum', 'threats'],
        'pageViews'           => ['sum', 'pageViews'],
        'uniques'             => ['uniq', 'uniques'],
        'cacheRate'           => ['computed', ['requests', 'cachedRequests']],
        'status2xx'           => ['statusMap', 200],
        'status3xx'           => ['statusMap', 300],
        'status4xx'           => ['statusMap', 400],
        'status5xx'           => ['statusMap', 500],
        'wafTotal'            => ['waf', null],
        'wafBlocked'          => ['waf', 'block'],
        'wafChallenged'       => ['waf', 'js_challenge'],
        'wafManagedChallenge' => ['waf', 'managed_challenge'],
    ];

    /**
     * WAF metric alias names (metricName → GraphQL alias).
     *
     * @var array<string, string>
     */
    private const WAF_ALIASES = [
        'wafTotal'            => 'total',
        'wafBlocked'          => 'blocked',
        'wafChallenged'       => 'challenged',
        'wafManagedChallenge' => 'managedChallenge',
    ];

    public function getName(): string
    {
        return 'cloudflare';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getLabel(): string
    {
        return 'Cloudflare';
    }

    public function getFormFields(): array
    {
        return [
            [
                'id' => 'settings.apiToken',
                'label' => 'API Token',
                'type' => 'password',
                'description' => 'Cloudflare API Token with Zone Analytics permission',
            ],
            [
                'id' => 'settings.hostname',
                'label' => 'Hostname Filter',
                'type' => 'text',
                'description' => 'Filter by hostname (e.g. example.com). Comma-separated for multiple. Wildcards not supported. Leave empty for all.',
            ],
        ];
    }

    public function getTemplates(): array
    {
        return [
            [
                'id' => 'cloudflare-zone',
                'label' => 'Cloudflare Zone',
                'namespace' => 'Cloudflare/Analytics',
                'dimensionKey' => 'ZoneId',
                'dimensionLabel' => 'Zone ID',
                'dimensionPlaceholder' => 'abc123def456ghi789',
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
                'label' => 'Cloudflare WAF',
                'namespace' => 'Cloudflare/WAF',
                'dimensionKey' => 'ZoneId',
                'dimensionLabel' => 'Zone ID',
                'dimensionPlaceholder' => 'abc123def456ghi789',
                'metrics' => [
                    ['metricName' => 'wafTotal', 'label' => 'WAF Events', 'description' => 'Total firewall events', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'wafBlocked', 'label' => 'WAF Blocked', 'description' => 'Requests blocked by WAF', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'wafChallenged', 'label' => 'JS Challenged', 'description' => 'Requests given JS challenge', 'stat' => 'Sum', 'unit' => 'Count'],
                    ['metricName' => 'wafManagedChallenge', 'label' => 'Managed Challenge', 'description' => 'Requests given managed challenge', 'stat' => 'Sum', 'unit' => 'Count'],
                ],
            ],
        ];
    }

    public function getDimensionLabels(): array
    {
        return ['ZoneId' => 'Zone ID'];
    }

    public function getDefaultSettings(): array
    {
        return ['apiToken' => '', 'hostname' => ''];
    }

    public function getSetupGuide(): array
    {
        return [
            'buttonLabel' => 'Cloudflare API Token',
            'title' => 'Cloudflare API Token Setup',
            'content' => [
                ['type' => 'paragraph', 'text' => 'Cloudflare analytics data is retrieved via API Token. We recommend creating an Account API Token, which allows monitoring all zones in the account with a single token.'],
                ['type' => 'heading', 'text' => 'Creating an Account API Token (Recommended)'],
                ['type' => 'steps', 'items' => [
                    'Go to the Cloudflare dashboard and navigate to <strong>My Profile → API Tokens</strong>',
                    'Click "Create Token"',
                    'Select "Create Custom Token"',
                    "Set the following permissions:\nAccount — Account Analytics — Read\nZone   — Analytics         — Read",
                    'Under "Account Resources", select the target account',
                    'Under "Zone Resources", select "All zones" (or specific zones)',
                    'Click "Continue to summary", then "Create Token"',
                    'Copy the token and paste it into the API Token field when adding a Cloudflare provider',
                ]],
                ['type' => 'note', 'text' => 'Tip: A single API Token can be reused across multiple Cloudflare providers (Zone analytics, WAF, etc.) as long as it has the required permissions.'],
                ['type' => 'heading', 'text' => 'Finding Your Zone ID'],
                ['type' => 'paragraph', 'text' => 'The Zone ID is shown on the right sidebar of your domain\'s Overview page in the Cloudflare dashboard, under the "API" section.'],
            ],
        ];
    }

    public function validateSettings(array $settings): bool
    {
        return !empty($settings['apiToken']);
    }

    /**
     * @return list<MetricResult>
     */
    public function query(MonitoringProvider $provider, MetricTimeRange $range): array
    {
        if ($provider->metrics === [] || !$provider->settings instanceof CloudflareProviderSettings) {
            $this->logger?->warning('Cloudflare provider "{id}": invalid settings type, skipping.', ['id' => $provider->id]);

            return [];
        }

        $zoneId = $this->extractZoneId($provider);
        if ($zoneId === '') {
            $this->logger?->warning('Cloudflare provider "{id}": missing ZoneId, skipping.', ['id' => $provider->id]);

            return [];
        }

        $apiToken = $provider->settings->apiToken;
        if ($apiToken === '') {
            $this->logger?->warning('Cloudflare provider "{id}": missing apiToken, skipping.', ['id' => $provider->id]);

            return [];
        }

        $rangeSeconds = $range->end->getTimestamp() - $range->start->getTimestamp();
        $adaptiveMinutes = $this->resolveAdaptiveMinutes($rangeSeconds);
        $hostnameFilter = $this->buildHostnameFilter($provider);

        // Analyze required fields from provider metrics
        $requirements = $this->analyzeRequirements($provider->metrics);

        $zoneGroups = [];
        $wafGroups = [];

        if ($requirements['needsZone']) {
            $dataset = $this->resolveZoneDataset($rangeSeconds);
            $maxChunkSeconds = $dataset === 'httpRequests1hGroups' ? 259_200 : 0;
            if ($maxChunkSeconds > 0 && $rangeSeconds > $maxChunkSeconds) {
                $zoneGroups = $this->fetchZoneAnalyticsChunked($apiToken, $zoneId, $range, $adaptiveMinutes, $dataset, $maxChunkSeconds, $requirements, $hostnameFilter);
            } else {
                $zoneGroups = $this->fetchZoneAnalytics($apiToken, $zoneId, $range, $adaptiveMinutes, $dataset, $requirements, $hostnameFilter) ?? [];
            }
        }

        if ($requirements['wafAliases'] !== []) {
            $wafGroups = $this->fetchFirewallEvents($apiToken, $zoneId, $range, $adaptiveMinutes, $requirements['wafAliases'], $hostnameFilter) ?? [];
        }

        return $this->mapResults($provider, $zoneGroups, $wafGroups);
    }

    // ──────────────────────────────────────────────
    // Requirement analysis
    // ──────────────────────────────────────────────

    /**
     * Analyze provider metrics to determine which GraphQL fields are needed.
     *
     * @param list<MetricDefinition> $metrics
     * @return array{needsZone: bool, sumFields: list<string>, needsUniq: bool, needsStatusMap: bool, wafAliases: array<string, string|null>}
     */
    private function analyzeRequirements(array $metrics): array
    {
        $sumFields = [];
        $needsUniq = false;
        $needsStatusMap = false;
        $needsZone = false;
        /** @var array<string, string|null> $wafAliases alias => action filter */
        $wafAliases = [];

        foreach ($metrics as $metric) {
            $mapping = self::FIELD_MAP[$metric->metricName] ?? null;
            if ($mapping === null) {
                // Unknown metric — try direct sum field
                $sumFields[] = $metric->metricName;
                $needsZone = true;

                continue;
            }

            [$source, $config] = $mapping;

            switch ($source) {
                case 'sum':
                    $sumFields[] = $config;
                    $needsZone = true;
                    break;
                case 'uniq':
                    $needsUniq = true;
                    $needsZone = true;
                    break;
                case 'computed':
                    // Add dependency fields
                    foreach ((array) $config as $dep) {
                        $depMapping = self::FIELD_MAP[$dep] ?? null;
                        if ($depMapping !== null && $depMapping[0] === 'sum') {
                            $sumFields[] = $depMapping[1];
                        }
                    }
                    $needsZone = true;
                    break;
                case 'statusMap':
                    $needsStatusMap = true;
                    $needsZone = true;
                    break;
                case 'waf':
                    $alias = self::WAF_ALIASES[$metric->metricName] ?? $metric->metricName;
                    $wafAliases[$alias] = $config;
                    break;
            }
        }

        return [
            'needsZone' => $needsZone,
            'sumFields' => array_values(array_unique($sumFields)),
            'needsUniq' => $needsUniq,
            'needsStatusMap' => $needsStatusMap,
            'wafAliases' => $wafAliases,
        ];
    }

    // ──────────────────────────────────────────────
    // Zone analytics
    // ──────────────────────────────────────────────

    /**
     * @param array{sumFields: list<string>, needsUniq: bool, needsStatusMap: bool, wafAliases: array<string, string|null>, needsZone: bool} $requirements
     * @return list<array<string, mixed>>|null
     */
    private function fetchZoneAnalytics(
        #[\SensitiveParameter]
        string $apiToken,
        string $zoneId,
        MetricTimeRange $range,
        int $adaptiveMinutes,
        string $dataset,
        array $requirements,
        string $hostnameFilter = '',
    ): ?array {
        $dtField = $this->resolveDatetimeField($adaptiveMinutes, $dataset);
        $sumBlock = $this->buildSumBlock($requirements);

        $query = <<<GRAPHQL
query ZoneAnalytics(\$zoneTag: string!, \$since: Time!, \$until: Time!, \$limit: Int!) {
  viewer {
    zones(filter: { zoneTag: \$zoneTag }) {
      {$dataset}(
        filter: { datetime_geq: \$since, datetime_lt: \$until{$hostnameFilter} }
        limit: \$limit
        orderBy: [{$dtField}_ASC]
      ) {
        dimensions {
          {$dtField}
        }
{$sumBlock}
      }
    }
  }
}
GRAPHQL;

        $result = $this->executeQuery($apiToken, $query, $zoneId, $range);

        return $result['data']['viewer']['zones'][0][$dataset] ?? null;
    }

    /**
     * @param array{sumFields: list<string>, needsUniq: bool, needsStatusMap: bool, wafAliases: array<string, string|null>, needsZone: bool} $requirements
     * @return list<array<string, mixed>>
     */
    private function fetchZoneAnalyticsChunked(
        #[\SensitiveParameter]
        string $apiToken,
        string $zoneId,
        MetricTimeRange $range,
        int $adaptiveMinutes,
        string $dataset,
        int $maxChunkSeconds,
        array $requirements,
        string $hostnameFilter = '',
    ): array {
        $allGroups = [];
        $chunkStart = $range->start;

        while ($chunkStart < $range->end) {
            $chunkEnd = $chunkStart->modify('+' . $maxChunkSeconds . ' seconds');
            if ($chunkEnd > $range->end) {
                $chunkEnd = $range->end;
            }

            $chunk = new MetricTimeRange($chunkStart, $chunkEnd);
            $groups = $this->fetchZoneAnalytics($apiToken, $zoneId, $chunk, $adaptiveMinutes, $dataset, $requirements, $hostnameFilter);
            if ($groups !== null) {
                $allGroups = [...$allGroups, ...$groups];
            }

            $chunkStart = $chunkEnd;
        }

        return $allGroups;
    }

    /**
     * Build the GraphQL sum/uniq block based on requirements.
     *
     * @param array{sumFields: list<string>, needsUniq: bool, needsStatusMap: bool} $requirements
     */
    private function buildSumBlock(array $requirements): string
    {
        $lines = [];

        if ($requirements['sumFields'] !== [] || $requirements['needsStatusMap']) {
            $lines[] = '        sum {';
            foreach ($requirements['sumFields'] as $field) {
                $lines[] = '          ' . $field;
            }
            if ($requirements['needsStatusMap']) {
                $lines[] = '          responseStatusMap {';
                $lines[] = '            edgeResponseStatus';
                $lines[] = '            requests';
                $lines[] = '          }';
            }
            $lines[] = '        }';
        }

        if ($requirements['needsUniq']) {
            $lines[] = '        uniq {';
            $lines[] = '          uniques';
            $lines[] = '        }';
        }

        return implode("\n", $lines);
    }

    // ──────────────────────────────────────────────
    // WAF / Firewall events
    // ──────────────────────────────────────────────

    /**
     * @param array<string, string|null> $wafAliases alias => action filter (null = no filter)
     * @return list<array<string, mixed>>|null
     */
    private function fetchFirewallEvents(
        #[\SensitiveParameter]
        string $apiToken,
        string $zoneId,
        MetricTimeRange $range,
        int $adaptiveMinutes,
        array $wafAliases,
        string $hostnameFilter = '',
    ): ?array {
        $rangeSeconds = $range->end->getTimestamp() - $range->start->getTimestamp();
        $maxChunkSeconds = 259_200;

        $chunks = [];
        $chunkStart = $range->start;
        while ($chunkStart < $range->end) {
            $chunkEnd = $chunkStart->modify('+' . $maxChunkSeconds . ' seconds');
            if ($chunkEnd > $range->end) {
                $chunkEnd = $range->end;
            }
            $chunks[] = new MetricTimeRange($chunkStart, $chunkEnd);
            $chunkStart = $chunkEnd;
        }

        $allMerged = [];
        foreach ($chunks as $chunk) {
            $chunkResult = $this->fetchFirewallEventsChunk($apiToken, $zoneId, $chunk, $adaptiveMinutes, $wafAliases, $hostnameFilter);
            if ($chunkResult !== null) {
                $allMerged = [...$allMerged, ...$chunkResult];
            }
        }

        return $allMerged !== [] ? $allMerged : null;
    }

    /**
     * @param array<string, string|null> $wafAliases
     * @return list<array<string, mixed>>|null
     */
    private function fetchFirewallEventsChunk(
        #[\SensitiveParameter]
        string $apiToken,
        string $zoneId,
        MetricTimeRange $range,
        int $adaptiveMinutes,
        array $wafAliases,
        string $hostnameFilter = '',
    ): ?array {
        $dtField = $this->resolveDatetimeField($adaptiveMinutes, 'firewallEventsAdaptiveGroups');
        $aliasQueries = $this->buildWafAliasQueries($wafAliases, $dtField, $hostnameFilter);

        $query = <<<GRAPHQL
query FirewallAnalytics(\$zoneTag: string!, \$since: Time!, \$until: Time!, \$limit: Int!) {
  viewer {
    zones(filter: { zoneTag: \$zoneTag }) {
{$aliasQueries}
    }
  }
}
GRAPHQL;

        $result = $this->executeQuery($apiToken, $query, $zoneId, $range);
        $zones = $result['data']['viewer']['zones'][0] ?? null;

        if ($zones === null) {
            return null;
        }

        return $this->mergeFirewallGroups($zones, array_keys($wafAliases));
    }

    /**
     * @param array<string, string|null> $wafAliases
     */
    private function buildWafAliasQueries(array $wafAliases, string $dtField, string $hostnameFilter = ''): string
    {
        $lines = [];

        foreach ($wafAliases as $alias => $actionFilter) {
            $filter = 'datetime_geq: $since, datetime_lt: $until';
            if ($actionFilter !== null) {
                $filter .= ', action: "' . $actionFilter . '"';
            }
            $filter .= $hostnameFilter;

            $lines[] = "      {$alias}: firewallEventsAdaptiveGroups(";
            $lines[] = "        filter: { {$filter} }";
            $lines[] = '        limit: $limit';
            $lines[] = "        orderBy: [{$dtField}_ASC]";
            $lines[] = '      ) {';
            $lines[] = "        dimensions { {$dtField} }";
            $lines[] = '        count';
            $lines[] = '      }';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $zones
     * @param list<string> $aliases
     * @return list<array<string, mixed>>
     */
    private function mergeFirewallGroups(array $zones, array $aliases): array
    {
        /** @var array<string, array<string, float>> $timeline */
        $timeline = [];

        foreach ($aliases as $alias) {
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
            $entry = ['timestamp' => $ts];
            foreach ($aliases as $alias) {
                // Map alias back to metricName
                $metricName = array_search($alias, self::WAF_ALIASES, true);
                $entry[$metricName !== false ? $metricName : $alias] = $counts[$alias] ?? 0.0;
            }
            $merged[] = $entry;
        }

        return $merged;
    }

    // ──────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────

    /**
     * Build the hostname filter fragment for GraphQL queries.
     *
     * Parses the hostname setting and returns the appropriate filter string:
     * - Empty string → no filter (empty return)
     * - Single hostname → clientRequestHTTPHost: "example.com"
     * - Multiple hostnames → clientRequestHTTPHost_in: ["a.com", "b.com"]
     */
    private function buildHostnameFilter(MonitoringProvider $provider): string
    {
        $hostname = $provider->settings instanceof CloudflareProviderSettings
            ? $provider->settings->hostname
            : '';

        if ($hostname === '') {
            return '';
        }

        $hosts = array_filter(array_map('trim', explode(',', $hostname)));

        if ($hosts === []) {
            return '';
        }

        if (\count($hosts) === 1) {
            return ', clientRequestHTTPHost: "' . $hosts[0] . '"';
        }

        $quoted = array_map(static fn(string $h): string => '"' . $h . '"', $hosts);

        return ', clientRequestHTTPHost_in: [' . implode(', ', $quoted) . ']';
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
            $rangeSeconds <= 3_600 => 1,
            $rangeSeconds <= 21_600 => 5,
            $rangeSeconds <= 86_400 => 15,
            $rangeSeconds <= 259_200 => 60,
            default => 360,
        };
    }

    private function resolveZoneDataset(int $rangeSeconds): string
    {
        return match (true) {
            $rangeSeconds <= 43_200 => 'httpRequests1mGroups',
            default => 'httpRequests1hGroups',
        };
    }

    private function resolveDatetimeField(int $adaptiveMinutes, string $dataset): string
    {
        if ($dataset === 'httpRequests1hGroups') {
            return 'datetime';
        }

        if ($dataset === 'httpRequests1mGroups') {
            return match ($adaptiveMinutes) {
                1, 5 => 'datetimeMinute',
                15 => 'datetimeFifteenMinutes',
                default => 'datetimeHour',
            };
        }

        return match ($adaptiveMinutes) {
            1, 5 => 'datetimeFiveMinutes',
            15 => 'datetimeFifteenMinutes',
            default => 'datetimeHour',
        };
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
    ): array {
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
                    'limit' => 10000,
                ],
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->logger?->error('Cloudflare API request failed: {error}', ['error' => $response->get_error_message()]);

            throw new \RuntimeException('Cloudflare API request failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!\is_array($body)) {
            $this->logger?->error('Failed to parse Cloudflare API response.');

            throw new \RuntimeException('Failed to parse Cloudflare API response.');
        }

        $errors = $body['errors'] ?? [];
        if (\is_array($errors) && $errors !== []) {
            $msg = $errors[0]['message'] ?? 'Unknown Cloudflare API error';
            $this->logger?->error('Cloudflare API error for zone "{zoneId}": {message}', [
                'zoneId' => $zoneId,
                'message' => $msg,
            ]);

            throw new \RuntimeException('Cloudflare API error: ' . $msg);
        }

        return $body;
    }

    // ──────────────────────────────────────────────
    // Result mapping
    // ──────────────────────────────────────────────

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
            $uniq = $group['uniq'] ?? [];
            $tsString = $dimensions[array_key_first($dimensions)] ?? null;

            if (!\is_string($tsString)) {
                continue;
            }

            $ts = new \DateTimeImmutable($tsString);

            // Pre-compute status counts if needed
            $statusCounts = null;
            $statusMap = $sum['responseStatusMap'] ?? [];
            if (\is_array($statusMap) && $statusMap !== []) {
                $statusCounts = ['status2xx' => 0.0, 'status3xx' => 0.0, 'status4xx' => 0.0, 'status5xx' => 0.0];
                foreach ($statusMap as $entry) {
                    $code = (int) ($entry['edgeResponseStatus'] ?? 0);
                    $count = (float) ($entry['requests'] ?? 0);
                    if ($code >= 200 && $code < 300) {
                        $statusCounts['status2xx'] += $count;
                    } elseif ($code >= 300 && $code < 400) {
                        $statusCounts['status3xx'] += $count;
                    } elseif ($code >= 400 && $code < 500) {
                        $statusCounts['status4xx'] += $count;
                    } elseif ($code >= 500) {
                        $statusCounts['status5xx'] += $count;
                    }
                }
            }

            foreach ($provider->metrics as $metric) {
                $mapping = self::FIELD_MAP[$metric->metricName] ?? null;

                if ($mapping === null) {
                    // Unknown metric — try direct sum field
                    $value = isset($sum[$metric->metricName]) ? (float) $sum[$metric->metricName] : null;
                } else {
                    [$source] = $mapping;
                    $value = match ($source) {
                        'sum' => isset($sum[$mapping[1]]) ? (float) $sum[$mapping[1]] : null,
                        'uniq' => isset($uniq[$mapping[1]]) ? (float) $uniq[$mapping[1]] : null,
                        'computed' => $this->computeValue($metric->metricName, $sum),
                        'statusMap' => $statusCounts[$metric->metricName] ?? null,
                        default => null,
                    };
                }

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

        // Map WAF events
        foreach ($wafGroups as $group) {
            $tsString = $group['timestamp'] ?? null;
            if (!\is_string($tsString)) {
                continue;
            }

            $ts = new \DateTimeImmutable($tsString);

            foreach ($provider->metrics as $metric) {
                $value = $group[$metric->metricName] ?? null;
                if ($value === null) {
                    continue;
                }

                $pointsByMetric[$metric->id][] = new MetricPoint(
                    timestamp: $ts,
                    value: (float) $value,
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

    /**
     * @param array<string, mixed> $sum
     */
    private function computeValue(string $metricName, array $sum): ?float
    {
        return match ($metricName) {
            'cacheRate' => (function () use ($sum): float {
                $total = (float) ($sum['requests'] ?? 0);
                $cached = (float) ($sum['cachedRequests'] ?? 0);

                return $total > 0 ? round($cached / $total * 100, 2) : 0.0;
            })(),
            default => null,
        };
    }

    public function getSettingsClass(): string
    {
        return CloudflareProviderSettings::class;
    }
}
