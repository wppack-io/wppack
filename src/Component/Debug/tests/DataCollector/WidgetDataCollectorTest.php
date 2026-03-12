<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\WidgetDataCollector;

final class WidgetDataCollectorTest extends TestCase
{
    private WidgetDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new WidgetDataCollector();
    }

    #[Test]
    public function getNameReturnsWidget(): void
    {
        self::assertSame('widget', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsWidget(): void
    {
        self::assertSame('Widget', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('wp_get_sidebars_widgets')) {
            self::markTestSkipped('WordPress functions are available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['sidebars']);
        self::assertSame(0, $data['total_widgets']);
        self::assertSame(0, $data['total_sidebars']);
        self::assertSame(0, $data['active_widgets']);
        self::assertSame(0.0, $data['render_time']);
        self::assertSame([], $data['sidebar_timings']);
    }

    #[Test]
    public function captureSidebarBeforeAndAfterRecordsTiming(): void
    {
        $this->collector->captureSidebarBefore('sidebar-1');
        $this->collector->captureSidebarAfter('sidebar-1');

        $reflection = new \ReflectionProperty($this->collector, 'sidebarTimings');
        $timings = $reflection->getValue($this->collector);

        self::assertCount(1, $timings);
        self::assertSame('sidebar-1', $timings[0]['sidebar']);
        self::assertArrayHasKey('start', $timings[0]);
        self::assertArrayHasKey('duration', $timings[0]);
        self::assertGreaterThanOrEqual(0.0, $timings[0]['duration']);
    }

    #[Test]
    public function captureSidebarAfterIgnoresMismatchedIndex(): void
    {
        $this->collector->captureSidebarBefore('sidebar-1');
        $this->collector->captureSidebarAfter('sidebar-2');

        $reflection = new \ReflectionProperty($this->collector, 'sidebarTimings');
        $timings = $reflection->getValue($this->collector);

        self::assertCount(0, $timings);
    }

    #[Test]
    public function getBadgeValueReturnsActiveWidgetCount(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['active_widgets' => 7]);

        self::assertSame('7', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenZero(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['active_widgets' => 0]);

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsDataAndTimings(): void
    {
        // Add some timing data
        $this->collector->captureSidebarBefore('sidebar-1');
        $this->collector->captureSidebarAfter('sidebar-1');

        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['active_widgets' => 3]);

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        $timingsReflection = new \ReflectionProperty($this->collector, 'sidebarTimings');
        self::assertSame([], $timingsReflection->getValue($this->collector));

        $startReflection = new \ReflectionProperty($this->collector, 'currentSidebarStart');
        self::assertSame(0.0, $startReflection->getValue($this->collector));

        $sidebarReflection = new \ReflectionProperty($this->collector, 'currentSidebar');
        self::assertSame('', $sidebarReflection->getValue($this->collector));
    }
}
