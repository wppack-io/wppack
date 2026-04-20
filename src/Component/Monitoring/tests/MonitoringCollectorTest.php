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

namespace WPPack\Component\Monitoring\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\CollectableMetricProviderInterface;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MetricPoint;
use WPPack\Component\Monitoring\MetricProviderInterface;
use WPPack\Component\Monitoring\MetricResult;
use WPPack\Component\Monitoring\MetricTimeRange;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Monitoring\ProviderSettings;
use WPPack\Component\Transient\TransientManager;

#[CoversClass(MonitoringCollector::class)]
final class MonitoringCollectorTest extends TestCase
{
    private TransientManager $transients;

    private int $testSeed = 0;

    protected function setUp(): void
    {
        $this->transients = new TransientManager();
        // Give every test a unique time window so the collector's
        // range-derived cache key never collides between tests.
        $this->testSeed = \random_int(100_000, 999_999);
    }

    protected function tearDown(): void
    {
        global $wpdb;
        // Drop anything our collector might have stored so the next
        // test boot starts with a cold cache.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '%wppack_monitoring_metrics%',
        ));
    }

    private function registryWith(MonitoringProvider ...$providers): MonitoringRegistry
    {
        $registry = new MonitoringRegistry();
        foreach ($providers as $p) {
            $registry->addProvider($p);
        }

        return $registry;
    }

    private function provider(string $id, string $bridgeName, MetricDefinition ...$metrics): MonitoringProvider
    {
        return new MonitoringProvider(
            id: $id,
            label: $id,
            bridge: $bridgeName,
            settings: new ProviderSettings(),
            metrics: $metrics,
        );
    }

    private function range(int $startOffset = -3600, int $endOffset = 0): MetricTimeRange
    {
        // The test seed shifts the fixed anchor so every test (including
        // data-provider cases) runs with a cache key nobody else has used.
        $end = (new \DateTimeImmutable('2024-01-01T12:00:00+00:00'))->modify('+' . $this->testSeed . ' seconds');

        return new MetricTimeRange($end->modify($startOffset . ' seconds'), $end);
    }

    // ── query: bridge availability, caching, error handling ─────────

    #[Test]
    public function queryReturnsEmptyWhenNoProvidersRegistered(): void
    {
        $collector = new MonitoringCollector(
            new MonitoringRegistry(),
            [],
            $this->transients,
        );

        self::assertSame([], $collector->query($this->range()));
    }

    #[Test]
    public function queryDelegatesToMatchingBridge(): void
    {
        $metric = new MetricDefinition('cpu', 'CPU');
        $bridge = new InMemoryBridge('aws', [
            new MetricResult('cpu', 'CPU', 'Percent', 'p1', [new MetricPoint(new \DateTimeImmutable(), 42.0)]),
        ]);

        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'aws', $metric)),
            ['aws' => $bridge],
            $this->transients,
        );

        $results = $collector->query($this->range());

        self::assertCount(1, $results);
        self::assertSame('cpu', $results[0]->sourceId);
        self::assertSame(1, $bridge->callCount, 'bridge should be queried once');
    }

    #[Test]
    public function querySkipsProviderWhenBridgeNotRegistered(): void
    {
        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'unknown-bridge', new MetricDefinition('cpu', 'CPU'))),
            [],
            $this->transients,
        );

        self::assertSame([], $collector->query($this->range()));
    }

    #[Test]
    public function querySkipsProviderWhenBridgeReportsUnavailable(): void
    {
        $bridge = new InMemoryBridge('aws', [], available: false);
        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'aws', new MetricDefinition('cpu', 'CPU'))),
            ['aws' => $bridge],
            $this->transients,
        );

        self::assertSame([], $collector->query($this->range()));
        self::assertSame(0, $bridge->callCount);
    }

    #[Test]
    public function cachedResultsReturnedOnSecondCall(): void
    {
        $bridge = new InMemoryBridge('aws', [
            new MetricResult('cpu', 'CPU', 'Percent', 'p1'),
        ]);
        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'aws', new MetricDefinition('cpu', 'CPU'))),
            ['aws' => $bridge],
            $this->transients,
        );

        $range = $this->range();
        $collector->query($range);
        $collector->query($range);

        self::assertSame(1, $bridge->callCount, 'second query should be served from cache');
    }

    #[Test]
    public function forceRefreshBypassesCache(): void
    {
        $bridge = new InMemoryBridge('aws', [
            new MetricResult('cpu', 'CPU', 'Percent', 'p1'),
        ]);
        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'aws', new MetricDefinition('cpu', 'CPU'))),
            ['aws' => $bridge],
            $this->transients,
        );

        $range = $this->range();
        $collector->query($range);
        $collector->query($range, forceRefresh: true);

        self::assertSame(2, $bridge->callCount);
    }

    #[Test]
    public function queryErrorEmitsErrorResultPerMetricWithFriendlyMessage(): void
    {
        $bridge = new InMemoryBridge('aws', []);
        $bridge->throw = new \RuntimeException('MissingAuthenticationToken: bad key');

        $metric = new MetricDefinition('cpu', 'CPU', unit: 'Percent');
        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'aws', $metric)),
            ['aws' => $bridge],
            $this->transients,
        );

        $results = $collector->query($this->range());

        self::assertCount(1, $results);
        self::assertSame('cpu', $results[0]->sourceId);
        self::assertSame('AWS authentication failed. Check credentials.', $results[0]->error);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function errorMessageProvider(): iterable
    {
        yield 'missing auth' => ['MissingAuthenticationToken', 'AWS authentication failed. Check credentials.'];
        yield 'invalid signature' => ['InvalidSignature present', 'AWS authentication failed. Check credentials.'];
        yield 'access denied' => ['AccessDenied on bucket', 'Access denied. Check IAM permissions.'];
        yield 'unauthorized' => ['UnauthorizedAccess', 'Access denied. Check IAM permissions.'];
        yield 'curl error' => ['cURL error 6', 'Connection failed. Check network.'];
        yield 'resolve host' => ['Could not resolve host example', 'Connection failed. Check network.'];
        yield 'cloudflare specific' => ['Cloudflare API error: 403', 'Cloudflare API error: 403'];
        yield 'fallback' => ['unexpected weirdness', 'Metric query failed. Check provider configuration.'];
    }

    /**
     * @dataProvider errorMessageProvider
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('errorMessageProvider')]
    public function formatsCommonErrorMessages(string $raw, string $expected): void
    {
        $bridge = new InMemoryBridge('aws', []);
        $bridge->throw = new \RuntimeException($raw);
        $metric = new MetricDefinition('cpu', 'CPU');

        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'aws', $metric)),
            ['aws' => $bridge],
            $this->transients,
        );

        $results = $collector->query($this->range());

        self::assertSame($expected, $results[0]->error);
    }

    #[Test]
    public function cacheKeyDiffersForDifferentTimeWindows(): void
    {
        $bridge = new InMemoryBridge('aws', [new MetricResult('cpu', 'CPU', 'Percent', 'p1')]);
        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'aws', new MetricDefinition('cpu', 'CPU'))),
            ['aws' => $bridge],
            $this->transients,
        );

        $collector->query($this->range(-3600));
        $collector->query($this->range(-7200));

        self::assertSame(2, $bridge->callCount, 'different ranges should not share cache keys');
    }

    // ── runCollectors ───────────────────────────────────────────────

    #[Test]
    public function runCollectorsInvokesCollectableBridgesOnly(): void
    {
        $aws = new InMemoryCollectableBridge('aws', []);
        $plain = new InMemoryBridge('plain', []);

        $collector = new MonitoringCollector(
            $this->registryWith(
                $this->provider('p1', 'aws', new MetricDefinition('cpu', 'CPU')),
                $this->provider('p2', 'plain', new MetricDefinition('ram', 'RAM')),
            ),
            ['aws' => $aws, 'plain' => $plain],
            $this->transients,
        );

        $collector->runCollectors();

        self::assertSame(1, $aws->collectCount);
        // plain bridge does not implement CollectableMetricProviderInterface
        // so collect() is not invoked on it.
    }

    #[Test]
    public function runCollectorsSkipsUnavailableBridges(): void
    {
        $aws = new InMemoryCollectableBridge('aws', [], available: false);
        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'aws', new MetricDefinition('cpu', 'CPU'))),
            ['aws' => $aws],
            $this->transients,
        );

        $collector->runCollectors();

        self::assertSame(0, $aws->collectCount);
    }

    #[Test]
    public function runCollectorsSkipsProvidersWithoutMetrics(): void
    {
        $aws = new InMemoryCollectableBridge('aws', []);
        $collector = new MonitoringCollector(
            $this->registryWith($this->provider('p1', 'aws')),  // no metrics
            ['aws' => $aws],
            $this->transients,
        );

        $collector->runCollectors();

        self::assertSame(0, $aws->collectCount);
    }

    // ── getBridgeMetadata ───────────────────────────────────────────

    #[Test]
    public function bridgeMetadataExposesFormFieldsAndCredentialFieldIds(): void
    {
        $bridge = new InMemoryBridge('aws', [], available: true);
        $bridge->formFields = [
            ['id' => 'region', 'type' => 'text'],
            ['id' => 'accessKeyId', 'type' => 'text'],
            ['id' => 'secretAccessKey', 'type' => 'password'],
        ];

        $collector = new MonitoringCollector(
            new MonitoringRegistry(),
            ['aws' => $bridge],
            $this->transients,
        );

        $metadata = $collector->getBridgeMetadata();

        self::assertArrayHasKey('aws', $metadata);
        self::assertSame('aws', $metadata['aws']['name']);
        self::assertSame('AWS', $metadata['aws']['label']);
        self::assertSame(['region', 'accessKeyId', 'secretAccessKey'], $metadata['aws']['credentialFieldIds']);
        // credentials (accessKeyId / secretAccessKey) are filtered out of
        // requiredFields — only non-credential fields remain.
        self::assertSame(['region'], $metadata['aws']['requiredFields']);
    }
}

/**
 * In-memory metric bridge for testing.
 */
class InMemoryBridge implements MetricProviderInterface
{
    public int $callCount = 0;
    public ?\Throwable $throw = null;
    /** @var list<array<string, mixed>> */
    public array $formFields = [];

    /** @param list<MetricResult> $results */
    public function __construct(
        private readonly string $name,
        private readonly array $results,
        private readonly bool $available = true,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getLabel(): string
    {
        return strtoupper($this->name);
    }

    public function getFormFields(): array
    {
        return $this->formFields;
    }

    public function getTemplates(): array
    {
        return [];
    }

    public function getDimensionLabels(): array
    {
        return [];
    }

    public function getDefaultSettings(): array
    {
        return [];
    }

    public function getSetupGuide(): ?array
    {
        return null;
    }

    public function validateSettings(array $settings): bool
    {
        return true;
    }

    public function query(MonitoringProvider $provider, MetricTimeRange $range): array
    {
        $this->callCount++;

        if ($this->throw !== null) {
            throw $this->throw;
        }

        return $this->results;
    }

    public function getSettingsClass(): string
    {
        return ProviderSettings::class;
    }
}

final class InMemoryCollectableBridge extends InMemoryBridge implements CollectableMetricProviderInterface
{
    public int $collectCount = 0;

    public function collect(MonitoringProvider $provider): void
    {
        $this->collectCount++;
    }

    public function getCollectInterval(): int
    {
        return 300;
    }
}
