<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\CacheDataCollector;

final class CacheDataCollectorTest extends TestCase
{
    private CacheDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new CacheDataCollector();
    }

    #[Test]
    public function getNameReturnsCache(): void
    {
        self::assertSame('cache', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsCache(): void
    {
        self::assertSame('Cache', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressSkipsGracefully(): void
    {
        global $wp_object_cache;
        if (isset($wp_object_cache)) {
            self::markTestSkipped('WordPress object cache is active; hits/misses are non-zero.');
        }

        // Without $wp_object_cache, collect should still work with zero values
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['hits']);
        self::assertSame(0, $data['misses']);
        self::assertSame(0.0, $data['hit_rate']);
        self::assertSame(0, $data['transient_sets']);
        self::assertSame(0, $data['transient_deletes']);
    }

    #[Test]
    public function getBadgeColorReturnsGreenForHighHitRate(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['hit_rate' => 90.0]);

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowForMediumHitRate(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['hit_rate' => 65.0]);

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedForLowHitRate(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['hit_rate' => 30.0]);

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorThresholdBoundaries(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');

        // 49.9 should be red
        $reflection->setValue($this->collector, ['hit_rate' => 49.9]);
        self::assertSame('red', $this->collector->getBadgeColor());

        // 50.0 should be yellow
        $reflection->setValue($this->collector, ['hit_rate' => 50.0]);
        self::assertSame('yellow', $this->collector->getBadgeColor());

        // 79.9 should be yellow
        $reflection->setValue($this->collector, ['hit_rate' => 79.9]);
        self::assertSame('yellow', $this->collector->getBadgeColor());

        // 80.0 should be green
        $reflection->setValue($this->collector, ['hit_rate' => 80.0]);
        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeValueReturnsFormattedHitRate(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['hit_rate' => 85.5]);

        self::assertSame('85.5%', $this->collector->getBadgeValue());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function resetClearsTransientCounters(): void
    {
        // Simulate transient operations
        $this->collector->onTransientSet();
        $this->collector->onTransientSet();
        $this->collector->onTransientDeleted();

        $this->collector->collect();
        $data = $this->collector->getData();
        self::assertSame(2, $data['transient_sets']);
        self::assertSame(1, $data['transient_deletes']);

        $this->collector->reset();
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['transient_sets']);
        self::assertSame(0, $data['transient_deletes']);
    }
}
