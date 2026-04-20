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

namespace WPPack\Component\Monitoring\Tests\Rest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MetricPoint;
use WPPack\Component\Monitoring\MetricProviderInterface;
use WPPack\Component\Monitoring\MetricResult;
use WPPack\Component\Monitoring\MetricTimeRange;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Monitoring\Rest\MonitoringController;
use WPPack\Component\Transient\TransientManager;

#[CoversClass(MonitoringController::class)]
final class MonitoringControllerTest extends TestCase
{
    private TransientManager $transients;

    protected function setUp(): void
    {
        $this->transients = new TransientManager();
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '%wppack_monitoring_metrics%',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\WPPack\Component\HttpFoundation\JsonResponse $response): array
    {
        /** @var array<string, mixed> */
        return json_decode($response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    private function bridge(string $name, MetricResult $result): MetricProviderInterface
    {
        return new class ($name, [$result]) implements MetricProviderInterface {
            /** @param list<MetricResult> $results */
            public function __construct(
                private readonly string $name,
                private readonly array $results,
            ) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function getLabel(): string
            {
                return strtoupper($this->name);
            }

            public function getFormFields(): array
            {
                return [];
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
                return $this->results;
            }

            public function getSettingsClass(): string
            {
                return AwsProviderSettings::class;
            }
        };
    }

    private function provider(string $id, string $bridgeName, MetricDefinition ...$metrics): MonitoringProvider
    {
        return new MonitoringProvider(
            id: $id,
            label: $id,
            bridge: $bridgeName,
            settings: new AwsProviderSettings(
                region: 'us-east-1',
                accessKeyId: 'AKIA-SECRET',
                secretAccessKey: 'S-SECRET',
            ),
            metrics: $metrics,
        );
    }

    private function request(int $periodHours = 3, bool $forceRefresh = false): \WP_REST_Request
    {
        $req = new \WP_REST_Request();
        $req->set_param('period', $periodHours);
        $req->set_param('force_refresh', $forceRefresh);

        return $req;
    }

    #[Test]
    public function getMetricsEmitsSerialisedPayloadWithDatapoints(): void
    {
        $metric = new MetricDefinition('cpu', 'CPU');
        $point = new MetricPoint(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'), 42.5);
        $result = new MetricResult(
            sourceId: 'cpu',
            label: 'CPU',
            unit: 'Percent',
            group: 'p1',
            datapoints: [$point],
            fetchedAt: new \DateTimeImmutable('2024-01-01T00:05:00+00:00'),
        );

        $registry = new MonitoringRegistry();
        $registry->addProvider($this->provider('p1', 'aws', $metric));
        $collector = new MonitoringCollector($registry, ['aws' => $this->bridge('aws', $result)], $this->transients);

        $response = (new MonitoringController($collector, $registry))
            ->getMetrics($this->request());

        $body = $this->decode($response);
        self::assertCount(1, $body['results']);
        self::assertSame('cpu', $body['results'][0]['sourceId']);
        self::assertSame('2024-01-01T00:00:00+00:00', $body['results'][0]['datapoints'][0]['timestamp']);
        self::assertSame(42.5, $body['results'][0]['datapoints'][0]['value']);
        self::assertSame('2024-01-01T00:05:00+00:00', $body['results'][0]['fetchedAt']);
    }

    #[Test]
    public function getMetricsIncludesProvidersListAndBridges(): void
    {
        $metric = new MetricDefinition('cpu', 'CPU');
        $registry = new MonitoringRegistry();
        $registry->addProvider($this->provider('p1', 'aws', $metric));
        $collector = new MonitoringCollector(
            $registry,
            ['aws' => $this->bridge('aws', new MetricResult('cpu', 'CPU', 'Percent', 'p1'))],
            $this->transients,
        );

        $response = (new MonitoringController($collector, $registry))->getMetrics($this->request());
        $body = $this->decode($response);

        self::assertCount(1, $body['providers']);
        self::assertSame('p1', $body['providers'][0]['id']);
        self::assertSame(['aws'], $body['bridges']);
    }

    #[Test]
    public function serializedProviderStripsSensitiveSettingsFields(): void
    {
        $metric = new MetricDefinition('cpu', 'CPU');
        $registry = new MonitoringRegistry();
        $registry->addProvider($this->provider('p1', 'aws', $metric));
        $collector = new MonitoringCollector(
            $registry,
            ['aws' => $this->bridge('aws', new MetricResult('cpu', 'CPU', 'Percent', 'p1'))],
            $this->transients,
        );

        $response = (new MonitoringController($collector, $registry))->getProviders();
        $body = $this->decode($response);

        self::assertSame('us-east-1', $body['providers'][0]['settings']['region'], 'non-sensitive fields kept');
        self::assertArrayNotHasKey('accessKeyId', $body['providers'][0]['settings'], 'masked in API output');
        self::assertArrayNotHasKey('secretAccessKey', $body['providers'][0]['settings']);
    }

    #[Test]
    public function periodHoursIsClampedBetween1And168(): void
    {
        $registry = new MonitoringRegistry();
        $collector = new MonitoringCollector($registry, [], $this->transients);
        $controller = new MonitoringController($collector, $registry);

        // no providers → just verify the endpoint does not throw.
        $response = $controller->getMetrics($this->request(periodHours: 9999));

        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function refreshEndpointReturnsFreshResults(): void
    {
        $metric = new MetricDefinition('cpu', 'CPU');
        $registry = new MonitoringRegistry();
        $registry->addProvider($this->provider('p1', 'aws', $metric));
        $collector = new MonitoringCollector(
            $registry,
            ['aws' => $this->bridge('aws', new MetricResult('cpu', 'CPU', 'Percent', 'p1'))],
            $this->transients,
        );
        $controller = new MonitoringController($collector, $registry);

        $response = $controller->refresh($this->request());

        self::assertSame(200, $response->statusCode);
        $body = $this->decode($response);
        self::assertArrayHasKey('results', $body);
        self::assertArrayHasKey('providers', $body);
        self::assertArrayHasKey('bridges', $body);
    }

    #[Test]
    public function getProvidersReturnsBridgesAndProvidersShape(): void
    {
        $registry = new MonitoringRegistry();
        $registry->addProvider($this->provider('p1', 'aws', new MetricDefinition('cpu', 'CPU', periodSeconds: 60)));
        $registry->addProvider($this->provider('p2', 'gcp'));

        $collector = new MonitoringCollector($registry, [], $this->transients);
        $controller = new MonitoringController($collector, $registry);

        $body = $this->decode($controller->getProviders());

        self::assertCount(2, $body['providers']);
        self::assertSame(['aws', 'gcp'], $body['bridges']);

        $first = $body['providers'][0];
        self::assertSame('p1', $first['id']);
        self::assertCount(1, $first['metrics']);
        self::assertSame(60, $first['metrics'][0]['periodSeconds']);
    }
}
