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

namespace WPPack\Component\Scheduler\Tests\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scheduler\Exception\InvalidArgumentException;
use WPPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WPPack\Component\Scheduler\Trigger\WpCronScheduleTrigger;

#[CoversClass(CronExpressionTrigger::class)]
#[CoversClass(WpCronScheduleTrigger::class)]
final class CronAndWpCronTriggersTest extends TestCase
{
    // ── CronExpressionTrigger ──────────────────────────────────────────

    #[Test]
    public function cronExpressionReturnsImmutableNext(): void
    {
        $trigger = new CronExpressionTrigger('0 0 * * *');
        $now = new \DateTimeImmutable('2024-06-15T12:30:00+00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertInstanceOf(\DateTimeImmutable::class, $next);
        // Next midnight is 2024-06-16T00:00:00
        self::assertSame('2024-06-16T00:00:00', $next->format('Y-m-d\TH:i:s'));
    }

    #[Test]
    public function cronExpressionComputesFromLastRunWhenProvided(): void
    {
        $trigger = new CronExpressionTrigger('*/15 * * * *');
        $now = new \DateTimeImmutable('2024-01-01T10:30:00+00:00');
        $lastRun = new \DateTimeImmutable('2024-01-01T09:45:00+00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        // Next quarter after 09:45 is 10:00
        self::assertSame('2024-01-01T10:00:00', $next->format('Y-m-d\TH:i:s'));
    }

    #[Test]
    public function cronExpressionIntervalIsNullBecauseCronIsNotPeriodic(): void
    {
        $trigger = new CronExpressionTrigger('0 0 * * *');

        self::assertNull($trigger->getIntervalInSeconds());
    }

    #[Test]
    public function cronExpressionStringifiesAsExpression(): void
    {
        self::assertSame('*/5 * * * *', (string) new CronExpressionTrigger('*/5 * * * *'));
    }

    #[Test]
    public function cronExpressionRejectsInvalidExpression(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CronExpressionTrigger('not a cron expression');
    }

    // ── WpCronScheduleTrigger ──────────────────────────────────────────

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function wpCronScheduleProvider(): iterable
    {
        yield 'hourly' => ['hourly', 3600];
        yield 'twicedaily' => ['twicedaily', 43200];
        yield 'daily' => ['daily', 86400];
        yield 'weekly' => ['weekly', 604800];
    }

    #[Test]
    #[DataProvider('wpCronScheduleProvider')]
    public function wpCronKnownSchedulesExposeExpectedInterval(string $schedule, int $expectedSeconds): void
    {
        $trigger = new WpCronScheduleTrigger($schedule);

        self::assertSame($expectedSeconds, $trigger->getIntervalInSeconds());
        self::assertSame($schedule, $trigger->getScheduleName());
        self::assertSame($schedule, (string) $trigger);
    }

    #[Test]
    public function wpCronUnknownScheduleThrowsInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown WP-Cron schedule');

        new WpCronScheduleTrigger('monthly-bogus-' . uniqid());
    }

    #[Test]
    public function wpCronNextRunWithoutLastRunReturnsNow(): void
    {
        $trigger = new WpCronScheduleTrigger('hourly');
        $now = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');

        $next = $trigger->getNextRunDate($now);

        self::assertSame($now->format(\DateTimeInterface::ATOM), $next->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function wpCronNextRunShiftsForwardByIntervalAfterLastRun(): void
    {
        $trigger = new WpCronScheduleTrigger('hourly');
        $lastRun = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $now = new \DateTimeImmutable('2024-01-01T12:30:00+00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        self::assertSame('2024-01-01T13:00:00', $next->format('Y-m-d\TH:i:s'));
    }

    #[Test]
    public function wpCronClampsToNowWhenLastRunIsAncient(): void
    {
        $trigger = new WpCronScheduleTrigger('hourly');
        $lastRun = new \DateTimeImmutable('2000-01-01');
        $now = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        self::assertSame($now->format(\DateTimeInterface::ATOM), $next->format(\DateTimeInterface::ATOM));
    }
}
