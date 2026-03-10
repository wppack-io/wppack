<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\EventDataCollector;

final class EventDataCollectorTest extends TestCase
{
    private EventDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new EventDataCollector();
    }

    #[Test]
    public function getNameReturnsEvent(): void
    {
        self::assertSame('event', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsEvents(): void
    {
        self::assertSame('Events', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['hooks']);
        self::assertSame(0, $data['total_firings']);
        self::assertSame(0, $data['unique_hooks']);
        self::assertSame([], $data['top_hooks']);
        self::assertSame(0, $data['registered_hooks']);
        self::assertSame(0, $data['orphan_hooks']);
        self::assertSame([], $data['listener_counts']);
    }

    #[Test]
    public function captureHookFiredIncrementsCount(): void
    {
        // Without WordPress, current_filter() is not available,
        // so captureHookFired() should return early (fallback behavior).
        $this->collector->captureHookFired();
        $this->collector->captureHookFired();

        $this->collector->collect();
        $data = $this->collector->getData();

        if (!function_exists('current_filter')) {
            // Without WordPress, the guard causes early return — no hooks captured
            self::assertSame(0, $data['total_firings']);
            self::assertSame(0, $data['unique_hooks']);
        } else {
            // With WordPress, hooks would be captured
            self::assertGreaterThan(0, $data['total_firings']);
        }
    }

    #[Test]
    public function getBadgeValueReturnsTotalFirings(): void
    {
        self::assertSame('0', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReflectsFiringsAfterCapture(): void
    {
        // Simulate firings via reflection since current_filter() may not exist
        $reflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $reflection->setValue($this->collector, 42);

        self::assertSame('42', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsGreenWhenBelowFiveHundred(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $reflection->setValue($this->collector, 0);

        self::assertSame('green', $this->collector->getBadgeColor());

        $reflection->setValue($this->collector, 499);
        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowWhenBelowOneThousand(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $reflection->setValue($this->collector, 500);

        self::assertSame('yellow', $this->collector->getBadgeColor());

        $reflection->setValue($this->collector, 999);
        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedWhenAtOrAboveOneThousand(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $reflection->setValue($this->collector, 1000);

        self::assertSame('red', $this->collector->getBadgeColor());

        $reflection->setValue($this->collector, 5000);
        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function topHooksAreSortedByCount(): void
    {
        // Simulate hook counts via reflection
        $reflection = new \ReflectionProperty($this->collector, 'hookCounts');
        $reflection->setValue($this->collector, [
            'init' => 5,
            'wp_head' => 100,
            'the_content' => 50,
            'wp_footer' => 10,
        ]);

        $totalReflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $totalReflection->setValue($this->collector, 165);

        $this->collector->collect();
        $data = $this->collector->getData();

        $topHookNames = array_keys($data['top_hooks']);
        self::assertSame(['wp_head', 'the_content', 'wp_footer', 'init'], $topHookNames);
    }

    #[Test]
    public function topHooksLimitedToTwenty(): void
    {
        // Simulate 25 hooks
        $hookCounts = [];
        for ($i = 0; $i < 25; $i++) {
            $hookCounts["hook_{$i}"] = 25 - $i;
        }

        $reflection = new \ReflectionProperty($this->collector, 'hookCounts');
        $reflection->setValue($this->collector, $hookCounts);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertCount(20, $data['top_hooks']);
        self::assertSame(25, $data['unique_hooks']);
    }

    #[Test]
    public function resetClearsData(): void
    {
        // Set up some state via reflection
        $hookCountsReflection = new \ReflectionProperty($this->collector, 'hookCounts');
        $hookCountsReflection->setValue($this->collector, ['init' => 5]);

        $totalReflection = new \ReflectionProperty($this->collector, 'totalFirings');
        $totalReflection->setValue($this->collector, 5);

        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // After reset, collecting again should have empty hookCounts
        $this->collector->collect();
        $data = $this->collector->getData();
        self::assertSame(0, $data['total_firings']);
        self::assertSame(0, $data['unique_hooks']);
        self::assertSame([], $data['hooks']);
    }
}
