<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Tests\Trigger;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Exception\InvalidArgumentException;
use WpPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WpPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WpPack\Component\Scheduler\Trigger\IntervalTrigger;
use WpPack\Component\Scheduler\Trigger\JitterTrigger;
use WpPack\Component\Scheduler\Trigger\TriggerInterface;
use WpPack\Component\Scheduler\Trigger\WpCronScheduleTrigger;

final class TriggerTest extends TestCase
{
    // -----------------------------------------------------------------------
    // CronExpressionTrigger
    // -----------------------------------------------------------------------

    #[Test]
    public function cronExpressionImplementsTriggerInterface(): void
    {
        $trigger = new CronExpressionTrigger('*/5 * * * *');

        self::assertInstanceOf(TriggerInterface::class, $trigger);
    }

    #[Test]
    public function cronExpressionNextRunDateUsesNowWhenNoLastRun(): void
    {
        // Every hour at minute 0
        $trigger = new CronExpressionTrigger('0 * * * *');
        $now = new \DateTimeImmutable('2026-03-18 10:30:00');

        $next = $trigger->getNextRunDate($now);

        self::assertSame('2026-03-18 11:00:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function cronExpressionNextRunDateUsesLastRunWhenProvided(): void
    {
        // Every hour at minute 0
        $trigger = new CronExpressionTrigger('0 * * * *');
        $now = new \DateTimeImmutable('2026-03-18 10:30:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // Next run after lastRun (09:00) is 10:00, not 11:00
        self::assertSame('2026-03-18 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function cronExpressionEveryFiveMinutes(): void
    {
        $trigger = new CronExpressionTrigger('*/5 * * * *');
        $now = new \DateTimeImmutable('2026-03-18 10:03:00');

        $next = $trigger->getNextRunDate($now);

        self::assertSame('2026-03-18 10:05:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function cronExpressionDailyAtMidnight(): void
    {
        $trigger = new CronExpressionTrigger('0 0 * * *');
        $now = new \DateTimeImmutable('2026-03-18 23:59:00');

        $next = $trigger->getNextRunDate($now);

        self::assertSame('2026-03-19 00:00:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function cronExpressionWeekdaysOnly(): void
    {
        // Monday-Friday at 9:00
        $trigger = new CronExpressionTrigger('0 9 * * 1-5');
        // Saturday
        $now = new \DateTimeImmutable('2026-03-21 10:00:00');

        $next = $trigger->getNextRunDate($now);

        // Next weekday is Monday 2026-03-23
        self::assertSame('2026-03-23 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function cronExpressionReturnsImmutableDateTime(): void
    {
        $trigger = new CronExpressionTrigger('0 * * * *');
        $now = new \DateTimeImmutable('2026-03-18 10:30:00');

        $next = $trigger->getNextRunDate($now);

        self::assertInstanceOf(\DateTimeImmutable::class, $next);
    }

    #[Test]
    public function cronExpressionIntervalIsAlwaysNull(): void
    {
        $trigger = new CronExpressionTrigger('*/5 * * * *');

        self::assertNull($trigger->getIntervalInSeconds());
    }

    #[Test]
    public function cronExpressionToStringReturnsExpression(): void
    {
        $trigger = new CronExpressionTrigger('*/15 * * * *');

        self::assertSame('*/15 * * * *', (string) $trigger);
    }

    #[Test]
    public function cronExpressionIsStringable(): void
    {
        $trigger = new CronExpressionTrigger('0 0 * * *');

        self::assertInstanceOf(\Stringable::class, $trigger);
    }

    #[Test]
    public function cronExpressionThrowsOnInvalidExpression(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CronExpressionTrigger('invalid cron');
    }

    #[Test]
    public function cronExpressionSpecificDayOfMonth(): void
    {
        // 1st of every month at noon
        $trigger = new CronExpressionTrigger('0 12 1 * *');
        $now = new \DateTimeImmutable('2026-03-15 10:00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertSame('2026-04-01 12:00:00', $next->format('Y-m-d H:i:s'));
    }

    // -----------------------------------------------------------------------
    // IntervalTrigger
    // -----------------------------------------------------------------------

    #[Test]
    public function intervalImplementsTriggerInterface(): void
    {
        $trigger = new IntervalTrigger(60);

        self::assertInstanceOf(TriggerInterface::class, $trigger);
    }

    #[Test]
    public function intervalReturnsNowWhenNoLastRunAndNoFrom(): void
    {
        $trigger = new IntervalTrigger(300);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertEquals($now, $next);
    }

    #[Test]
    public function intervalReturnsFromDateWhenFromIsInFuture(): void
    {
        $from = new \DateTimeImmutable('2026-03-18 12:00:00');
        $trigger = new IntervalTrigger(300, $from);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertEquals($from, $next);
    }

    #[Test]
    public function intervalReturnsNowWhenFromIsInPast(): void
    {
        $from = new \DateTimeImmutable('2026-03-18 08:00:00');
        $trigger = new IntervalTrigger(300, $from);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertEquals($now, $next);
    }

    #[Test]
    public function intervalAddsSecondsToLastRun(): void
    {
        $trigger = new IntervalTrigger(3600);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:30:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // lastRun + 3600s = 10:30:00
        self::assertSame('2026-03-18 10:30:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function intervalReturnsNowWhenLastRunPlusIntervalIsInPast(): void
    {
        $trigger = new IntervalTrigger(60);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 08:00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // lastRun + 60s = 08:01:00, which is in the past, so return now
        self::assertEquals($now, $next);
    }

    #[Test]
    public function intervalReturnsExactBoundaryWhenLastRunPlusIntervalEqualsNow(): void
    {
        $trigger = new IntervalTrigger(3600);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // lastRun + 3600s = 10:00:00 = now, so $next > $now is false, return $now
        self::assertEquals($now, $next);
    }

    #[Test]
    public function intervalLastRunIgnoresFromDate(): void
    {
        $from = new \DateTimeImmutable('2026-03-18 12:00:00');
        $trigger = new IntervalTrigger(300, $from);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:58:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // When lastRun is set, from is ignored. lastRun + 300s = 10:03:00
        self::assertSame('2026-03-18 10:03:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function intervalReturnsCorrectInterval(): void
    {
        $trigger = new IntervalTrigger(1800);

        self::assertSame(1800, $trigger->getIntervalInSeconds());
    }

    #[Test]
    public function intervalToStringFormatsCorrectly(): void
    {
        $trigger = new IntervalTrigger(300);

        self::assertSame('every 300 seconds', (string) $trigger);
    }

    #[Test]
    public function intervalThrowsOnZeroInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval must be positive.');

        new IntervalTrigger(0);
    }

    #[Test]
    public function intervalThrowsOnNegativeInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval must be positive.');

        new IntervalTrigger(-10);
    }

    #[Test]
    public function intervalConsecutiveRunsAdvanceCorrectly(): void
    {
        $trigger = new IntervalTrigger(600);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $first = $trigger->getNextRunDate($now);
        self::assertEquals($now, $first);

        $second = $trigger->getNextRunDate($now, $first);
        self::assertSame('2026-03-18 10:10:00', $second->format('Y-m-d H:i:s'));

        $third = $trigger->getNextRunDate($now, $second);
        self::assertSame('2026-03-18 10:20:00', $third->format('Y-m-d H:i:s'));
    }

    // -----------------------------------------------------------------------
    // DateTimeTrigger
    // -----------------------------------------------------------------------

    #[Test]
    public function dateTimeImplementsTriggerInterface(): void
    {
        $trigger = new DateTimeTrigger(new \DateTimeImmutable('2026-12-31 23:59:59'));

        self::assertInstanceOf(TriggerInterface::class, $trigger);
    }

    #[Test]
    public function dateTimeReturnsDateWhenInFuture(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 15:00:00');
        $trigger = new DateTimeTrigger($target);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertEquals($target, $next);
    }

    #[Test]
    public function dateTimeReturnsNullWhenInPast(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 08:00:00');
        $trigger = new DateTimeTrigger($target);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertNull($next);
    }

    #[Test]
    public function dateTimeReturnsNullWhenExactlyNow(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 10:00:00');
        $trigger = new DateTimeTrigger($target);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        // $target <= $now is true, so returns null
        $next = $trigger->getNextRunDate($now);

        self::assertNull($next);
    }

    #[Test]
    public function dateTimeReturnsNullAfterFirstFire(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 15:00:00');
        $trigger = new DateTimeTrigger($target);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 15:00:00');

        // Once fired (lastRun is set), always returns null regardless of now
        $next = $trigger->getNextRunDate($now, $lastRun);

        self::assertNull($next);
    }

    #[Test]
    public function dateTimeReturnsNullAfterFirstFireEvenWithEarlyLastRun(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 15:00:00');
        $trigger = new DateTimeTrigger($target);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:00:00');

        // Any non-null lastRun means "already fired" for a one-shot trigger
        $next = $trigger->getNextRunDate($now, $lastRun);

        self::assertNull($next);
    }

    #[Test]
    public function dateTimeIntervalIsAlwaysNull(): void
    {
        $trigger = new DateTimeTrigger(new \DateTimeImmutable('2026-12-31 23:59:59'));

        self::assertNull($trigger->getIntervalInSeconds());
    }

    #[Test]
    public function dateTimeToStringReturnsAtomFormat(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 15:00:00+09:00');
        $trigger = new DateTimeTrigger($target);

        self::assertSame($target->format(\DateTimeInterface::ATOM), (string) $trigger);
    }

    #[Test]
    public function dateTimeGetDateTimeReturnsOriginalValue(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 15:00:00');
        $trigger = new DateTimeTrigger($target);

        self::assertEquals($target, $trigger->getDateTime());
    }

    #[Test]
    public function dateTimeIsOneShotTrigger(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 15:00:00');
        $trigger = new DateTimeTrigger($target);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        // First call returns the target date
        $first = $trigger->getNextRunDate($now);
        self::assertNotNull($first);

        // Simulate firing and checking again
        $afterFire = $trigger->getNextRunDate($now, $first);
        self::assertNull($afterFire);
    }

    // -----------------------------------------------------------------------
    // JitterTrigger
    // -----------------------------------------------------------------------

    #[Test]
    public function jitterImplementsTriggerInterface(): void
    {
        $inner = new IntervalTrigger(300);
        $trigger = new JitterTrigger($inner);

        self::assertInstanceOf(TriggerInterface::class, $trigger);
    }

    #[Test]
    public function jitterAddsRandomOffsetWithinBounds(): void
    {
        $inner = new IntervalTrigger(3600);
        $maxJitter = 120;
        $trigger = new JitterTrigger($inner, $maxJitter);

        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:30:00');

        // Inner trigger would return 10:30:00, jitter adds 0-120 seconds
        $expectedBase = new \DateTimeImmutable('2026-03-18 10:30:00');
        $expectedMax = $expectedBase->modify("+{$maxJitter} seconds");

        // Run multiple times to test bounds (statistical)
        for ($i = 0; $i < 50; $i++) {
            $next = $trigger->getNextRunDate($now, $lastRun);
            self::assertNotNull($next);
            self::assertGreaterThanOrEqual($expectedBase, $next);
            self::assertLessThanOrEqual($expectedMax, $next);
        }
    }

    #[Test]
    public function jitterReturnsNullWhenInnerReturnsNull(): void
    {
        // DateTimeTrigger returns null when already fired
        $inner = new DateTimeTrigger(new \DateTimeImmutable('2026-03-18 15:00:00'));
        $trigger = new JitterTrigger($inner);

        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 15:00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        self::assertNull($next);
    }

    #[Test]
    public function jitterDelegatesIntervalToInner(): void
    {
        $inner = new IntervalTrigger(600);
        $trigger = new JitterTrigger($inner);

        self::assertSame(600, $trigger->getIntervalInSeconds());
    }

    #[Test]
    public function jitterDelegatesNullIntervalFromInner(): void
    {
        $inner = new DateTimeTrigger(new \DateTimeImmutable('2026-12-31 23:59:59'));
        $trigger = new JitterTrigger($inner);

        self::assertNull($trigger->getIntervalInSeconds());
    }

    #[Test]
    public function jitterToStringAppendsSuffix(): void
    {
        $inner = new IntervalTrigger(300);
        $trigger = new JitterTrigger($inner);

        self::assertSame('every 300 seconds (with jitter)', (string) $trigger);
    }

    #[Test]
    public function jitterGetInnerTriggerReturnsWrappedTrigger(): void
    {
        $inner = new IntervalTrigger(300);
        $trigger = new JitterTrigger($inner);

        self::assertSame($inner, $trigger->getInnerTrigger());
    }

    #[Test]
    public function jitterDefaultMaxIsSixtySeconds(): void
    {
        $inner = new IntervalTrigger(3600);
        $trigger = new JitterTrigger($inner);

        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:30:00');

        $expectedBase = new \DateTimeImmutable('2026-03-18 10:30:00');
        $expectedMax = $expectedBase->modify('+60 seconds');

        for ($i = 0; $i < 50; $i++) {
            $next = $trigger->getNextRunDate($now, $lastRun);
            self::assertNotNull($next);
            self::assertGreaterThanOrEqual($expectedBase, $next);
            self::assertLessThanOrEqual($expectedMax, $next);
        }
    }

    #[Test]
    public function jitterWithZeroMaxAddsNoJitter(): void
    {
        $inner = new IntervalTrigger(600);
        $trigger = new JitterTrigger($inner, 0);

        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:50:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // random_int(0, 0) always returns 0
        self::assertSame('2026-03-18 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function jitterCanNestWithMultipleTriggers(): void
    {
        $inner = new IntervalTrigger(3600);
        $jittered = new JitterTrigger($inner, 30);
        $doubleJittered = new JitterTrigger($jittered, 10);

        self::assertSame('every 3600 seconds (with jitter) (with jitter)', (string) $doubleJittered);
        self::assertSame($jittered, $doubleJittered->getInnerTrigger());
        self::assertSame(3600, $doubleJittered->getIntervalInSeconds());
    }

    #[Test]
    public function jitterPreservesDateTimeImmutableType(): void
    {
        $inner = new IntervalTrigger(300);
        $trigger = new JitterTrigger($inner, 10);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertInstanceOf(\DateTimeImmutable::class, $next);
    }

    #[Test]
    public function jitterWrappingDateTimeTriggerInFuture(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 15:00:00');
        $inner = new DateTimeTrigger($target);
        $trigger = new JitterTrigger($inner, 30);

        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertNotNull($next);
        self::assertGreaterThanOrEqual($target, $next);
        self::assertLessThanOrEqual($target->modify('+30 seconds'), $next);
    }

    // -----------------------------------------------------------------------
    // WpCronScheduleTrigger
    // -----------------------------------------------------------------------

    #[Test]
    public function wpCronScheduleImplementsTriggerInterface(): void
    {
        $trigger = new WpCronScheduleTrigger('hourly');

        self::assertInstanceOf(TriggerInterface::class, $trigger);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function builtInScheduleProvider(): array
    {
        return [
            'hourly' => ['hourly', 3600],
            'twicedaily' => ['twicedaily', 43200],
            'daily' => ['daily', 86400],
            'weekly' => ['weekly', 604800],
        ];
    }

    #[Test]
    #[DataProvider('builtInScheduleProvider')]
    public function wpCronScheduleBuiltInIntervals(string $schedule, int $expectedInterval): void
    {
        $trigger = new WpCronScheduleTrigger($schedule);

        self::assertSame($expectedInterval, $trigger->getIntervalInSeconds());
    }

    #[Test]
    #[DataProvider('builtInScheduleProvider')]
    public function wpCronScheduleBuiltInToString(string $schedule): void
    {
        $trigger = new WpCronScheduleTrigger($schedule);

        self::assertSame($schedule, (string) $trigger);
    }

    #[Test]
    #[DataProvider('builtInScheduleProvider')]
    public function wpCronScheduleBuiltInScheduleName(string $schedule): void
    {
        $trigger = new WpCronScheduleTrigger($schedule);

        self::assertSame($schedule, $trigger->getScheduleName());
    }

    #[Test]
    public function wpCronScheduleThrowsOnUnknownSchedule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown WP-Cron schedule "nonexistent"');

        new WpCronScheduleTrigger('nonexistent');
    }

    #[Test]
    public function wpCronScheduleReturnsNowWhenNoLastRun(): void
    {
        $trigger = new WpCronScheduleTrigger('hourly');
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertEquals($now, $next);
    }

    #[Test]
    public function wpCronScheduleAddsIntervalToLastRun(): void
    {
        $trigger = new WpCronScheduleTrigger('hourly');
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:30:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // lastRun + 3600s = 10:30:00
        self::assertSame('2026-03-18 10:30:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function wpCronScheduleReturnsNowWhenLastRunPlusIntervalIsPast(): void
    {
        $trigger = new WpCronScheduleTrigger('hourly');
        $now = new \DateTimeImmutable('2026-03-18 12:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 08:00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // lastRun + 3600s = 09:00:00, which is < now, so return now
        self::assertEquals($now, $next);
    }

    #[Test]
    public function wpCronScheduleReturnsNowWhenLastRunPlusIntervalEqualsNow(): void
    {
        $trigger = new WpCronScheduleTrigger('daily');
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-17 10:00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // lastRun + 86400s = now, $next > $now is false, return $now
        self::assertEquals($now, $next);
    }

    #[Test]
    public function wpCronScheduleDailyConsecutiveRuns(): void
    {
        $trigger = new WpCronScheduleTrigger('daily');
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $first = $trigger->getNextRunDate($now);
        self::assertEquals($now, $first);

        $second = $trigger->getNextRunDate($now, $first);
        self::assertSame('2026-03-19 10:00:00', $second->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function wpCronScheduleWeeklyInterval(): void
    {
        $trigger = new WpCronScheduleTrigger('weekly');
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 10:00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        self::assertSame('2026-03-25 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function wpCronScheduleUsesWordPressRegisteredSchedules(): void
    {
        // Register a custom WordPress schedule
        add_filter('cron_schedules', static function (array $schedules): array {
            $schedules['every_fifteen_minutes'] = [
                'interval' => 900,
                'display' => 'Every 15 Minutes',
            ];

            return $schedules;
        });

        $trigger = new WpCronScheduleTrigger('every_fifteen_minutes');

        self::assertSame(900, $trigger->getIntervalInSeconds());
        self::assertSame('every_fifteen_minutes', (string) $trigger);
        self::assertSame('every_fifteen_minutes', $trigger->getScheduleName());
    }

    #[Test]
    public function wpCronScheduleExceptionMessageIncludesKnownSchedules(): void
    {
        try {
            new WpCronScheduleTrigger('bad_schedule');
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('hourly', $e->getMessage());
            self::assertStringContainsString('twicedaily', $e->getMessage());
            self::assertStringContainsString('daily', $e->getMessage());
            self::assertStringContainsString('weekly', $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Cross-trigger: TriggerInterface contract verification
    // -----------------------------------------------------------------------

    #[Test]
    public function allTriggersAreStringable(): void
    {
        $triggers = [
            new CronExpressionTrigger('*/5 * * * *'),
            new IntervalTrigger(300),
            new DateTimeTrigger(new \DateTimeImmutable('2026-12-31 23:59:59')),
            new JitterTrigger(new IntervalTrigger(300)),
            new WpCronScheduleTrigger('hourly'),
        ];

        foreach ($triggers as $trigger) {
            self::assertInstanceOf(\Stringable::class, $trigger);
            self::assertNotEmpty((string) $trigger);
        }
    }

    #[Test]
    public function allTriggersReturnDateTimeImmutable(): void
    {
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $triggers = [
            new CronExpressionTrigger('*/5 * * * *'),
            new IntervalTrigger(300),
            new JitterTrigger(new IntervalTrigger(300)),
            new WpCronScheduleTrigger('hourly'),
        ];

        foreach ($triggers as $trigger) {
            $next = $trigger->getNextRunDate($now);
            self::assertInstanceOf(\DateTimeImmutable::class, $next);
        }
    }

    #[Test]
    public function recurringTriggersNeverReturnNull(): void
    {
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:00:00');

        $triggers = [
            new CronExpressionTrigger('*/5 * * * *'),
            new IntervalTrigger(300),
            new WpCronScheduleTrigger('hourly'),
        ];

        foreach ($triggers as $trigger) {
            $next = $trigger->getNextRunDate($now);
            self::assertNotNull($next, sprintf('%s should never return null for getNextRunDate without lastRun', $trigger::class));

            $nextWithLast = $trigger->getNextRunDate($now, $lastRun);
            self::assertNotNull($nextWithLast, sprintf('%s should never return null for getNextRunDate with lastRun', $trigger::class));
        }
    }

    #[Test]
    public function oneShotTriggerReturnsNullAfterFiring(): void
    {
        $target = new \DateTimeImmutable('2026-03-18 15:00:00');
        $trigger = new DateTimeTrigger($target);
        $now = new \DateTimeImmutable('2026-03-18 10:00:00');

        $first = $trigger->getNextRunDate($now);
        self::assertNotNull($first);

        $second = $trigger->getNextRunDate($now, $first);
        self::assertNull($second, 'DateTimeTrigger is one-shot and should return null after firing');
    }

    #[Test]
    public function jitterWrappingWpCronScheduleStaysWithinBounds(): void
    {
        $inner = new WpCronScheduleTrigger('hourly');
        $trigger = new JitterTrigger($inner, 60);

        $now = new \DateTimeImmutable('2026-03-18 10:00:00');
        $lastRun = new \DateTimeImmutable('2026-03-18 09:30:00');

        // Inner returns 10:30:00, jitter adds 0-60 seconds
        $expectedBase = new \DateTimeImmutable('2026-03-18 10:30:00');
        $expectedMax = $expectedBase->modify('+60 seconds');

        for ($i = 0; $i < 30; $i++) {
            $next = $trigger->getNextRunDate($now, $lastRun);
            self::assertNotNull($next);
            self::assertGreaterThanOrEqual($expectedBase, $next);
            self::assertLessThanOrEqual($expectedMax, $next);
        }
    }

    #[Test]
    public function jitterWrappingCronExpressionStaysWithinBounds(): void
    {
        $inner = new CronExpressionTrigger('0 * * * *');
        $trigger = new JitterTrigger($inner, 30);

        $now = new \DateTimeImmutable('2026-03-18 10:15:00');

        $expectedBase = new \DateTimeImmutable('2026-03-18 11:00:00');
        $expectedMax = $expectedBase->modify('+30 seconds');

        for ($i = 0; $i < 30; $i++) {
            $next = $trigger->getNextRunDate($now);
            self::assertNotNull($next);
            self::assertGreaterThanOrEqual($expectedBase, $next);
            self::assertLessThanOrEqual($expectedMax, $next);
        }
    }
}
