<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\MemoryDataCollector;

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
    public function getBadgeValueReturnsFormattedPeakMemory(): void
    {
        $this->collector->collect();
        $badgeValue = $this->collector->getBadgeValue();

        // Badge value should be a formatted bytes string (e.g., "2 MB")
        self::assertMatchesRegularExpression('/^\d+(\.\d+)?\s+(B|KB|MB|GB)$/', $badgeValue);
    }

    #[Test]
    public function getBadgeColorReturnsGreenForLowUsage(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['usage_percentage' => 50.0]);

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowForMediumUsage(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['usage_percentage' => 75.0]);

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedForHighUsage(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['usage_percentage' => 95.0]);

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorThresholdBoundaries(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');

        // 69.9 should be green
        $reflection->setValue($this->collector, ['usage_percentage' => 69.9]);
        self::assertSame('green', $this->collector->getBadgeColor());

        // 70.0 should be yellow
        $reflection->setValue($this->collector, ['usage_percentage' => 70.0]);
        self::assertSame('yellow', $this->collector->getBadgeColor());

        // 89.9 should be yellow
        $reflection->setValue($this->collector, ['usage_percentage' => 89.9]);
        self::assertSame('yellow', $this->collector->getBadgeColor());

        // 90.0 should be red
        $reflection->setValue($this->collector, ['usage_percentage' => 90.0]);
        self::assertSame('red', $this->collector->getBadgeColor());
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
}
