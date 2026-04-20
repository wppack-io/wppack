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
use WPPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MetricProviderInterface;
use WPPack\Component\Monitoring\MetricResult;
use WPPack\Component\Monitoring\MetricTimeRange;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringStore;
use WPPack\Component\Monitoring\ProviderSettings;
use WPPack\Component\Option\OptionManager;

#[CoversClass(MonitoringStore::class)]
final class MonitoringStoreTest extends TestCase
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
     * Build a bridge whose settings class knows about sensitive fields.
     * We use AwsProviderSettings directly: it exposes accessKeyId +
     * secretAccessKey as sensitiveFields(), which is exactly what the
     * masking logic needs to exercise.
     */
    private function awsBridge(): MetricProviderInterface
    {
        return new class implements MetricProviderInterface {
            public function getName(): string
            {
                return 'cloudwatch';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function getLabel(): string
            {
                return 'AWS CloudWatch';
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
                return [new MetricResult('x', 'x', 'Count', $provider->id)];
            }

            public function getSettingsClass(): string
            {
                return AwsProviderSettings::class;
            }
        };
    }

    // ── getProviders (hydration) ─────────────────────────────────────

    #[Test]
    public function emptyStoreReturnsEmptyList(): void
    {
        $store = new MonitoringStore($this->options);

        self::assertSame([], $store->getProviders());
    }

    #[Test]
    public function corruptOptionIsToleratedAsEmpty(): void
    {
        $this->options->update(self::OPTION, 'not-an-array');

        self::assertSame([], (new MonitoringStore($this->options))->getProviders());
    }

    #[Test]
    public function hydratesFromRawEntry(): void
    {
        $this->options->update(self::OPTION, [
            [
                'id' => 'p1',
                'label' => 'My Provider',
                'bridge' => 'cloudwatch',
                'settings' => ['region' => 'us-east-1', 'accessKeyId' => 'AKIA', 'secretAccessKey' => 'S'],
                'metrics' => [[
                    'id' => 'cpu',
                    'label' => 'CPU',
                    'metricName' => 'CPUUtilization',
                    'namespace' => 'AWS/EC2',
                    'unit' => 'Percent',
                    'stat' => 'Average',
                    'dimensions' => ['InstanceId' => 'i-1'],
                    'periodSeconds' => 60,
                    'locked' => true,
                ]],
                'locked' => false,
                'templateId' => 'aws-ec2',
            ],
        ]);

        $providers = (new MonitoringStore($this->options, [$this->awsBridge()]))->getProviders();

        self::assertCount(1, $providers);
        $p = $providers[0];
        self::assertSame('p1', $p->id);
        self::assertSame('My Provider', $p->label);
        self::assertSame('cloudwatch', $p->bridge);
        self::assertInstanceOf(AwsProviderSettings::class, $p->settings);
        self::assertSame('us-east-1', $p->settings->region);
        self::assertSame('aws-ec2', $p->templateId);
        self::assertCount(1, $p->metrics);
        self::assertSame('cpu', $p->metrics[0]->id);
        self::assertSame(60, $p->metrics[0]->periodSeconds);
        self::assertTrue($p->metrics[0]->locked);
        self::assertSame(['InstanceId' => 'i-1'], $p->metrics[0]->dimensions);
    }

    #[Test]
    public function hydrateFallsBackToBaseSettingsWhenBridgeUnknown(): void
    {
        $this->options->update(self::OPTION, [[
            'id' => 'p1',
            'label' => 'Orphan',
            'bridge' => 'unregistered',
            'settings' => ['foo' => 'bar'],
        ]]);

        $providers = (new MonitoringStore($this->options))->getProviders();

        self::assertInstanceOf(ProviderSettings::class, $providers[0]->settings);
        self::assertSame([], $providers[0]->settings->toArray());
    }

    #[Test]
    public function hydrateIgnoresNonArrayEntries(): void
    {
        $this->options->update(self::OPTION, [[
            'id' => 'p1',
            'label' => 'ok',
            'bridge' => 'cloudwatch',
        ], 'corrupt']);

        $providers = (new MonitoringStore($this->options))->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('p1', $providers[0]->id);
    }

    #[Test]
    public function hydrateIgnoresNonArrayMetricEntries(): void
    {
        $this->options->update(self::OPTION, [[
            'id' => 'p1',
            'label' => 'ok',
            'bridge' => 'cloudwatch',
            'metrics' => [['id' => 'cpu'], 'invalid', 42],
        ]]);

        $providers = (new MonitoringStore($this->options))->getProviders();

        self::assertCount(1, $providers[0]->metrics);
        self::assertInstanceOf(MetricDefinition::class, $providers[0]->metrics[0]);
    }

    #[Test]
    public function hydrateCoercesNonArrayDimensions(): void
    {
        $this->options->update(self::OPTION, [[
            'id' => 'p1',
            'label' => 'ok',
            'bridge' => 'cloudwatch',
            'metrics' => [['id' => 'cpu', 'dimensions' => 'not-an-array']],
        ]]);

        $providers = (new MonitoringStore($this->options))->getProviders();

        self::assertSame([], $providers[0]->metrics[0]->dimensions);
    }

    // ── saveProvider ────────────────────────────────────────────────

    #[Test]
    public function saveProviderAppendsNewEntry(): void
    {
        $store = new MonitoringStore($this->options);

        $store->saveProvider(['id' => 'p1', 'label' => 'First', 'bridge' => 'cloudwatch']);

        self::assertSame([[
            'id' => 'p1',
            'label' => 'First',
            'bridge' => 'cloudwatch',
        ]], $this->options->get(self::OPTION));
    }

    #[Test]
    public function saveProviderIgnoresEntryWithoutId(): void
    {
        $store = new MonitoringStore($this->options);

        $store->saveProvider(['label' => 'no id']);

        self::assertSame(false, $this->options->get(self::OPTION, false));
    }

    #[Test]
    public function saveProviderReplacesExistingEntryWithSameId(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider(['id' => 'p1', 'label' => 'original', 'bridge' => 'cloudwatch']);
        $store->saveProvider(['id' => 'p1', 'label' => 'updated', 'bridge' => 'cloudwatch']);

        $raw = $this->options->get(self::OPTION);

        self::assertCount(1, $raw);
        self::assertSame('updated', $raw[0]['label']);
    }

    #[Test]
    public function saveProviderPreservesExistingSettingsWhenNoneSupplied(): void
    {
        $store = new MonitoringStore($this->options, [$this->awsBridge()]);

        $store->saveProvider([
            'id' => 'p1',
            'label' => 'aws',
            'bridge' => 'cloudwatch',
            'settings' => ['region' => 'us-east-1', 'accessKeyId' => 'AKIA', 'secretAccessKey' => 'S'],
        ]);

        // Second save: no settings → previous values kept.
        $store->saveProvider(['id' => 'p1', 'label' => 'aws', 'bridge' => 'cloudwatch']);

        $raw = $this->options->get(self::OPTION);
        self::assertSame('us-east-1', $raw[0]['settings']['region']);
        self::assertSame('AKIA', $raw[0]['settings']['accessKeyId']);
        self::assertSame('S', $raw[0]['settings']['secretAccessKey']);
    }

    #[Test]
    public function saveProviderRestoresSensitiveFieldsFromMaskedInput(): void
    {
        $store = new MonitoringStore($this->options, [$this->awsBridge()]);

        $store->saveProvider([
            'id' => 'p1',
            'label' => 'aws',
            'bridge' => 'cloudwatch',
            'settings' => ['region' => 'us-east-1', 'accessKeyId' => 'AKIA-original', 'secretAccessKey' => 'S-original'],
        ]);

        // Second save: client sent masked value back → keep original.
        $store->saveProvider([
            'id' => 'p1',
            'label' => 'aws',
            'bridge' => 'cloudwatch',
            'settings' => ['region' => 'us-west-2', 'accessKeyId' => '********', 'secretAccessKey' => ''],
        ]);

        $raw = $this->options->get(self::OPTION);
        self::assertSame('us-west-2', $raw[0]['settings']['region'], 'non-sensitive field should update');
        self::assertSame('AKIA-original', $raw[0]['settings']['accessKeyId'], 'masked input must not overwrite secret');
        self::assertSame('S-original', $raw[0]['settings']['secretAccessKey']);
    }

    #[Test]
    public function saveProviderAcceptsChangedSensitiveFields(): void
    {
        $store = new MonitoringStore($this->options, [$this->awsBridge()]);

        $store->saveProvider([
            'id' => 'p1',
            'label' => 'aws',
            'bridge' => 'cloudwatch',
            'settings' => ['region' => 'us-east-1', 'accessKeyId' => 'AKIA', 'secretAccessKey' => 'S'],
        ]);

        $store->saveProvider([
            'id' => 'p1',
            'label' => 'aws',
            'bridge' => 'cloudwatch',
            'settings' => ['region' => 'us-east-1', 'accessKeyId' => 'AKIA-new', 'secretAccessKey' => 'S-new'],
        ]);

        $raw = $this->options->get(self::OPTION);
        self::assertSame('AKIA-new', $raw[0]['settings']['accessKeyId']);
        self::assertSame('S-new', $raw[0]['settings']['secretAccessKey']);
    }

    // ── deleteProvider ──────────────────────────────────────────────

    #[Test]
    public function deleteProviderRemovesMatchingEntry(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider(['id' => 'p1', 'label' => 'a', 'bridge' => 'cloudwatch']);
        $store->saveProvider(['id' => 'p2', 'label' => 'b', 'bridge' => 'cloudwatch']);

        $store->deleteProvider('p1');

        $raw = $this->options->get(self::OPTION);
        self::assertCount(1, $raw);
        self::assertSame('p2', $raw[0]['id']);
    }

    #[Test]
    public function deleteProviderIsNoopForMissingId(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider(['id' => 'p1', 'label' => 'a', 'bridge' => 'cloudwatch']);

        $store->deleteProvider('does-not-exist');

        self::assertCount(1, $this->options->get(self::OPTION));
    }

    // ── saveMetric / deleteMetric ───────────────────────────────────

    #[Test]
    public function saveMetricAppendsToProvider(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider(['id' => 'p1', 'label' => 'a', 'bridge' => 'cloudwatch', 'metrics' => []]);

        $store->saveMetric('p1', ['id' => 'cpu', 'label' => 'CPU']);

        $raw = $this->options->get(self::OPTION);
        self::assertCount(1, $raw[0]['metrics']);
        self::assertSame('cpu', $raw[0]['metrics'][0]['id']);
    }

    #[Test]
    public function saveMetricReplacesExistingMetric(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'p1',
            'label' => 'a',
            'bridge' => 'cloudwatch',
            'metrics' => [['id' => 'cpu', 'label' => 'original']],
        ]);

        $store->saveMetric('p1', ['id' => 'cpu', 'label' => 'updated']);

        $raw = $this->options->get(self::OPTION);
        self::assertSame('updated', $raw[0]['metrics'][0]['label']);
    }

    #[Test]
    public function saveMetricIgnoresEmptyId(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider(['id' => 'p1', 'label' => 'a', 'bridge' => 'cloudwatch', 'metrics' => []]);

        $store->saveMetric('p1', ['label' => 'no id']);

        self::assertSame([], $this->options->get(self::OPTION)[0]['metrics']);
    }

    #[Test]
    public function saveMetricIgnoresMissingProvider(): void
    {
        $store = new MonitoringStore($this->options);

        $store->saveMetric('does-not-exist', ['id' => 'cpu']);

        // saveMetric unconditionally persists (still writing the
        // unchanged empty list), but the provider list stays empty —
        // the metric is not attached to a phantom entry.
        self::assertSame([], $this->options->get(self::OPTION));
    }

    #[Test]
    public function saveMetricCoercesNonArrayMetricsListToEmpty(): void
    {
        $this->options->update(self::OPTION, [[
            'id' => 'p1',
            'label' => 'a',
            'bridge' => 'cloudwatch',
            'metrics' => 'corrupt',
        ]]);

        (new MonitoringStore($this->options))->saveMetric('p1', ['id' => 'cpu']);

        $raw = $this->options->get(self::OPTION);
        self::assertSame([['id' => 'cpu']], $raw[0]['metrics']);
    }

    #[Test]
    public function deleteMetricRemovesByIdOnly(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'p1',
            'label' => 'a',
            'bridge' => 'cloudwatch',
            'metrics' => [['id' => 'cpu'], ['id' => 'mem']],
        ]);

        $store->deleteMetric('p1', 'cpu');

        $raw = $this->options->get(self::OPTION);
        self::assertCount(1, $raw[0]['metrics']);
        self::assertSame('mem', $raw[0]['metrics'][0]['id']);
    }

    #[Test]
    public function deleteMetricNoopWhenMetricsNotArray(): void
    {
        $this->options->update(self::OPTION, [[
            'id' => 'p1',
            'label' => 'a',
            'bridge' => 'cloudwatch',
            'metrics' => 'corrupt',
        ]]);

        (new MonitoringStore($this->options))->deleteMetric('p1', 'cpu');

        self::assertSame('corrupt', $this->options->get(self::OPTION)[0]['metrics']);
    }

    // ── syncMetrics ────────────────────────────────────────────────

    #[Test]
    public function syncMetricsReturnsFalseWhenAlreadyInSync(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'p1',
            'label' => 'a',
            'bridge' => 'cloudwatch',
            'metrics' => [[
                'id' => 'p1.cpu',
                'metricName' => 'CPUUtilization',
                'label' => 'CPU',
                'description' => 'desc',
                'namespace' => 'AWS/EC2',
                'unit' => 'Percent',
                'stat' => 'Average',
                'dimensions' => ['InstanceId' => 'i-1'],
                'periodSeconds' => 300,
                'locked' => false,
            ]],
        ]);

        $result = $store->syncMetrics('p1', [[
            'metricName' => 'CPUUtilization',
            'label' => 'CPU',
            'description' => 'desc',
            'namespace' => 'AWS/EC2',
            'unit' => 'Percent',
            'stat' => 'Average',
        ]]);

        self::assertFalse($result);
    }

    #[Test]
    public function syncMetricsAddsNewMetricsFromTemplate(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'p1',
            'label' => 'a',
            'bridge' => 'cloudwatch',
            'metrics' => [[
                'id' => 'p1.cpu',
                'metricName' => 'CPUUtilization',
                'label' => 'CPU',
                'description' => '',
                'namespace' => 'AWS/EC2',
                'unit' => 'Percent',
                'stat' => 'Average',
                'dimensions' => ['InstanceId' => 'i-1'],
            ]],
        ]);

        $result = $store->syncMetrics('p1', [
            ['metricName' => 'CPUUtilization', 'label' => 'CPU', 'description' => '', 'namespace' => 'AWS/EC2', 'unit' => 'Percent', 'stat' => 'Average'],
            ['metricName' => 'NetworkIn', 'label' => 'Network In', 'description' => '', 'namespace' => 'AWS/EC2', 'unit' => 'Bytes', 'stat' => 'Sum'],
        ]);

        self::assertTrue($result);
        $raw = $this->options->get(self::OPTION);
        self::assertCount(2, $raw[0]['metrics']);
        $names = array_column($raw[0]['metrics'], 'metricName');
        self::assertContains('NetworkIn', $names);
    }

    #[Test]
    public function syncMetricsReturnsTrueWhenLabelsDiverge(): void
    {
        $store = new MonitoringStore($this->options);
        $store->saveProvider([
            'id' => 'p1',
            'label' => 'a',
            'bridge' => 'cloudwatch',
            'metrics' => [[
                'id' => 'p1.cpu',
                'metricName' => 'CPUUtilization',
                'label' => 'Old Label',
                'description' => '',
                'namespace' => 'AWS/EC2',
                'unit' => 'Percent',
                'stat' => 'Average',
            ]],
        ]);

        $result = $store->syncMetrics('p1', [[
            'metricName' => 'CPUUtilization',
            'label' => 'New Label',
            'description' => '',
            'namespace' => 'AWS/EC2',
            'unit' => 'Percent',
            'stat' => 'Average',
        ]]);

        self::assertTrue($result);
        $raw = $this->options->get(self::OPTION);
        self::assertSame('New Label', $raw[0]['metrics'][0]['label']);
    }

    #[Test]
    public function syncMetricsReturnsFalseForMissingProvider(): void
    {
        $store = new MonitoringStore($this->options);

        self::assertFalse($store->syncMetrics('missing', []));
    }
}
