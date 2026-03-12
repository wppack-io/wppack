<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\FeedDataCollector;

final class FeedDataCollectorTest extends TestCase
{
    private FeedDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new FeedDataCollector();
    }

    #[Test]
    public function getNameReturnsFeed(): void
    {
        self::assertSame('feed', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsFeed(): void
    {
        self::assertSame('Feed', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('get_bloginfo')) {
            self::markTestSkipped('WordPress functions are available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['feeds']);
        self::assertSame(0, $data['total_count']);
        self::assertSame(0, $data['custom_count']);
        self::assertTrue($data['feed_discovery']);
    }

    #[Test]
    public function getBadgeValueReturnsTotalCount(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 4]);

        self::assertSame('4', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenZero(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 0]);

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 4]);
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }
}
