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
use WPPack\Component\Scheduler\Scheduler\ActionSchedulerScheduler;
use WPPack\Component\Scheduler\Scheduler\SchedulerInterface;
use WPPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WPPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WPPack\Component\Scheduler\Trigger\IntervalTrigger;

#[CoversClass(ActionSchedulerScheduler::class)]
final class ActionSchedulerSchedulerTest extends TestCase
{
    private ActionSchedulerScheduler $scheduler;
    private string $group = 'wppack-test';

    protected function setUp(): void
    {
        if (!\function_exists('as_schedule_single_action')) {
            self::markTestSkipped('Action Scheduler is not loaded.');
        }

        $this->scheduler = new ActionSchedulerScheduler($this->group);
    }

    protected function tearDown(): void
    {
        if (\function_exists('as_unschedule_all_actions')) {
            // Scrub test scheduleIds used below.
            foreach (['as-int-1', 'as-date-1', 'as-cron-1', 'as-upsert-1'] as $id) {
                as_unschedule_all_actions($id, [], $this->group);
            }
        }
    }

    #[Test]
    public function implementsSchedulerInterface(): void
    {
        self::assertInstanceOf(SchedulerInterface::class, $this->scheduler);
    }

    #[Test]
    public function scheduleIntervalRegistersRecurringAction(): void
    {
        $message = RecurringMessage::trigger(new IntervalTrigger(3600), new \stdClass());

        $this->scheduler->schedule('as-int-1', $message);

        self::assertTrue($this->scheduler->has('as-int-1'));
        self::assertInstanceOf(\DateTimeImmutable::class, $this->scheduler->getNextRunDate('as-int-1'));
    }

    #[Test]
    public function scheduleCronExpressionRegistersCronAction(): void
    {
        $message = RecurringMessage::trigger(new CronExpressionTrigger('0 * * * *'), new \stdClass());

        $this->scheduler->schedule('as-cron-1', $message);

        self::assertTrue($this->scheduler->has('as-cron-1'));
    }

    #[Test]
    public function scheduleDateTimeRegistersSingleAction(): void
    {
        $when = (new \DateTimeImmutable())->modify('+1 hour');
        $message = RecurringMessage::trigger(new DateTimeTrigger($when), new \stdClass());

        $this->scheduler->schedule('as-date-1', $message);

        self::assertTrue($this->scheduler->has('as-date-1'));
        $next = $this->scheduler->getNextRunDate('as-date-1');
        self::assertNotNull($next);
        self::assertSame($when->getTimestamp(), $next->getTimestamp());
    }

    #[Test]
    public function unscheduleRemovesAction(): void
    {
        $message = RecurringMessage::trigger(new IntervalTrigger(3600), new \stdClass());
        $this->scheduler->schedule('as-int-1', $message);

        $this->scheduler->unschedule('as-int-1');

        self::assertFalse($this->scheduler->has('as-int-1'));
        self::assertNull($this->scheduler->getNextRunDate('as-int-1'));
    }

    #[Test]
    public function scheduleIsIdempotent(): void
    {
        $message = RecurringMessage::trigger(new IntervalTrigger(3600), new \stdClass());

        $this->scheduler->schedule('as-upsert-1', $message);
        $this->scheduler->schedule('as-upsert-1', $message);

        self::assertTrue($this->scheduler->has('as-upsert-1'));
    }

    #[Test]
    public function createScheduleRawThrowsUnsupported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('EventBridge-specific');

        $this->scheduler->createScheduleRaw('x', 'rate(1 hour)', '{}', true);
    }
}
