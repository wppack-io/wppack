<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests\Collector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\Collector\ActionSchedulerCollector;

final class ActionSchedulerCollectorTest extends TestCase
{
    private ActionSchedulerCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new ActionSchedulerCollector();
    }

    #[Test]
    public function collectReturnsEmptyWhenActionSchedulerNotAvailable(): void
    {
        if (class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('This test requires Action Scheduler to NOT be available.');
        }

        self::assertSame([], $this->collector->collect());
    }

    #[Test]
    public function collectReturnsPendingActions(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $timestamp = time() + 3600;
        $actionId = as_schedule_single_action($timestamp, 'test_collect_hook', ['arg1'], 'test-group');

        $actions = $this->collector->collect();

        $found = false;
        foreach ($actions as $action) {
            if ($action['actionId'] === $actionId) {
                self::assertSame('test_collect_hook', $action['hook']);
                self::assertSame(['arg1'], $action['args']);
                self::assertSame('test-group', $action['group']);
                self::assertSame('single', $action['scheduleType']);
                self::assertSame(0, $action['interval']);
                self::assertSame('', $action['cronExpression']);
                self::assertInstanceOf(\DateTimeImmutable::class, $action['scheduledDate']);
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Pending action should be collected');
    }

    #[Test]
    public function collectResolvesIntervalScheduleType(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $actionId = as_schedule_recurring_action(time(), 3600, 'test_collect_interval_hook', [], 'test-group');

        $actions = $this->collector->collect();

        $found = false;
        foreach ($actions as $action) {
            if ($action['actionId'] === $actionId) {
                self::assertSame('interval', $action['scheduleType']);
                self::assertSame(3600, $action['interval']);
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Interval action should be collected');
    }

    #[Test]
    public function collectResolvesCronScheduleType(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $actionId = as_schedule_cron_action(time(), '*/15 * * * *', 'test_collect_cron_hook', [], 'test-group');

        $actions = $this->collector->collect();

        $found = false;
        foreach ($actions as $action) {
            if ($action['actionId'] === $actionId) {
                self::assertSame('cron', $action['scheduleType']);
                self::assertSame('*/15 * * * *', $action['cronExpression']);
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Cron action should be collected');
    }
}
