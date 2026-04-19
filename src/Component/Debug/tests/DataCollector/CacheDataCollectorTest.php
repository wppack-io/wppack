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
use WPPack\Component\Debug\DataCollector\CacheDataCollector;

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
        self::assertSame([], $data['transient_operations']);
    }

    #[Test]
    public function getIndicatorColorReturnsGreenForHighHitRate(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['hit_rate' => 90.0]);

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowForMediumHitRate(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['hit_rate' => 65.0]);

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedForLowHitRate(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['hit_rate' => 30.0]);

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorThresholdBoundaries(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');

        // 49.9 should be red
        $reflection->setValue($this->collector, ['hit_rate' => 49.9]);
        self::assertSame('red', $this->collector->getIndicatorColor());

        // 50.0 should be yellow
        $reflection->setValue($this->collector, ['hit_rate' => 50.0]);
        self::assertSame('yellow', $this->collector->getIndicatorColor());

        // 79.9 should be yellow
        $reflection->setValue($this->collector, ['hit_rate' => 79.9]);
        self::assertSame('yellow', $this->collector->getIndicatorColor());

        // 80.0 should be green
        $reflection->setValue($this->collector, ['hit_rate' => 80.0]);
        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorValueReturnsFormattedHitRate(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['hit_rate' => 85.5]);

        self::assertSame('85.5%', $this->collector->getIndicatorValue());
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
        $this->collector->onTransientSet('key1', 'value1', 3600);
        $this->collector->onTransientSet('key2', 'value2', 7200);
        $this->collector->onTransientDeleted('key3');

        $this->collector->collect();
        $data = $this->collector->getData();
        self::assertSame(2, $data['transient_sets']);
        self::assertSame(1, $data['transient_deletes']);

        $this->collector->reset();
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['transient_sets']);
        self::assertSame(0, $data['transient_deletes']);
        self::assertSame([], $data['transient_operations']);
    }

    #[Test]
    public function onTransientSetRecordsDetailedOperation(): void
    {
        $this->collector->onTransientSet('my_cache', 'some_value', 3600);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertCount(1, $data['transient_operations']);
        $op = $data['transient_operations'][0];
        self::assertSame('my_cache', $op['name']);
        self::assertSame('set', $op['operation']);
        self::assertSame(3600, $op['expiration']);
        self::assertNotEmpty($op['caller']);
        self::assertArrayHasKey('time', $op);
        self::assertIsFloat($op['time']);
    }

    #[Test]
    public function onTransientDeletedRecordsDetailedOperation(): void
    {
        $this->collector->onTransientDeleted('old_cache');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertCount(1, $data['transient_operations']);
        $op = $data['transient_operations'][0];
        self::assertSame('old_cache', $op['name']);
        self::assertSame('delete', $op['operation']);
        self::assertSame(0, $op['expiration']);
        self::assertNotEmpty($op['caller']);
        self::assertArrayHasKey('time', $op);
        self::assertIsFloat($op['time']);
    }

    #[Test]
    public function collectIncludesObjectCacheDropinInfo(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('object_cache_dropin', $data);
        self::assertIsString($data['object_cache_dropin']);
    }

    #[Test]
    public function collectIncludesCacheGroups(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('cache_groups', $data);
        self::assertIsArray($data['cache_groups']);
    }

    #[Test]
    public function multipleTransientOperationsAreTracked(): void
    {
        $this->collector->onTransientSet('key1', 'val1', 3600);
        $this->collector->onTransientSet('key2', 'val2', 0);
        $this->collector->onTransientDeleted('key3');
        $this->collector->onTransientSet('key4', 'val4', 86400);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(3, $data['transient_sets']);
        self::assertSame(1, $data['transient_deletes']);
        self::assertCount(4, $data['transient_operations']);

        self::assertSame('key1', $data['transient_operations'][0]['name']);
        self::assertSame('set', $data['transient_operations'][0]['operation']);
        self::assertSame(3600, $data['transient_operations'][0]['expiration']);

        self::assertSame('key3', $data['transient_operations'][2]['name']);
        self::assertSame('delete', $data['transient_operations'][2]['operation']);
    }

    #[Test]
    public function getElapsedMsReturnsZeroWhenRequestTimeFloatNotSet(): void
    {
        $method = new \ReflectionMethod($this->collector, 'getElapsedMs');

        $originalServer = $_SERVER;
        unset($_SERVER['REQUEST_TIME_FLOAT']);

        try {
            $result = $method->invoke($this->collector);
            self::assertSame(0.0, $result);
        } finally {
            $_SERVER = $originalServer;
        }
    }

    #[Test]
    public function detectDropinReturnsNoneWithoutObjectCache(): void
    {
        $method = new \ReflectionMethod($this->collector, 'detectDropin');

        global $wp_object_cache;
        $savedCache = $wp_object_cache ?? null;
        $wp_object_cache = null;

        try {
            $result = $method->invoke($this->collector);
            self::assertSame('none', $result);
        } finally {
            $wp_object_cache = $savedCache;
        }
    }

    #[Test]
    public function collectGroupStatsReturnsEmptyWithoutObjectCache(): void
    {
        $method = new \ReflectionMethod($this->collector, 'collectGroupStats');

        global $wp_object_cache;
        $savedCache = $wp_object_cache ?? null;
        $wp_object_cache = null;

        try {
            $result = $method->invoke($this->collector);
            self::assertSame([], $result);
        } finally {
            $wp_object_cache = $savedCache;
        }
    }

    #[Test]
    public function collectGroupStatsReturnsEmptyWhenCachePropertyMissing(): void
    {
        $method = new \ReflectionMethod($this->collector, 'collectGroupStats');

        global $wp_object_cache;
        $savedCache = $wp_object_cache ?? null;
        // Object without 'cache' property
        $wp_object_cache = new \stdClass();

        try {
            $result = $method->invoke($this->collector);
            self::assertSame([], $result);
        } finally {
            $wp_object_cache = $savedCache;
        }
    }

    #[Test]
    public function capturerCallerReturnsCallerName(): void
    {
        $method = new \ReflectionMethod($this->collector, 'captureCaller');

        $result = $method->invoke($this->collector);

        // Should return caller info (our test method via reflection invoke)
        self::assertIsString($result);
        self::assertNotEmpty($result);
    }
}
