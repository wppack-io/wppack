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

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WpPack\Component\Scheduler\Exception\InvalidArgumentException;
use WpPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WpPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WpPack\Component\Scheduler\Trigger\IntervalTrigger;
use WpPack\Component\Scheduler\Trigger\JitterTrigger;
use WpPack\Component\Scheduler\Trigger\TriggerInterface;
use WpPack\Component\Scheduler\Trigger\WpCronScheduleTrigger;

final class EventBridgeScheduleFactoryTest extends TestCase
{
    private EventBridgeScheduleFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new EventBridgeScheduleFactory();
    }

    #[Test]
    public function intervalTrigger300SecondsConvertsToRate5Minutes(): void
    {
        $trigger = new IntervalTrigger(300);
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(5 minutes)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function intervalTrigger3600SecondsConvertsToRate1Hour(): void
    {
        $trigger = new IntervalTrigger(3600);
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(1 hour)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function intervalTrigger7200SecondsConvertsToRate2Hours(): void
    {
        $trigger = new IntervalTrigger(7200);
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(2 hours)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function intervalTrigger60SecondsConvertsToRate1Minute(): void
    {
        $trigger = new IntervalTrigger(60);
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(1 minute)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function intervalTriggerSubMinuteConvertsToRate1Minute(): void
    {
        $trigger = new IntervalTrigger(30);
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(1 minute)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function intervalTriggerNonEvenMinutesRoundsUp(): void
    {
        $trigger = new IntervalTrigger(90); // 1.5 minutes
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(2 minutes)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function wpCronScheduleHourlyConvertsToRate1Hour(): void
    {
        $trigger = new WpCronScheduleTrigger('hourly');
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(1 hour)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function wpCronScheduleTwicedailyConvertsToRate12Hours(): void
    {
        $trigger = new WpCronScheduleTrigger('twicedaily');
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(12 hours)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function wpCronScheduleDailyConvertsToRate24Hours(): void
    {
        $trigger = new WpCronScheduleTrigger('daily');
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(24 hours)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function wpCronScheduleWeeklyConvertsToRate168Hours(): void
    {
        $trigger = new WpCronScheduleTrigger('weekly');
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(168 hours)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function cronExpressionConvertsToEventBridge6Field(): void
    {
        $trigger = new CronExpressionTrigger('0 3 * * *');
        $result = $this->factory->createExpression($trigger);

        self::assertSame('cron(0 3 * * ? *)', $result['expression']);
        self::assertSame('cron', $result['type']);
    }

    #[Test]
    public function cronExpressionWithDowStarReplacesWithQuestionMark(): void
    {
        $trigger = new CronExpressionTrigger('30 2 15 * *');
        $result = $this->factory->createExpression($trigger);

        // dom=15, dow=* → dow becomes ?
        self::assertSame('cron(30 2 15 * ? *)', $result['expression']);
        self::assertSame('cron', $result['type']);
    }

    #[Test]
    public function cronExpressionWithDomStarAndSpecificDowReplacesCorrectly(): void
    {
        $trigger = new CronExpressionTrigger('0 9 * * 1');
        $result = $this->factory->createExpression($trigger);

        // dom=*, dow=1 → dom becomes ?
        self::assertSame('cron(0 9 ? * 1 *)', $result['expression']);
        self::assertSame('cron', $result['type']);
    }

    #[Test]
    public function dateTimeTriggerConvertsToAtExpression(): void
    {
        $dateTime = new \DateTimeImmutable('2025-12-31T23:59:59', new \DateTimeZone('UTC'));
        $trigger = new DateTimeTrigger($dateTime);
        $result = $this->factory->createExpression($trigger);

        self::assertSame('at(2025-12-31T23:59:59)', $result['expression']);
        self::assertSame('at', $result['type']);
    }

    #[Test]
    public function dateTimeTriggerConvertsNonUtcToUtc(): void
    {
        $dateTime = new \DateTimeImmutable('2025-12-31T23:59:59', new \DateTimeZone('Asia/Tokyo'));
        $trigger = new DateTimeTrigger($dateTime);
        $result = $this->factory->createExpression($trigger);

        // Asia/Tokyo is UTC+9
        self::assertSame('at(2025-12-31T14:59:59)', $result['expression']);
        self::assertSame('at', $result['type']);
    }

    #[Test]
    public function jitterTriggerIsUnwrapped(): void
    {
        $inner = new IntervalTrigger(3600);
        $trigger = new JitterTrigger($inner, 30);
        $result = $this->factory->createExpression($trigger);

        self::assertSame('rate(1 hour)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function jitterTriggerWithCronIsUnwrapped(): void
    {
        $inner = new CronExpressionTrigger('0 3 * * *');
        $trigger = new JitterTrigger($inner, 60);
        $result = $this->factory->createExpression($trigger);

        self::assertSame('cron(0 3 * * ? *)', $result['expression']);
        self::assertSame('cron', $result['type']);
    }

    #[Test]
    public function cronExpressionWithBothDomAndDowSpecifiedPrefersDom(): void
    {
        // Both dom=15 and dow=1 are specified (neither is * or ?)
        $result = $this->factory->fromCronExpression('0 9 15 * 1');

        // Both specified — prefer dom, set dow to ?
        self::assertSame('cron(0 9 15 * ? *)', $result['expression']);
        self::assertSame('cron', $result['type']);
    }

    #[Test]
    public function cronExpressionWithQuestionMarkInDomIsPreserved(): void
    {
        // dom=? already, dow=1 — no adjustment needed
        $result = $this->factory->fromCronExpression('0 9 ? * 1');

        self::assertSame('cron(0 9 ? * 1 *)', $result['expression']);
        self::assertSame('cron', $result['type']);
    }

    #[Test]
    public function cronExpressionWithQuestionMarkInDowIsPreserved(): void
    {
        // dom=15, dow=? already — no adjustment needed
        $result = $this->factory->fromCronExpression('0 9 15 * ?');

        self::assertSame('cron(0 9 15 * ? *)', $result['expression']);
        self::assertSame('cron', $result['type']);
    }

    #[Test]
    public function cronExpressionWithInvalidFieldCountThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected 5-field cron expression');

        $this->factory->fromCronExpression('0 9 * *');
    }

    #[Test]
    public function cronExpressionWithTooManyFieldsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected 5-field cron expression');

        $this->factory->fromCronExpression('0 9 * * * 2025');
    }

    #[Test]
    public function fromWpCronIntervalSubMinuteConvertsToRate1Minute(): void
    {
        $result = $this->factory->fromWpCronInterval(30);

        self::assertSame('rate(1 minute)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function fromWpCronIntervalConverts60ToRate1Minute(): void
    {
        $result = $this->factory->fromWpCronInterval(60);

        self::assertSame('rate(1 minute)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function fromWpCronIntervalNonEvenMinutesRoundsUp(): void
    {
        $result = $this->factory->fromWpCronInterval(90);

        self::assertSame('rate(2 minutes)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function unsupportedTriggerTypeThrowsException(): void
    {
        $trigger = new class implements TriggerInterface {
            public function getNextRunDate(\DateTimeImmutable $now, ?\DateTimeImmutable $lastRun = null): ?\DateTimeImmutable
            {
                return null;
            }

            public function getIntervalInSeconds(): ?int
            {
                return null;
            }

            public function __toString(): string
            {
                return 'custom';
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported trigger type');

        $this->factory->createExpression($trigger);
    }

    #[Test]
    public function fromWpCronIntervalConverts3600ToRate1Hour(): void
    {
        $result = $this->factory->fromWpCronInterval(3600);

        self::assertSame('rate(1 hour)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function fromWpCronIntervalConverts86400ToRate24Hours(): void
    {
        $result = $this->factory->fromWpCronInterval(86400);

        self::assertSame('rate(24 hours)', $result['expression']);
        self::assertSame('rate', $result['type']);
    }

    #[Test]
    public function fromTimestampConvertsToAtExpression(): void
    {
        // 2025-06-15T12:00:00 UTC
        $timestamp = (new \DateTimeImmutable('2025-06-15T12:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        $result = $this->factory->fromTimestamp($timestamp);

        self::assertSame('at(2025-06-15T12:00:00)', $result['expression']);
        self::assertSame('at', $result['type']);
    }
}
