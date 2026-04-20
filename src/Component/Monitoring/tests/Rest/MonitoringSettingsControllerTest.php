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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Monitoring\MonitoringStore;
use WPPack\Component\Monitoring\Rest\MonitoringSettingsController;
use WPPack\Component\Option\OptionManager;

#[CoversClass(MonitoringSettingsController::class)]
final class MonitoringSettingsControllerTest extends TestCase
{
    private const OPTION = 'wppack_monitoring_providers';

    private OptionManager $options;

    protected function setUp(): void
    {
        $this->options = new OptionManager();
        $this->options->delete(self::OPTION);
    }

    protected function tearDown(): void
    {
        $this->options->delete(self::OPTION);
    }

    /**
     * @param list<MonitoringProvider> $providers
     */
    private function controller(array $providers = []): MonitoringSettingsController
    {
        $registry = new MonitoringRegistry();
        foreach ($providers as $p) {
            $registry->addProvider($p);
        }

        return new MonitoringSettingsController(
            new MonitoringStore($this->options),
            $registry,
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body = []): \WP_REST_Request
    {
        $req = new \WP_REST_Request();
        $req->set_header('content-type', 'application/json');
        $req->set_body(json_encode($body, \JSON_THROW_ON_ERROR));

        return $req;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\WPPack\Component\HttpFoundation\Response $response): array
    {
        /** @var array<string, mixed> */
        return json_decode($response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    private function provider(string $id, bool $locked = false): MonitoringProvider
    {
        return new MonitoringProvider(
            id: $id,
            label: 'Provider ' . $id,
            bridge: 'cloudwatch',
            settings: new AwsProviderSettings(
                region: 'us-east-1',
                accessKeyId: 'AKIA-SECRET',
                secretAccessKey: 'S-SECRET',
            ),
            locked: $locked,
        );
    }

    // ── listProviders ────────────────────────────────────────────────

    #[Test]
    public function listProvidersMasksSensitiveFields(): void
    {
        $response = $this->controller([$this->provider('p1')])->listProviders();

        self::assertSame(200, $response->statusCode);
        $body = $this->decode($response);
        self::assertSame('us-east-1', $body['providers'][0]['settings']['region']);
        self::assertSame('********', $body['providers'][0]['settings']['accessKeyId']);
        self::assertSame('********', $body['providers'][0]['settings']['secretAccessKey']);
    }

    #[Test]
    public function listProvidersKeepsEmptySensitiveFieldsBlank(): void
    {
        $provider = new MonitoringProvider(
            id: 'p2',
            label: 'Empty creds',
            bridge: 'cloudwatch',
            settings: new AwsProviderSettings(region: 'us-east-1'),
        );

        $body = $this->decode($this->controller([$provider])->listProviders());

        self::assertSame('', $body['providers'][0]['settings']['accessKeyId']);
        self::assertSame('', $body['providers'][0]['settings']['secretAccessKey']);
    }

    // ── addProvider ─────────────────────────────────────────────────

    #[Test]
    public function addProviderReturns201OnSuccess(): void
    {
        $response = $this->controller()->addProvider($this->request([
            'id' => 'p1',
            'label' => 'My Provider',
            'bridge' => 'cloudwatch',
        ]));

        self::assertSame(201, $response->statusCode);
        self::assertCount(1, $this->options->get(self::OPTION));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidProviderPayloadProvider(): iterable
    {
        yield 'missing id' => [['label' => 'x', 'bridge' => 'cloudwatch'], 'Provider id is required'];
        yield 'invalid id' => [['id' => 'bad id!', 'label' => 'x', 'bridge' => 'cloudwatch'], 'alphanumeric'];
        yield 'missing label' => [['id' => 'p1', 'bridge' => 'cloudwatch'], 'Label is required'];
        yield 'label too long' => [['id' => 'p1', 'label' => str_repeat('x', 201), 'bridge' => 'cloudwatch'], 'Label is required'];
        yield 'missing bridge' => [['id' => 'p1', 'label' => 'x'], 'Bridge is required'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Test]
    #[DataProvider('invalidProviderPayloadProvider')]
    public function addProviderRejectsInvalidPayload(array $payload, string $expectedMessage): void
    {
        $response = $this->controller()->addProvider($this->request($payload));

        self::assertSame(400, $response->statusCode);
        self::assertStringContainsString($expectedMessage, $this->decode($response)['error']);
    }

    // ── updateProvider ──────────────────────────────────────────────

    #[Test]
    public function updateProviderPersistsValidChanges(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider(['id' => 'p1', 'label' => 'Original', 'bridge' => 'cloudwatch']);

        $response = $this->controller()->updateProvider($this->request([
            'id' => 'p1',
            'label' => 'Updated',
            'bridge' => 'cloudwatch',
        ]));

        self::assertSame(200, $response->statusCode);
        $raw = $this->options->get(self::OPTION);
        self::assertSame('Updated', $raw[0]['label']);
    }

    #[Test]
    public function updateProviderRejectsLockedProviderWith403(): void
    {
        $controller = $this->controller([$this->provider('locked', locked: true)]);

        $response = $controller->updateProvider($this->request([
            'id' => 'locked',
            'label' => 'new',
            'bridge' => 'cloudwatch',
        ]));

        self::assertSame(403, $response->statusCode);
    }

    // ── deleteProvider ──────────────────────────────────────────────

    #[Test]
    public function deleteProviderRemovesEntryAndReturns204(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider(['id' => 'p1', 'label' => 'x', 'bridge' => 'cloudwatch']);

        $response = $this->controller()->deleteProvider($this->request(['id' => 'p1']));

        self::assertSame(204, $response->statusCode);
        self::assertSame([], $this->options->get(self::OPTION));
    }

    #[Test]
    public function deleteProviderRejectsMissingId(): void
    {
        $response = $this->controller()->deleteProvider($this->request([]));

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function deleteProviderRejectsLockedProvider(): void
    {
        $response = $this->controller([$this->provider('locked', locked: true)])
            ->deleteProvider($this->request(['id' => 'locked']));

        self::assertSame(403, $response->statusCode);
    }

    // ── addMetric / updateMetric / deleteMetric ─────────────────────

    #[Test]
    public function addMetricReturns201OnValid(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider(['id' => 'p1', 'label' => 'x', 'bridge' => 'cloudwatch', 'metrics' => []]);

        $response = $this->controller()->addMetric($this->request([
            'providerId' => 'p1',
            'metric' => [
                'id' => 'cpu',
                'label' => 'CPU',
                'namespace' => 'AWS/EC2',
                'metricName' => 'CPUUtilization',
            ],
        ]));

        self::assertSame(201, $response->statusCode);
    }

    #[Test]
    public function addMetricRejectsInvalidProviderId(): void
    {
        $response = $this->controller()->addMetric($this->request([
            'providerId' => 'invalid id!',
            'metric' => [],
        ]));

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function addMetricRejectsMissingLabelOrNamespaceOrName(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider(['id' => 'p1', 'label' => 'x', 'bridge' => 'cloudwatch']);

        $response = $this->controller()->addMetric($this->request([
            'providerId' => 'p1',
            'metric' => ['id' => 'cpu'],  // missing label / namespace / metricName
        ]));

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function addMetricRejectsLockedProvider(): void
    {
        $response = $this->controller([$this->provider('locked', locked: true)])
            ->addMetric($this->request([
                'providerId' => 'locked',
                'metric' => ['id' => 'x', 'label' => 'x', 'namespace' => 'n', 'metricName' => 'm'],
            ]));

        self::assertSame(403, $response->statusCode);
    }

    #[Test]
    public function updateMetricReturns200(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'p1', 'label' => 'x', 'bridge' => 'cloudwatch',
            'metrics' => [['id' => 'cpu', 'label' => 'Old', 'namespace' => 'N', 'metricName' => 'M']],
        ]);

        $response = $this->controller()->updateMetric($this->request([
            'providerId' => 'p1',
            'metric' => ['id' => 'cpu', 'label' => 'New', 'namespace' => 'N', 'metricName' => 'M'],
        ]));

        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function deleteMetricReturns204(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'p1', 'label' => 'x', 'bridge' => 'cloudwatch',
            'metrics' => [['id' => 'cpu']],
        ]);

        $response = $this->controller()->deleteMetric($this->request([
            'providerId' => 'p1',
            'metricId' => 'cpu',
        ]));

        self::assertSame(204, $response->statusCode);
    }

    #[Test]
    public function deleteMetricRejectsInvalidIds(): void
    {
        $response = $this->controller()->deleteMetric($this->request([
            'providerId' => '',
            'metricId' => '',
        ]));

        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function deleteMetricRejectsLockedProvider(): void
    {
        $response = $this->controller([$this->provider('locked', locked: true)])
            ->deleteMetric($this->request([
                'providerId' => 'locked',
                'metricId' => 'cpu',
            ]));

        self::assertSame(403, $response->statusCode);
    }
}
