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

namespace WPPack\Plugin\MonitoringPlugin\Tests\Rest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WPPack\Component\Monitoring\MonitoringStore;
use WPPack\Component\Option\OptionManager;
use WPPack\Plugin\MonitoringPlugin\Rest\SyncTemplatesController;
use WPPack\Plugin\MonitoringPlugin\Template\MetricTemplateRegistry;

#[CoversClass(SyncTemplatesController::class)]
final class SyncTemplatesControllerTest extends TestCase
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

    private function controller(?LoggerInterface $logger = null): SyncTemplatesController
    {
        return new SyncTemplatesController(
            new MonitoringStore($this->options),
            new MetricTemplateRegistry(),
            $logger,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\WPPack\Component\HttpFoundation\JsonResponse $response): array
    {
        /** @var array<string, mixed> */
        return json_decode($response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function syncReturnsZeroWhenThereAreNoProviders(): void
    {
        $response = $this->controller()->sync();

        self::assertSame(200, $response->statusCode);
        self::assertSame(['updated' => 0], $this->decode($response));
    }

    #[Test]
    public function syncUpdatesProviderWhoseTemplateIdMatches(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'my-ec2',
            'label' => 'EC2 Production',
            'bridge' => 'cloudwatch',
            'templateId' => 'ec2',
            'metrics' => [
                ['id' => 'm1', 'metricName' => 'CPUUtilization', 'label' => 'old label', 'description' => 'old', 'namespace' => 'AWS/EC2', 'unit' => 'Percent', 'stat' => 'Average'],
            ],
        ]);

        $response = $this->controller()->sync();

        self::assertSame(200, $response->statusCode);
        self::assertSame(['updated' => 1], $this->decode($response));

        // All ec2 template metrics should now be present
        $raw = $this->options->get(self::OPTION);
        $metricNames = array_map(static fn(array $m): string => $m['metricName'], $raw[0]['metrics']);
        self::assertContains('CPUUtilization', $metricNames);
        self::assertContains('NetworkIn', $metricNames);
        self::assertContains('NetworkOut', $metricNames);
        self::assertContains('StatusCheckFailed', $metricNames);
    }

    #[Test]
    public function syncSkipsLockedProviders(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'system-ec2',
            'label' => 'System EC2',
            'bridge' => 'cloudwatch',
            'templateId' => 'ec2',
            'locked' => true,
            'metrics' => [],
        ]);

        $response = $this->controller()->sync();

        self::assertSame(['updated' => 0], $this->decode($response));
    }

    #[Test]
    public function syncSkipsProvidersWithUnknownTemplateId(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'weird',
            'label' => 'Weird',
            'bridge' => 'cloudwatch',
            'templateId' => 'no-such-template',
            'metrics' => [],
        ]);

        $response = $this->controller()->sync();

        self::assertSame(['updated' => 0], $this->decode($response));
    }

    #[Test]
    public function syncInfersTemplateByMatchingBridgeAndMetricNames(): void
    {
        $store = new MonitoringStore($this->options);

        // A provider without a templateId but with the complete ec2 metric set.
        $store->saveProvider([
            'id' => 'legacy-ec2',
            'label' => 'Legacy',
            'bridge' => 'cloudwatch',
            'metrics' => [
                ['id' => 'a', 'metricName' => 'CPUUtilization', 'label' => 'old', 'description' => 'old', 'namespace' => 'AWS/EC2', 'unit' => 'Percent', 'stat' => 'Average'],
                ['id' => 'b', 'metricName' => 'NetworkIn', 'label' => 'old', 'description' => 'old', 'namespace' => 'AWS/EC2', 'unit' => 'Bytes', 'stat' => 'Sum'],
                ['id' => 'c', 'metricName' => 'NetworkOut', 'label' => 'old', 'description' => 'old', 'namespace' => 'AWS/EC2', 'unit' => 'Bytes', 'stat' => 'Sum'],
                ['id' => 'd', 'metricName' => 'StatusCheckFailed', 'label' => 'old', 'description' => 'old', 'namespace' => 'AWS/EC2', 'unit' => 'Count', 'stat' => 'Maximum'],
            ],
        ]);

        $response = $this->controller()->sync();

        self::assertSame(['updated' => 1], $this->decode($response));

        $raw = $this->options->get(self::OPTION);
        self::assertSame('CPU Utilization', $raw[0]['metrics'][0]['label'], 'template label applied');
    }

    #[Test]
    public function syncReturnsZeroWhenMetricsAlreadyMatchTemplate(): void
    {
        $store = new MonitoringStore($this->options);

        // Pre-synced provider — second call should report 0 updates.
        $store->saveProvider([
            'id' => 'ec2-fresh',
            'label' => 'Fresh',
            'bridge' => 'cloudwatch',
            'templateId' => 'ec2',
            'metrics' => [],
        ]);

        $this->controller()->sync();
        $second = $this->controller()->sync();

        self::assertSame(['updated' => 0], $this->decode($second), 'idempotent on second run');
    }

    #[Test]
    public function syncLogsCountWhenLoggerProvided(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'x',
            'label' => 'X',
            'bridge' => 'cloudwatch',
            'templateId' => 'ec2',
            'metrics' => [],
        ]);

        $logged = null;
        $logger = new class ($logged) implements LoggerInterface {
            public function __construct(public ?string &$logged) {}

            public function emergency(string|\Stringable $message, array $context = []): void {}

            public function alert(string|\Stringable $message, array $context = []): void {}

            public function critical(string|\Stringable $message, array $context = []): void {}

            public function error(string|\Stringable $message, array $context = []): void {}

            public function warning(string|\Stringable $message, array $context = []): void {}

            public function notice(string|\Stringable $message, array $context = []): void {}

            public function info(string|\Stringable $message, array $context = []): void
            {
                $this->logged = (string) $message;
            }

            public function debug(string|\Stringable $message, array $context = []): void {}

            public function log($level, string|\Stringable $message, array $context = []): void {}
        };

        $this->controller($logger)->sync();

        self::assertNotNull($logger->logged);
        self::assertStringContainsString('Template sync completed', $logger->logged);
    }
}
