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

namespace WPPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DataCollector\MemoryDataCollector;

final class MemoryDataCollectorTest extends TestCase
{
    private MemoryDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new MemoryDataCollector();
    }

    #[Test]
    public function getNameReturnsMemory(): void
    {
        self::assertSame('memory', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsMemory(): void
    {
        self::assertSame('Memory', $this->collector->getLabel());
    }

    #[Test]
    public function takeSnapshotRecordsMemoryAtLabeledPoint(): void
    {
        $this->collector->takeSnapshot('before_load');
        $this->collector->takeSnapshot('after_load');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('snapshots', $data);
        self::assertArrayHasKey('before_load', $data['snapshots']);
        self::assertArrayHasKey('after_load', $data['snapshots']);
        self::assertIsInt($data['snapshots']['before_load']);
        self::assertIsInt($data['snapshots']['after_load']);
    }

    #[Test]
    public function collectGathersCurrentPeakAndLimit(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('current', $data);
        self::assertArrayHasKey('peak', $data);
        self::assertArrayHasKey('limit', $data);
        self::assertArrayHasKey('usage_percentage', $data);

        self::assertIsInt($data['current']);
        self::assertIsInt($data['peak']);
        self::assertIsInt($data['limit']);
        self::assertIsFloat($data['usage_percentage']);

        self::assertGreaterThan(0, $data['current']);
        self::assertGreaterThan(0, $data['peak']);
    }

    #[Test]
    public function formatBytesFormatsCorrectly(): void
    {
        self::assertSame('0 B', $this->collector->formatBytes(0));
        self::assertSame('100 B', $this->collector->formatBytes(100));
        self::assertSame('1 KB', $this->collector->formatBytes(1024));
        self::assertSame('1 MB', $this->collector->formatBytes(1048576));
        self::assertSame('1.5 MB', $this->collector->formatBytes(1572864));
        self::assertSame('1 GB', $this->collector->formatBytes(1073741824));
    }

    #[Test]
    public function getIndicatorValueReturnsFormattedPeakMemory(): void
    {
        $this->collector->collect();
        $indicatorValue = $this->collector->getIndicatorValue();

        // Indicator value should be a formatted bytes string (e.g., "2 MB")
        self::assertMatchesRegularExpression('/^\d+(\.\d+)?\s+(B|KB|MB|GB)$/', $indicatorValue);
    }

    #[Test]
    public function getIndicatorColorReturnsGreenForLowUsage(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['usage_percentage' => 50.0]);

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowForMediumUsage(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['usage_percentage' => 75.0]);

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedForHighUsage(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['usage_percentage' => 95.0]);

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorThresholdBoundaries(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');

        // 69.9 should be green
        $reflection->setValue($this->collector, ['usage_percentage' => 69.9]);
        self::assertSame('green', $this->collector->getIndicatorColor());

        // 70.0 should be yellow
        $reflection->setValue($this->collector, ['usage_percentage' => 70.0]);
        self::assertSame('yellow', $this->collector->getIndicatorColor());

        // 89.9 should be yellow
        $reflection->setValue($this->collector, ['usage_percentage' => 89.9]);
        self::assertSame('yellow', $this->collector->getIndicatorColor());

        // 90.0 should be red
        $reflection->setValue($this->collector, ['usage_percentage' => 90.0]);
        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function resetClearsDataAndSnapshots(): void
    {
        $this->collector->takeSnapshot('test_point');
        $this->collector->collect();

        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // After reset, collecting again should have no snapshots
        $this->collector->collect();
        $data = $this->collector->getData();
        self::assertEmpty($data['snapshots']);
    }

    #[Test]
    public function onWpLoadedTakesSnapshot(): void
    {
        $this->collector->onWpLoaded();
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('wp_loaded', $data['snapshots']);
        self::assertIsInt($data['snapshots']['wp_loaded']);
        self::assertGreaterThan(0, $data['snapshots']['wp_loaded']);
    }

    #[Test]
    public function onTemplateRedirectTakesSnapshot(): void
    {
        $this->collector->onTemplateRedirect();
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('template_redirect', $data['snapshots']);
        self::assertGreaterThan(0, $data['snapshots']['template_redirect']);
    }

    #[Test]
    public function onWpFooterTakesSnapshot(): void
    {
        $this->collector->onWpFooter();
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('wp_footer', $data['snapshots']);
        self::assertGreaterThan(0, $data['snapshots']['wp_footer']);
    }

    #[Test]
    public function onShutdownTakesSnapshot(): void
    {
        $this->collector->onShutdown();
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('shutdown', $data['snapshots']);
        self::assertGreaterThan(0, $data['snapshots']['shutdown']);
    }

    #[Test]
    public function parseMemoryValueHandlesSuffixes(): void
    {
        $reflection = new \ReflectionMethod($this->collector, 'parseMemoryValue');

        self::assertSame(128 * 1024 * 1024, $reflection->invoke($this->collector, '128M'));
        self::assertSame(1024 * 1024 * 1024, $reflection->invoke($this->collector, '1G'));
        self::assertSame(512 * 1024, $reflection->invoke($this->collector, '512K'));
        self::assertSame(1024, $reflection->invoke($this->collector, '1024'));
    }

    #[Test]
    public function collectComputesUsagePercentage(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        if ($data['limit'] > 0) {
            $expected = round(($data['peak'] / $data['limit']) * 100, 2);
            self::assertSame($expected, $data['usage_percentage']);
        } else {
            self::assertSame(0.0, $data['usage_percentage']);
        }
    }

    #[Test]
    public function multipleSnapshotsRecordAllLabels(): void
    {
        $this->collector->onWpLoaded();
        $this->collector->onTemplateRedirect();
        $this->collector->onWpFooter();
        $this->collector->onShutdown();

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('wp_loaded', $data['snapshots']);
        self::assertArrayHasKey('template_redirect', $data['snapshots']);
        self::assertArrayHasKey('wp_footer', $data['snapshots']);
        self::assertArrayHasKey('shutdown', $data['snapshots']);
        self::assertCount(4, $data['snapshots']);
    }
}
