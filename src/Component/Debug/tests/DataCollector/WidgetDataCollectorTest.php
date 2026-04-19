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
use WPPack\Component\Debug\DataCollector\WidgetDataCollector;

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
    public function getIndicatorValueReturnsActiveWidgetCount(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['active_widgets' => 7]);

        self::assertSame('7', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenZero(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['active_widgets' => 0]);

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getIndicatorColor());
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

    #[Test]
    public function collectWithRegisteredSidebarsReturnsData(): void
    {
        $sidebarId = 'test-sidebar-' . uniqid();
        register_sidebar([
            'name' => 'Test Sidebar',
            'id' => $sidebarId,
            'before_widget' => '<div>',
            'after_widget' => '</div>',
            'before_title' => '<h2>',
            'after_title' => '</h2>',
        ]);

        // Ensure the sidebar appears in wp_get_sidebars_widgets()
        $sidebarsWidgets = wp_get_sidebars_widgets();
        $sidebarsWidgets[$sidebarId] = [];
        wp_set_sidebars_widgets($sidebarsWidgets);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertGreaterThanOrEqual(1, $data['total_sidebars']);
            self::assertArrayHasKey($sidebarId, $data['sidebars']);
            self::assertSame('Test Sidebar', $data['sidebars'][$sidebarId]['name']);
        } finally {
            unregister_sidebar($sidebarId);
        }
    }

    #[Test]
    public function collectWithSidebarTimingsIncludesTimingData(): void
    {
        global $wp_registered_sidebars;

        $sidebarId = 'timing-sidebar-' . uniqid();
        register_sidebar([
            'name' => 'Timing Sidebar',
            'id' => $sidebarId,
            'before_widget' => '<div>',
            'after_widget' => '</div>',
            'before_title' => '<h2>',
            'after_title' => '</h2>',
        ]);

        // Simulate sidebar rendering via capture methods
        $this->collector->captureSidebarBefore($sidebarId);
        usleep(1000);
        $this->collector->captureSidebarAfter($sidebarId);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertNotEmpty($data['sidebar_timings']);
            self::assertSame($sidebarId, $data['sidebar_timings'][0]['sidebar']);
            self::assertSame('Timing Sidebar', $data['sidebar_timings'][0]['name']);
            self::assertGreaterThan(0.0, $data['sidebar_timings'][0]['duration']);
            self::assertGreaterThan(0.0, $data['render_time']);
        } finally {
            unregister_sidebar($sidebarId);
        }
    }

    #[Test]
    public function collectExcludesInactiveWidgets(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        // wp_inactive_widgets should not appear in sidebars
        self::assertArrayNotHasKey('wp_inactive_widgets', $data['sidebars']);
    }
}
