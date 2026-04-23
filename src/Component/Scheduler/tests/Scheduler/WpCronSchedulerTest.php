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
use WPPack\Component\Scheduler\Exception\LogicException;
use WPPack\Component\Scheduler\Message\RecurringMessage;
use WPPack\Component\Scheduler\Scheduler\SchedulerInterface;
use WPPack\Component\Scheduler\Scheduler\WpCronScheduler;
use WPPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WPPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WPPack\Component\Scheduler\Trigger\IntervalTrigger;

#[CoversClass(WpCronScheduler::class)]
final class WpCronSchedulerTest extends TestCase
{
    private WpCronScheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new WpCronScheduler();
    }

    protected function tearDown(): void
    {
        foreach (['wpcron-int-1', 'wpcron-date-1', 'wpcron-upsert-1'] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    #[Test]
    public function implementsSchedulerInterface(): void
    {
        self::assertInstanceOf(SchedulerInterface::class, $this->scheduler);
    }

    #[Test]
    public function scheduleDateTimeRegistersSingleEvent(): void
    {
        $when = (new \DateTimeImmutable())->modify('+1 hour');
        $message = RecurringMessage::trigger(new DateTimeTrigger($when), new \stdClass());

        $this->scheduler->schedule('wpcron-date-1', $message);

        self::assertTrue($this->scheduler->has('wpcron-date-1'));
        $next = $this->scheduler->getNextRunDate('wpcron-date-1');
        self::assertNotNull($next);
        self::assertSame($when->getTimestamp(), $next->getTimestamp());
    }

    #[Test]
    public function scheduleIntervalRegistersRecurringEvent(): void
    {
        $message = RecurringMessage::trigger(new IntervalTrigger(3600), new \stdClass());

        $this->scheduler->schedule('wpcron-int-1', $message);

        self::assertTrue($this->scheduler->has('wpcron-int-1'));
        self::assertInstanceOf(\DateTimeImmutable::class, $this->scheduler->getNextRunDate('wpcron-int-1'));
    }

    #[Test]
    public function scheduleCronExpressionThrows(): void
    {
        $message = RecurringMessage::trigger(new CronExpressionTrigger('0 * * * *'), new \stdClass());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('WP-Cron has no cron-expression support');

        $this->scheduler->schedule('wpcron-cron-unused', $message);
    }

    #[Test]
    public function unscheduleRemovesEvent(): void
    {
        $message = RecurringMessage::trigger(new IntervalTrigger(3600), new \stdClass());
        $this->scheduler->schedule('wpcron-int-1', $message);

        $this->scheduler->unschedule('wpcron-int-1');

        self::assertFalse($this->scheduler->has('wpcron-int-1'));
        self::assertNull($this->scheduler->getNextRunDate('wpcron-int-1'));
    }

    #[Test]
    public function scheduleIsIdempotent(): void
    {
        $message = RecurringMessage::trigger(new IntervalTrigger(3600), new \stdClass());

        $this->scheduler->schedule('wpcron-upsert-1', $message);
        $this->scheduler->schedule('wpcron-upsert-1', $message);

        self::assertTrue($this->scheduler->has('wpcron-upsert-1'));
    }

    #[Test]
    public function createScheduleRawThrowsUnsupported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('EventBridge-specific');

        $this->scheduler->createScheduleRaw('x', 'rate(1 hour)', '{}', true);
    }

    #[Test]
    public function customRecurrenceIsVisibleInCronSchedulesFilter(): void
    {
        $message = RecurringMessage::trigger(new IntervalTrigger(777), new \stdClass());

        $this->scheduler->schedule('wpcron-int-1', $message);

        /** @var array<string, array{interval: int, display: string}> $schedules */
        $schedules = apply_filters('cron_schedules', []);
        self::assertArrayHasKey('wppack_every_777s', $schedules);
        self::assertSame(777, $schedules['wppack_every_777s']['interval']);
    }
}
