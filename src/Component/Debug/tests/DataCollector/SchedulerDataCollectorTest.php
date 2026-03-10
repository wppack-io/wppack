<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\SchedulerDataCollector;

final class SchedulerDataCollectorTest extends TestCase
{
    private SchedulerDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new SchedulerDataCollector();
    }

    #[Test]
    public function getNameReturnsScheduler(): void
    {
        self::assertSame('scheduler', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsScheduler(): void
    {
        self::assertSame('Scheduler', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('_get_cron_array')) {
            self::markTestSkipped('WordPress functions are available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['cron_events']);
        self::assertSame(0, $data['cron_total']);
        self::assertSame(0, $data['cron_overdue']);
        self::assertFalse($data['action_scheduler_available']);
        self::assertSame('', $data['action_scheduler_version']);
        self::assertSame(0, $data['as_pending']);
        self::assertSame(0, $data['as_failed']);
        self::assertSame(0, $data['as_complete']);
        self::assertSame([], $data['as_recent_actions']);
        self::assertFalse($data['cron_disabled']);
        self::assertFalse($data['alternate_cron']);
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenNoEvents(): void
    {
        // Directly set data to simulate empty state
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 0]);

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsTotalWhenEventsExist(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 15]);

        self::assertSame('15', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsGreenWhenNoOverdueAndFewEvents(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 10, 'cron_overdue' => 0]);

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowWhenManyEvents(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 60, 'cron_overdue' => 0]);

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedWhenOverdue(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 5, 'cron_overdue' => 2]);

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();
        self::assertEmpty($this->collector->getData());
    }
}
