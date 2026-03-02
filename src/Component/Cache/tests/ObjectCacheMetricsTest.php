<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\ObjectCacheMetrics;

final class ObjectCacheMetricsTest extends TestCase
{
    #[Test]
    public function constructsWithValues(): void
    {
        $metrics = new ObjectCacheMetrics(hits: 10, misses: 5, adapterName: 'redis');

        self::assertSame(10, $metrics->hits);
        self::assertSame(5, $metrics->misses);
        self::assertSame('redis', $metrics->adapterName);
    }

    #[Test]
    public function constructsWithNullAdapter(): void
    {
        $metrics = new ObjectCacheMetrics(hits: 0, misses: 0, adapterName: null);

        self::assertSame(0, $metrics->hits);
        self::assertSame(0, $metrics->misses);
        self::assertNull($metrics->adapterName);
    }
}
