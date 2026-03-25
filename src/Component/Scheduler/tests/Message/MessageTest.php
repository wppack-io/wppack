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

namespace WpPack\Component\Scheduler\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Exception\InvalidArgumentException;
use WpPack\Component\Scheduler\Message\ActionSchedulerMessage;
use WpPack\Component\Scheduler\Message\OneTimeMessage;
use WpPack\Component\Scheduler\Message\RecurringMessage;
use WpPack\Component\Scheduler\Message\ScheduledMessage;
use WpPack\Component\Scheduler\Message\WpCronMessage;
use WpPack\Component\Scheduler\Schedule;
use WpPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WpPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WpPack\Component\Scheduler\Trigger\IntervalTrigger;
use WpPack\Component\Scheduler\Trigger\TriggerInterface;
use WpPack\Component\Scheduler\Trigger\WpCronScheduleTrigger;

#[CoversClass(RecurringMessage::class)]
#[CoversClass(OneTimeMessage::class)]
#[CoversClass(Schedule::class)]
#[CoversClass(WpCronMessage::class)]
#[CoversClass(ActionSchedulerMessage::class)]
final class MessageTest extends TestCase
{
    // =========================================================================
    // RecurringMessage::every() — interval parsing
    // =========================================================================

    #[Test]
    public function everyCreatesRecurringMessageWithSeconds(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('30 seconds', $msg);

        self::assertInstanceOf(RecurringMessage::class, $recurring);
        self::assertInstanceOf(ScheduledMessage::class, $recurring);
        self::assertSame($msg, $recurring->getMessage());
        self::assertInstanceOf(IntervalTrigger::class, $recurring->getTrigger());
        self::assertSame(30, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyCreatesRecurringMessageWithSingularSecond(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('1 second', $msg);

        self::assertSame(1, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyCreatesRecurringMessageWithMinutes(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('5 minutes', $msg);

        self::assertSame(300, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyCreatesRecurringMessageWithSingularMinute(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('1 minute', $msg);

        self::assertSame(60, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyCreatesRecurringMessageWithHours(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('2 hours', $msg);

        self::assertSame(7200, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyCreatesRecurringMessageWithSingularHour(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('1 hour', $msg);

        self::assertSame(3600, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyCreatesRecurringMessageWithDays(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('3 days', $msg);

        self::assertSame(259200, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyCreatesRecurringMessageWithSingularDay(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('1 day', $msg);

        self::assertSame(86400, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyCreatesRecurringMessageWithWeeks(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('2 weeks', $msg);

        self::assertSame(1209600, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyCreatesRecurringMessageWithSingularWeek(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('1 week', $msg);

        self::assertSame(604800, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function everyThrowsOnInvalidIntervalFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid interval format "invalid"');

        RecurringMessage::every('invalid', new \stdClass());
    }

    #[Test]
    public function everyThrowsOnEmptyInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecurringMessage::every('', new \stdClass());
    }

    #[Test]
    public function everyThrowsOnUnknownUnit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecurringMessage::every('5 months', new \stdClass());
    }

    #[Test]
    public function everyThrowsOnMissingNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecurringMessage::every('minutes', new \stdClass());
    }

    #[Test]
    public function everyTrimsWhitespace(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('  10 seconds  ', $msg);

        self::assertSame(10, $recurring->getTrigger()->getIntervalInSeconds());
    }

    // =========================================================================
    // RecurringMessage::cron()
    // =========================================================================

    #[Test]
    public function cronCreatesRecurringMessageWithCronExpression(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::cron('*/5 * * * *', $msg);

        self::assertInstanceOf(RecurringMessage::class, $recurring);
        self::assertSame($msg, $recurring->getMessage());
        self::assertInstanceOf(CronExpressionTrigger::class, $recurring->getTrigger());
    }

    #[Test]
    public function cronTriggerHasNoFixedInterval(): void
    {
        $recurring = RecurringMessage::cron('0 9 * * 1', new \stdClass());

        self::assertNull($recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function cronTriggerStringRepresentation(): void
    {
        $recurring = RecurringMessage::cron('*/5 * * * *', new \stdClass());

        self::assertSame('*/5 * * * *', (string) $recurring->getTrigger());
    }

    // =========================================================================
    // RecurringMessage::schedule() — WP-Cron schedules
    // =========================================================================

    #[Test]
    public function scheduleCreatesRecurringMessageWithWpCronSchedule(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::schedule('hourly', $msg);

        self::assertInstanceOf(RecurringMessage::class, $recurring);
        self::assertSame($msg, $recurring->getMessage());
        self::assertInstanceOf(WpCronScheduleTrigger::class, $recurring->getTrigger());
        self::assertSame(3600, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function scheduleWorksWithDailySchedule(): void
    {
        $recurring = RecurringMessage::schedule('daily', new \stdClass());

        self::assertSame(86400, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function scheduleWorksWithTwiceDailySchedule(): void
    {
        $recurring = RecurringMessage::schedule('twicedaily', new \stdClass());

        self::assertSame(43200, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function scheduleWorksWithWeeklySchedule(): void
    {
        $recurring = RecurringMessage::schedule('weekly', new \stdClass());

        self::assertSame(604800, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function scheduleThrowsOnUnknownSchedule(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecurringMessage::schedule('nonexistent_schedule', new \stdClass());
    }

    // =========================================================================
    // RecurringMessage::trigger() — custom trigger
    // =========================================================================

    #[Test]
    public function triggerCreatesRecurringMessageWithCustomTrigger(): void
    {
        $trigger = new class implements TriggerInterface {
            public function getNextRunDate(\DateTimeImmutable $now, ?\DateTimeImmutable $lastRun = null): ?\DateTimeImmutable
            {
                return $now->modify('+1 hour');
            }

            public function getIntervalInSeconds(): ?int
            {
                return 3600;
            }

            public function __toString(): string
            {
                return 'custom-trigger';
            }
        };

        $msg = new \stdClass();
        $recurring = RecurringMessage::trigger($trigger, $msg);

        self::assertInstanceOf(RecurringMessage::class, $recurring);
        self::assertSame($trigger, $recurring->getTrigger());
        self::assertSame($msg, $recurring->getMessage());
    }

    // =========================================================================
    // RecurringMessage — name chaining
    // =========================================================================

    #[Test]
    public function recurringMessageNameIsNullByDefault(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass());

        self::assertNull($recurring->getName());
    }

    #[Test]
    public function recurringMessageNameCanBeSet(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass())
            ->name('cleanup-task');

        self::assertSame('cleanup-task', $recurring->getName());
    }

    #[Test]
    public function recurringMessageNameReturnsSelf(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass());
        $result = $recurring->name('my-task');

        self::assertSame($recurring, $result);
    }

    #[Test]
    public function recurringMessageNameCanBeOverwritten(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass())
            ->name('first-name')
            ->name('second-name');

        self::assertSame('second-name', $recurring->getName());
    }

    #[Test]
    public function recurringMessageNameChainsWithEvery(): void
    {
        $msg = new \stdClass();
        $recurring = RecurringMessage::every('30 seconds', $msg)->name('heartbeat');

        self::assertSame('heartbeat', $recurring->getName());
        self::assertSame($msg, $recurring->getMessage());
        self::assertSame(30, $recurring->getTrigger()->getIntervalInSeconds());
    }

    #[Test]
    public function recurringMessageNameChainsWithCron(): void
    {
        $recurring = RecurringMessage::cron('0 * * * *', new \stdClass())->name('hourly-job');

        self::assertSame('hourly-job', $recurring->getName());
    }

    #[Test]
    public function recurringMessageNameChainsWithSchedule(): void
    {
        $recurring = RecurringMessage::schedule('daily', new \stdClass())->name('daily-sync');

        self::assertSame('daily-sync', $recurring->getName());
    }

    // =========================================================================
    // RecurringMessage — message object types
    // =========================================================================

    #[Test]
    public function recurringMessageAcceptsAnonymousClassMessage(): void
    {
        $msg = new class {
            public string $type = 'cleanup';
        };

        $recurring = RecurringMessage::every('1 hour', $msg);

        self::assertSame($msg, $recurring->getMessage());
        self::assertSame('cleanup', $recurring->getMessage()->type);
    }

    #[Test]
    public function recurringMessageImplementsScheduledMessage(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass());

        self::assertInstanceOf(ScheduledMessage::class, $recurring);
    }

    // =========================================================================
    // OneTimeMessage::at()
    // =========================================================================

    #[Test]
    public function atCreatesOneTimeMessageWithDateTimeTrigger(): void
    {
        $dateTime = new \DateTimeImmutable('+1 hour');
        $msg = new \stdClass();
        $oneTime = OneTimeMessage::at($dateTime, $msg);

        self::assertInstanceOf(OneTimeMessage::class, $oneTime);
        self::assertInstanceOf(ScheduledMessage::class, $oneTime);
        self::assertSame($msg, $oneTime->getMessage());
        self::assertInstanceOf(DateTimeTrigger::class, $oneTime->getTrigger());
    }

    #[Test]
    public function atTriggerReturnsCorrectDateTime(): void
    {
        $dateTime = new \DateTimeImmutable('2030-06-15 12:00:00');
        $oneTime = OneTimeMessage::at($dateTime, new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        self::assertEquals($dateTime, $trigger->getDateTime());
    }

    #[Test]
    public function atTriggerHasNoFixedInterval(): void
    {
        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 day'), new \stdClass());

        self::assertNull($oneTime->getTrigger()->getIntervalInSeconds());
    }

    // =========================================================================
    // OneTimeMessage::delay()
    // =========================================================================

    #[Test]
    public function delayCreatesOneTimeMessageWithSeconds(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delay('30 seconds', new \stdClass());
        $after = new \DateTimeImmutable();

        self::assertInstanceOf(OneTimeMessage::class, $oneTime);
        self::assertInstanceOf(DateTimeTrigger::class, $oneTime->getTrigger());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        // Scheduled time should be approximately 30 seconds from now
        self::assertGreaterThanOrEqual(
            $before->modify('+30 seconds')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
        self::assertLessThanOrEqual(
            $after->modify('+31 seconds')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    #[Test]
    public function delayCreatesOneTimeMessageWithSingularSecond(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delay('1 second', new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+1 second')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    #[Test]
    public function delayCreatesOneTimeMessageWithMinutes(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delay('5 minutes', new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+5 minutes')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    #[Test]
    public function delayCreatesOneTimeMessageWithSingularMinute(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delay('1 minute', new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+1 minute')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    #[Test]
    public function delayCreatesOneTimeMessageWithHours(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delay('2 hours', new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+2 hours')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    #[Test]
    public function delayCreatesOneTimeMessageWithSingularHour(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delay('1 hour', new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+1 hour')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    #[Test]
    public function delayCreatesOneTimeMessageWithDays(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delay('3 days', new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+3 days')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    #[Test]
    public function delayCreatesOneTimeMessageWithSingularDay(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delay('1 day', new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+1 day')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    #[Test]
    public function delayThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid delay format "invalid"');

        OneTimeMessage::delay('invalid', new \stdClass());
    }

    #[Test]
    public function delayThrowsOnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OneTimeMessage::delay('', new \stdClass());
    }

    #[Test]
    public function delayThrowsOnUnsupportedUnit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OneTimeMessage::delay('2 weeks', new \stdClass());
    }

    #[Test]
    public function delayThrowsOnMissingNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OneTimeMessage::delay('hours', new \stdClass());
    }

    #[Test]
    public function delayTrimsWhitespace(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delay('  10 seconds  ', new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+10 seconds')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    // =========================================================================
    // OneTimeMessage::delaySeconds()
    // =========================================================================

    #[Test]
    public function delaySecondsCreatesOneTimeMessage(): void
    {
        $before = new \DateTimeImmutable();
        $msg = new \stdClass();
        $oneTime = OneTimeMessage::delaySeconds(60, $msg);
        $after = new \DateTimeImmutable();

        self::assertInstanceOf(OneTimeMessage::class, $oneTime);
        self::assertSame($msg, $oneTime->getMessage());
        self::assertInstanceOf(DateTimeTrigger::class, $oneTime->getTrigger());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+60 seconds')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
        self::assertLessThanOrEqual(
            $after->modify('+61 seconds')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    #[Test]
    public function delaySecondsWithLargeValue(): void
    {
        $before = new \DateTimeImmutable();
        $oneTime = OneTimeMessage::delaySeconds(86400, new \stdClass());

        $trigger = $oneTime->getTrigger();
        self::assertInstanceOf(DateTimeTrigger::class, $trigger);
        $scheduledTime = $trigger->getDateTime();

        self::assertGreaterThanOrEqual(
            $before->modify('+86400 seconds')->getTimestamp(),
            $scheduledTime->getTimestamp(),
        );
    }

    // =========================================================================
    // OneTimeMessage — name chaining
    // =========================================================================

    #[Test]
    public function oneTimeMessageNameIsNullByDefault(): void
    {
        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 hour'), new \stdClass());

        self::assertNull($oneTime->getName());
    }

    #[Test]
    public function oneTimeMessageNameCanBeSet(): void
    {
        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 hour'), new \stdClass())
            ->name('send-welcome-email');

        self::assertSame('send-welcome-email', $oneTime->getName());
    }

    #[Test]
    public function oneTimeMessageNameReturnsSelf(): void
    {
        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 hour'), new \stdClass());
        $result = $oneTime->name('my-task');

        self::assertSame($oneTime, $result);
    }

    #[Test]
    public function oneTimeMessageNameCanBeOverwritten(): void
    {
        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 hour'), new \stdClass())
            ->name('first-name')
            ->name('second-name');

        self::assertSame('second-name', $oneTime->getName());
    }

    #[Test]
    public function oneTimeMessageNameChainsWithDelay(): void
    {
        $oneTime = OneTimeMessage::delay('5 minutes', new \stdClass())->name('delayed-job');

        self::assertSame('delayed-job', $oneTime->getName());
    }

    #[Test]
    public function oneTimeMessageNameChainsWithDelaySeconds(): void
    {
        $oneTime = OneTimeMessage::delaySeconds(120, new \stdClass())->name('quick-task');

        self::assertSame('quick-task', $oneTime->getName());
    }

    // =========================================================================
    // OneTimeMessage — message object types
    // =========================================================================

    #[Test]
    public function oneTimeMessageAcceptsAnonymousClassMessage(): void
    {
        $msg = new class {
            public string $email = 'user@example.com';
        };

        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 hour'), $msg);

        self::assertSame($msg, $oneTime->getMessage());
        self::assertSame('user@example.com', $oneTime->getMessage()->email);
    }

    #[Test]
    public function oneTimeMessageImplementsScheduledMessage(): void
    {
        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 hour'), new \stdClass());

        self::assertInstanceOf(ScheduledMessage::class, $oneTime);
    }

    // =========================================================================
    // Schedule — add / getMessages
    // =========================================================================

    #[Test]
    public function scheduleStartsEmpty(): void
    {
        $schedule = new Schedule();

        self::assertSame([], $schedule->getMessages());
    }

    #[Test]
    public function scheduleAddSingleRecurringMessage(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass());
        $schedule = new Schedule();
        $schedule->add($recurring);

        $messages = $schedule->getMessages();
        self::assertCount(1, $messages);
        self::assertSame($recurring, $messages[0]);
    }

    #[Test]
    public function scheduleAddSingleOneTimeMessage(): void
    {
        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 day'), new \stdClass());
        $schedule = new Schedule();
        $schedule->add($oneTime);

        $messages = $schedule->getMessages();
        self::assertCount(1, $messages);
        self::assertSame($oneTime, $messages[0]);
    }

    #[Test]
    public function scheduleAddMultipleMessages(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass());
        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 day'), new \stdClass());
        $cronMessage = RecurringMessage::cron('0 * * * *', new \stdClass());

        $schedule = new Schedule();
        $schedule->add($recurring);
        $schedule->add($oneTime);
        $schedule->add($cronMessage);

        $messages = $schedule->getMessages();
        self::assertCount(3, $messages);
        self::assertSame($recurring, $messages[0]);
        self::assertSame($oneTime, $messages[1]);
        self::assertSame($cronMessage, $messages[2]);
    }

    #[Test]
    public function scheduleAddReturnsSelf(): void
    {
        $schedule = new Schedule();
        $result = $schedule->add(RecurringMessage::every('1 hour', new \stdClass()));

        self::assertSame($schedule, $result);
    }

    #[Test]
    public function scheduleAddSupportsFluentChaining(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass());
        $oneTime = OneTimeMessage::at(new \DateTimeImmutable('+1 day'), new \stdClass());

        $schedule = new Schedule();
        $schedule
            ->add($recurring)
            ->add($oneTime);

        self::assertCount(2, $schedule->getMessages());
    }

    #[Test]
    public function schedulePreservesInsertionOrder(): void
    {
        $msg1 = RecurringMessage::every('30 seconds', new \stdClass())->name('first');
        $msg2 = RecurringMessage::every('1 minute', new \stdClass())->name('second');
        $msg3 = RecurringMessage::every('5 minutes', new \stdClass())->name('third');

        $schedule = new Schedule();
        $schedule->add($msg1)->add($msg2)->add($msg3);

        $messages = $schedule->getMessages();
        self::assertSame('first', $messages[0]->getName());
        self::assertSame('second', $messages[1]->getName());
        self::assertSame('third', $messages[2]->getName());
    }

    #[Test]
    public function scheduleGetMessagesReturnsList(): void
    {
        $schedule = new Schedule();
        $schedule->add(RecurringMessage::every('1 hour', new \stdClass()));
        $schedule->add(OneTimeMessage::delay('5 minutes', new \stdClass()));

        $messages = $schedule->getMessages();

        // Verify it's a list (sequential integer keys starting from 0)
        self::assertSame(array_values($messages), $messages);
        self::assertArrayHasKey(0, $messages);
        self::assertArrayHasKey(1, $messages);
    }

    #[Test]
    public function scheduleMixesRecurringAndOneTimeMessages(): void
    {
        $schedule = new Schedule();
        $schedule
            ->add(RecurringMessage::every('1 hour', new \stdClass())->name('hourly-cleanup'))
            ->add(OneTimeMessage::delay('30 seconds', new \stdClass())->name('deferred-init'))
            ->add(RecurringMessage::cron('0 3 * * *', new \stdClass())->name('nightly-backup'))
            ->add(OneTimeMessage::delaySeconds(300, new \stdClass())->name('warmup-cache'))
            ->add(RecurringMessage::schedule('daily', new \stdClass())->name('daily-report'));

        $messages = $schedule->getMessages();
        self::assertCount(5, $messages);

        self::assertInstanceOf(RecurringMessage::class, $messages[0]);
        self::assertInstanceOf(OneTimeMessage::class, $messages[1]);
        self::assertInstanceOf(RecurringMessage::class, $messages[2]);
        self::assertInstanceOf(OneTimeMessage::class, $messages[3]);
        self::assertInstanceOf(RecurringMessage::class, $messages[4]);
    }

    // =========================================================================
    // DateTimeTrigger behavior via OneTimeMessage
    // =========================================================================

    #[Test]
    public function dateTimeTriggerReturnsNullAfterFiring(): void
    {
        $futureDate = new \DateTimeImmutable('+1 hour');
        $oneTime = OneTimeMessage::at($futureDate, new \stdClass());
        $trigger = $oneTime->getTrigger();

        // First call with no lastRun and now before target: returns the scheduled time
        $now = new \DateTimeImmutable('now');
        $nextRun = $trigger->getNextRunDate($now);
        self::assertNotNull($nextRun);

        // After firing (lastRun is set), should return null
        $nextRun = $trigger->getNextRunDate($now, $futureDate);
        self::assertNull($nextRun);
    }

    #[Test]
    public function dateTimeTriggerReturnsNullWhenPastDue(): void
    {
        $pastDate = new \DateTimeImmutable('-1 hour');
        $oneTime = OneTimeMessage::at($pastDate, new \stdClass());
        $trigger = $oneTime->getTrigger();

        $now = new \DateTimeImmutable('now');
        self::assertNull($trigger->getNextRunDate($now));
    }

    #[Test]
    public function dateTimeTriggerStringRepresentation(): void
    {
        $dateTime = new \DateTimeImmutable('2030-01-15T10:30:00+00:00');
        $oneTime = OneTimeMessage::at($dateTime, new \stdClass());

        self::assertSame('2030-01-15T10:30:00+00:00', (string) $oneTime->getTrigger());
    }

    // =========================================================================
    // IntervalTrigger behavior via RecurringMessage
    // =========================================================================

    #[Test]
    public function intervalTriggerReturnsNowWhenNoLastRun(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass());
        $trigger = $recurring->getTrigger();

        $now = new \DateTimeImmutable('2030-01-01 12:00:00');
        $nextRun = $trigger->getNextRunDate($now);

        self::assertEquals($now, $nextRun);
    }

    #[Test]
    public function intervalTriggerCalculatesNextRunFromLastRun(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass());
        $trigger = $recurring->getTrigger();

        $now = new \DateTimeImmutable('2030-01-01 12:00:00');
        $lastRun = new \DateTimeImmutable('2030-01-01 11:30:00');
        $nextRun = $trigger->getNextRunDate($now, $lastRun);

        // lastRun + 1 hour = 12:30, which is after $now, so returns 12:30
        self::assertEquals(new \DateTimeImmutable('2030-01-01 12:30:00'), $nextRun);
    }

    #[Test]
    public function intervalTriggerReturnsNowWhenOverdue(): void
    {
        $recurring = RecurringMessage::every('1 hour', new \stdClass());
        $trigger = $recurring->getTrigger();

        $now = new \DateTimeImmutable('2030-01-01 14:00:00');
        $lastRun = new \DateTimeImmutable('2030-01-01 12:00:00');
        $nextRun = $trigger->getNextRunDate($now, $lastRun);

        // lastRun + 1 hour = 13:00, which is before $now (14:00), so returns $now
        self::assertEquals($now, $nextRun);
    }

    #[Test]
    public function intervalTriggerStringRepresentation(): void
    {
        $recurring = RecurringMessage::every('5 minutes', new \stdClass());

        self::assertSame('every 300 seconds', (string) $recurring->getTrigger());
    }

    #[Test]
    public function intervalTriggerRejectsZeroInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IntervalTrigger(0);
    }

    #[Test]
    public function intervalTriggerRejectsNegativeInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IntervalTrigger(-1);
    }

    // =========================================================================
    // WpCronMessage
    // =========================================================================

    #[Test]
    public function wpCronMessageDefaultValues(): void
    {
        $message = new WpCronMessage('my_hook');

        self::assertSame('my_hook', $message->hook);
        self::assertSame([], $message->args);
        self::assertFalse($message->schedule);
        self::assertSame(0, $message->timestamp);
    }

    #[Test]
    public function wpCronMessageWithAllProperties(): void
    {
        $message = new WpCronMessage(
            hook: 'cleanup_hook',
            args: ['arg1', 'arg2'],
            schedule: 'hourly',
            timestamp: 1700000000,
        );

        self::assertSame('cleanup_hook', $message->hook);
        self::assertSame(['arg1', 'arg2'], $message->args);
        self::assertSame('hourly', $message->schedule);
        self::assertSame(1700000000, $message->timestamp);
    }

    #[Test]
    public function wpCronMessageIsReadonly(): void
    {
        $reflection = new \ReflectionClass(WpCronMessage::class);

        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function wpCronMessageWithFalseSchedule(): void
    {
        $message = new WpCronMessage(
            hook: 'one_time_hook',
            schedule: false,
        );

        self::assertFalse($message->schedule);
    }

    // =========================================================================
    // ActionSchedulerMessage
    // =========================================================================

    #[Test]
    public function actionSchedulerMessageDefaultValues(): void
    {
        $message = new ActionSchedulerMessage('my_action');

        self::assertSame('my_action', $message->hook);
        self::assertSame([], $message->args);
        self::assertSame('', $message->group);
        self::assertSame(0, $message->actionId);
    }

    #[Test]
    public function actionSchedulerMessageWithAllProperties(): void
    {
        $message = new ActionSchedulerMessage(
            hook: 'process_order',
            args: [42, 'premium'],
            group: 'orders',
            actionId: 123,
        );

        self::assertSame('process_order', $message->hook);
        self::assertSame([42, 'premium'], $message->args);
        self::assertSame('orders', $message->group);
        self::assertSame(123, $message->actionId);
    }

    #[Test]
    public function actionSchedulerMessageIsReadonly(): void
    {
        $reflection = new \ReflectionClass(ActionSchedulerMessage::class);

        self::assertTrue($reflection->isReadOnly());
    }
}
