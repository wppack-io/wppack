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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scheduler\Exception\InvalidArgumentException;
use WPPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WPPack\Component\Scheduler\Trigger\IntervalTrigger;
use WPPack\Component\Scheduler\Trigger\JitterTrigger;

#[CoversClass(IntervalTrigger::class)]
#[CoversClass(DateTimeTrigger::class)]
#[CoversClass(JitterTrigger::class)]
final class TriggersTest extends TestCase
{
    // ── IntervalTrigger ────────────────────────────────────────────────

    #[Test]
    public function intervalTriggerRejectsNonPositiveInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IntervalTrigger(0);
    }

    #[Test]
    public function intervalTriggerRejectsNegativeInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IntervalTrigger(-60);
    }

    #[Test]
    public function intervalTriggerNextRunAfterLastRunIsShiftedForward(): void
    {
        $trigger = new IntervalTrigger(3600);
        $lastRun = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $now = new \DateTimeImmutable('2024-01-01T12:30:00+00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        self::assertSame('2024-01-01T13:00:00+00:00', $next->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function intervalTriggerClampsToNowWhenLastRunIsAncient(): void
    {
        $trigger = new IntervalTrigger(60);
        $lastRun = new \DateTimeImmutable('2000-01-01');
        $now = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');

        $next = $trigger->getNextRunDate($now, $lastRun);

        self::assertSame($now->format(\DateTimeInterface::ATOM), $next->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function intervalTriggerWithoutLastRunReturnsFromOrNow(): void
    {
        $from = new \DateTimeImmutable('2024-06-01T12:00:00+00:00');
        $trigger = new IntervalTrigger(60, from: $from);
        $now = new \DateTimeImmutable('2024-01-01');

        self::assertSame($from->format(\DateTimeInterface::ATOM), $trigger->getNextRunDate($now)->format(\DateTimeInterface::ATOM));

        $now = new \DateTimeImmutable('2025-01-01');
        self::assertSame($now->format(\DateTimeInterface::ATOM), $trigger->getNextRunDate($now)->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function intervalTriggerExposesIntervalAndDescription(): void
    {
        $trigger = new IntervalTrigger(300);

        self::assertSame(300, $trigger->getIntervalInSeconds());
        self::assertSame('every 300 seconds', (string) $trigger);
    }

    // ── DateTimeTrigger ────────────────────────────────────────────────

    #[Test]
    public function dateTimeTriggerFiresOnceForFutureDate(): void
    {
        $when = new \DateTimeImmutable('2030-01-01T00:00:00+00:00');
        $trigger = new DateTimeTrigger($when);

        self::assertSame($when, $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01')));
    }

    #[Test]
    public function dateTimeTriggerReturnsNullForPastDate(): void
    {
        $trigger = new DateTimeTrigger(new \DateTimeImmutable('2000-01-01'));

        self::assertNull($trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01')));
    }

    #[Test]
    public function dateTimeTriggerDoesNotFireTwice(): void
    {
        $trigger = new DateTimeTrigger(new \DateTimeImmutable('2030-01-01'));

        self::assertNull($trigger->getNextRunDate(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-01'),
        ));
    }

    #[Test]
    public function dateTimeTriggerHasNoIntervalAndAtomDescription(): void
    {
        $when = new \DateTimeImmutable('2030-01-01T12:34:56+00:00');
        $trigger = new DateTimeTrigger($when);

        self::assertNull($trigger->getIntervalInSeconds());
        self::assertSame($when, $trigger->getDateTime());
        self::assertSame($when->format(\DateTimeInterface::ATOM), (string) $trigger);
    }

    // ── JitterTrigger ──────────────────────────────────────────────────

    #[Test]
    public function jitterTriggerAddsJitterWithinBound(): void
    {
        $inner = new IntervalTrigger(60);
        $trigger = new JitterTrigger($inner, maxJitterSeconds: 30);

        $now = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $next = $trigger->getNextRunDate($now, new \DateTimeImmutable('2024-01-01T11:58:00+00:00'));

        // Inner would return 2024-01-01T12:00:00 (last + 60s, but clamped to now).
        // With jitter [0,30] added: next ∈ [now, now+30].
        self::assertGreaterThanOrEqual($now->getTimestamp(), $next->getTimestamp());
        self::assertLessThanOrEqual($now->getTimestamp() + 30, $next->getTimestamp());
    }

    #[Test]
    public function jitterTriggerPropagatesNullFromInner(): void
    {
        $inner = new DateTimeTrigger(new \DateTimeImmutable('2000-01-01'));
        $trigger = new JitterTrigger($inner);

        self::assertNull($trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01')));
    }

    #[Test]
    public function jitterTriggerDelegatesIntervalAndDescription(): void
    {
        $inner = new IntervalTrigger(60);
        $trigger = new JitterTrigger($inner, maxJitterSeconds: 5);

        self::assertSame(60, $trigger->getIntervalInSeconds());
        self::assertSame($inner, $trigger->getInnerTrigger());
        self::assertSame('every 60 seconds (with jitter)', (string) $trigger);
    }
}
