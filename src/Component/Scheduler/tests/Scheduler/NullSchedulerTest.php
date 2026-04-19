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

namespace WPPack\Component\Scheduler\Tests\Scheduler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scheduler\Message\RecurringMessage;
use WPPack\Component\Scheduler\Scheduler\NullScheduler;
use WPPack\Component\Scheduler\Scheduler\SchedulerInterface;
use WPPack\Component\Scheduler\Trigger\IntervalTrigger;

#[CoversClass(NullScheduler::class)]
final class NullSchedulerTest extends TestCase
{
    private NullScheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new NullScheduler();
    }

    #[Test]
    public function implementsSchedulerInterface(): void
    {
        self::assertInstanceOf(SchedulerInterface::class, $this->scheduler);
    }

    #[Test]
    public function scheduleAddsMessage(): void
    {
        $message = RecurringMessage::every('1 hour', new \stdClass());

        $this->scheduler->schedule('task-1', $message);

        self::assertTrue($this->scheduler->has('task-1'));
    }

    #[Test]
    public function unscheduleRemovesMessage(): void
    {
        $message = RecurringMessage::every('1 hour', new \stdClass());
        $this->scheduler->schedule('task-1', $message);

        $this->scheduler->unschedule('task-1');

        self::assertFalse($this->scheduler->has('task-1'));
    }

    #[Test]
    public function unscheduleNonExistentIsNoop(): void
    {
        // Should not throw
        $this->scheduler->unschedule('nonexistent');

        self::assertFalse($this->scheduler->has('nonexistent'));
    }

    #[Test]
    public function hasReturnsFalseForUnknownId(): void
    {
        self::assertFalse($this->scheduler->has('unknown'));
    }

    #[Test]
    public function hasReturnsTrueForScheduledId(): void
    {
        $message = RecurringMessage::every('30 seconds', new \stdClass());
        $this->scheduler->schedule('my-task', $message);

        self::assertTrue($this->scheduler->has('my-task'));
    }

    #[Test]
    public function getNextRunDateReturnsNullForUnknownId(): void
    {
        self::assertNull($this->scheduler->getNextRunDate('unknown'));
    }

    #[Test]
    public function getNextRunDateReturnsDateForScheduledMessage(): void
    {
        $message = RecurringMessage::every('1 hour', new \stdClass());
        $this->scheduler->schedule('task-1', $message);

        $nextRunDate = $this->scheduler->getNextRunDate('task-1');

        // IntervalTrigger with no lastRun returns "now"
        self::assertInstanceOf(\DateTimeImmutable::class, $nextRunDate);
    }

    #[Test]
    public function createScheduleRawIsNoop(): void
    {
        // NullScheduler does not interact with EventBridge, so this is a no-op
        $this->scheduler->createScheduleRaw('raw-1', 'rate(1 hour)', '{}', true);

        // No schedule should be created from raw params
        self::assertFalse($this->scheduler->has('raw-1'));
    }

    #[Test]
    public function getSchedulesReturnsAllScheduled(): void
    {
        self::assertSame([], $this->scheduler->getSchedules());

        $msg1 = RecurringMessage::every('1 hour', new \stdClass());
        $msg2 = RecurringMessage::every('30 seconds', new \stdClass());

        $this->scheduler->schedule('task-1', $msg1);
        $this->scheduler->schedule('task-2', $msg2);

        $schedules = $this->scheduler->getSchedules();

        self::assertCount(2, $schedules);
        self::assertArrayHasKey('task-1', $schedules);
        self::assertArrayHasKey('task-2', $schedules);
        self::assertSame($msg1, $schedules['task-1']);
        self::assertSame($msg2, $schedules['task-2']);
    }

    #[Test]
    public function scheduleOverwritesExistingId(): void
    {
        $msg1 = RecurringMessage::every('1 hour', new \stdClass());
        $msg2 = RecurringMessage::every('30 seconds', new \stdClass());

        $this->scheduler->schedule('task-1', $msg1);
        $this->scheduler->schedule('task-1', $msg2);

        self::assertSame($msg2, $this->scheduler->getSchedules()['task-1']);
        self::assertCount(1, $this->scheduler->getSchedules());
    }

    #[Test]
    public function getNextRunDateDelegatesToTrigger(): void
    {
        // Use a trigger that returns a specific future date
        $trigger = new IntervalTrigger(3600);
        $message = RecurringMessage::trigger($trigger, new \stdClass());

        $this->scheduler->schedule('trigger-task', $message);

        $nextRun = $this->scheduler->getNextRunDate('trigger-task');

        self::assertNotNull($nextRun);
        // IntervalTrigger with no lastRun returns $now
        self::assertInstanceOf(\DateTimeImmutable::class, $nextRun);
    }
}
