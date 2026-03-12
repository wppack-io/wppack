<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\ShortcodeDataCollector;

final class ShortcodeDataCollectorTest extends TestCase
{
    private ShortcodeDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new ShortcodeDataCollector();
    }

    #[Test]
    public function getNameReturnsShortcode(): void
    {
        self::assertSame('shortcode', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsShortcode(): void
    {
        self::assertSame('Shortcode', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutGlobalsReturnsDefaults(): void
    {
        unset($GLOBALS['shortcode_tags']);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['shortcodes']);
        self::assertSame(0, $data['total_count']);
        self::assertSame(0, $data['used_count']);
        self::assertSame([], $data['used_shortcodes']);
        self::assertSame(0.0, $data['execution_time']);
        self::assertSame([], $data['executions']);
    }

    #[Test]
    public function capturePreAndPostShortcodeRecordsTiming(): void
    {
        // Simulate a shortcode execution cycle
        $this->collector->capturePreShortcode(false, 'gallery', [], []);
        // Small delay to ensure measurable duration
        $this->collector->capturePostShortcode('output', 'gallery', [], []);

        $reflection = new \ReflectionProperty($this->collector, 'shortcodeTimings');
        $timings = $reflection->getValue($this->collector);

        self::assertCount(1, $timings);
        self::assertSame('gallery', $timings[0]['tag']);
        self::assertArrayHasKey('start', $timings[0]);
        self::assertArrayHasKey('duration', $timings[0]);
        self::assertGreaterThanOrEqual(0.0, $timings[0]['duration']);
    }

    #[Test]
    public function getBadgeValueReturnsTotalCount(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 12]);

        self::assertSame('12', $this->collector->getBadgeValue());
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
    public function resetClearsDataAndTimings(): void
    {
        // Add some timing data
        $this->collector->capturePreShortcode(false, 'test', [], []);
        $this->collector->capturePostShortcode('out', 'test', [], []);

        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 5]);

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        $timingsReflection = new \ReflectionProperty($this->collector, 'shortcodeTimings');
        self::assertSame([], $timingsReflection->getValue($this->collector));

        $stackReflection = new \ReflectionProperty($this->collector, 'shortcodeStartStack');
        self::assertSame([], $stackReflection->getValue($this->collector));
    }
}
