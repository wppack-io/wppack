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

namespace WPPack\Component\Scheduler\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scheduler\Exception\InvalidArgumentException;
use WPPack\Component\Scheduler\Message\OneTimeMessage;
use WPPack\Component\Scheduler\Message\RecurringMessage;
use WPPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WPPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WPPack\Component\Scheduler\Trigger\IntervalTrigger;
use WPPack\Component\Scheduler\Trigger\WpCronScheduleTrigger;

#[CoversClass(OneTimeMessage::class)]
#[CoversClass(RecurringMessage::class)]
final class MessagesTest extends TestCase
{
    // ── OneTimeMessage ─────────────────────────────────────────────────

    #[Test]
    public function atProducesDateTimeTrigger(): void
    {
        $when = new \DateTimeImmutable('2030-01-01');
        $payload = new \stdClass();

        $msg = OneTimeMessage::at($when, $payload);

        self::assertSame($payload, $msg->getMessage());
        self::assertInstanceOf(DateTimeTrigger::class, $msg->getTrigger());
        self::assertSame($when->format(\DateTimeInterface::ATOM), $msg->getTrigger()->getDateTime()->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function delaySecondsShiftsForwardByRequestedSeconds(): void
    {
        $msg = OneTimeMessage::delaySeconds(600, new \stdClass());

        $trigger = $msg->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);

        $now = new \DateTimeImmutable();
        $diff = $trigger->getDateTime()->getTimestamp() - $now->getTimestamp();

        // Allow 5 seconds of timing slop
        self::assertGreaterThan(595, $diff);
        self::assertLessThan(605, $diff);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function oneTimeDelayProvider(): iterable
    {
        yield 'seconds' => ['30 seconds', 30];
        yield 'minutes' => ['5 minutes', 300];
        yield 'hour' => ['1 hour', 3600];
        yield 'hours' => ['3 hours', 10800];
        yield 'day' => ['1 day', 86400];
        yield 'days' => ['2 days', 172800];
    }

    #[Test]
    #[DataProvider('oneTimeDelayProvider')]
    public function delayParsesDurationUnits(string $delay, int $expectedSeconds): void
    {
        $msg = OneTimeMessage::delay($delay, new \stdClass());
        $now = new \DateTimeImmutable();

        $diff = $msg->getTrigger()->getDateTime()->getTimestamp() - $now->getTimestamp();

        self::assertGreaterThan($expectedSeconds - 5, $diff);
        self::assertLessThan($expectedSeconds + 5, $diff);
    }

    #[Test]
    public function delayRejectsMalformedFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OneTimeMessage::delay('tomorrow morning', new \stdClass());
    }

    #[Test]
    public function nameIsChainableAndRetrievable(): void
    {
        $msg = OneTimeMessage::delaySeconds(60, new \stdClass());

        self::assertNull($msg->getName());

        $result = $msg->name('provision-user-42');

        self::assertSame($msg, $result, 'name() returns $this for chaining');
        self::assertSame('provision-user-42', $msg->getName());
    }

    // ── RecurringMessage ───────────────────────────────────────────────

    #[Test]
    public function scheduleProducesWpCronScheduleTrigger(): void
    {
        $msg = RecurringMessage::schedule('hourly', new \stdClass());

        self::assertInstanceOf(WpCronScheduleTrigger::class, $msg->getTrigger());
    }

    #[Test]
    public function cronProducesCronExpressionTrigger(): void
    {
        $msg = RecurringMessage::cron('0 * * * *', new \stdClass());

        self::assertInstanceOf(CronExpressionTrigger::class, $msg->getTrigger());
    }

    #[Test]
    public function triggerFactoryAllowsArbitraryTriggerInjection(): void
    {
        $trigger = new IntervalTrigger(60);
        $msg = RecurringMessage::trigger($trigger, new \stdClass());

        self::assertSame($trigger, $msg->getTrigger());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function recurringIntervalProvider(): iterable
    {
        yield 'seconds' => ['30 seconds', 30];
        yield 'minutes' => ['5 minutes', 300];
        yield 'hour' => ['1 hour', 3600];
        yield 'days' => ['3 days', 259200];
        yield 'week' => ['1 week', 604800];
        yield 'weeks' => ['2 weeks', 1209600];
    }

    #[Test]
    #[DataProvider('recurringIntervalProvider')]
    public function everyParsesIntervalUnits(string $interval, int $expectedSeconds): void
    {
        $msg = RecurringMessage::every($interval, new \stdClass());
        $trigger = $msg->getTrigger();

        self::assertInstanceOf(IntervalTrigger::class, $trigger);
        self::assertSame($expectedSeconds, $trigger->getIntervalInSeconds());
    }

    #[Test]
    public function everyRejectsMalformedInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecurringMessage::every('forever', new \stdClass());
    }

    #[Test]
    public function recurringMessageNameIsChainable(): void
    {
        $msg = RecurringMessage::every('1 hour', new \stdClass());

        self::assertNull($msg->getName());
        self::assertSame($msg, $msg->name('nightly-cleanup'));
        self::assertSame('nightly-cleanup', $msg->getName());
    }
}
