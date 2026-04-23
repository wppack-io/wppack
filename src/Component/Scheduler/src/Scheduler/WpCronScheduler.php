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

namespace WPPack\Component\Scheduler\Scheduler;

use WPPack\Component\Scheduler\Exception\LogicException;
use WPPack\Component\Scheduler\Exception\SchedulerException;
use WPPack\Component\Scheduler\Message\ScheduledMessage;
use WPPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WPPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WPPack\Component\Scheduler\Trigger\IntervalTrigger;
use WPPack\Component\Scheduler\Trigger\JitterTrigger;

/**
 * Scheduler backend that persists schedules via WordPress' built-in WP-Cron.
 *
 * Supported triggers:
 *   - DateTimeTrigger → wp_schedule_single_event()
 *   - IntervalTrigger → wp_schedule_event() with an auto-registered
 *                       recurrence named "wppack_every_<seconds>s"
 *
 * NOT supported:
 *   - CronExpressionTrigger — WP-Cron has no native cron-expression
 *     support. Use ActionSchedulerScheduler or EventBridgeScheduler
 *     when you need cron semantics.
 *
 * scheduleId is used as the wp-cron hook name; message payload is
 * PHP-serialized + base64-encoded into a single positional arg. Register
 * an `add_action($scheduleId, ...)` handler to execute the scheduled
 * message.
 */
final class WpCronScheduler implements SchedulerInterface
{
    /**
     * Register the cron_schedules filter once per process so
     * wp_schedule_event() accepts our dynamically-named recurrences.
     */
    private bool $filterRegistered = false;

    /** @var array<int, true> Interval seconds we've already exposed. */
    private array $registeredIntervals = [];

    public function schedule(string $scheduleId, ScheduledMessage $message): void
    {
        $trigger = $message->getTrigger();
        if ($trigger instanceof JitterTrigger) {
            $trigger = $trigger->getInnerTrigger();
        }

        $args = [$this->encodePayload($message->getMessage())];

        // Idempotent: clear any prior registration for this scheduleId.
        $this->unschedule($scheduleId);

        match (true) {
            $trigger instanceof DateTimeTrigger => $this->scheduleSingle($trigger, $scheduleId, $args),
            $trigger instanceof IntervalTrigger => $this->scheduleInterval($trigger, $scheduleId, $args),
            $trigger instanceof CronExpressionTrigger => throw new LogicException(
                'WpCronScheduler cannot schedule CronExpressionTrigger. WP-Cron has no cron-expression support; use ActionSchedulerScheduler or EventBridgeScheduler for cron triggers.',
            ),
            default => throw new LogicException(sprintf(
                'WpCronScheduler cannot schedule trigger of type %s.',
                $trigger::class,
            )),
        };
    }

    public function unschedule(string $scheduleId): void
    {
        // wp_clear_scheduled_hook('hook') with empty \$args only clears
        // events whose args are also empty — it does an exact
        // _compare_args() match, not a hook-scoped wipe. Our schedule()
        // always stores a non-empty \$args (the encoded payload), so we
        // iterate _get_cron_array() and unset every event for the hook
        // regardless of args.
        $crons = _get_cron_array();
        $dirty = false;

        foreach ($crons as $timestamp => $hooks) {
            if (!isset($hooks[$scheduleId])) {
                continue;
            }
            unset($crons[$timestamp][$scheduleId]);
            if ($crons[$timestamp] === []) {
                unset($crons[$timestamp]);
            }
            $dirty = true;
        }

        if ($dirty) {
            _set_cron_array($crons);
        }
    }

    public function has(string $scheduleId): bool
    {
        return $this->findNextTimestamp($scheduleId) !== null;
    }

    public function getNextRunDate(string $scheduleId): ?\DateTimeImmutable
    {
        $timestamp = $this->findNextTimestamp($scheduleId);

        if ($timestamp === null) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($timestamp);
    }

    /**
     * wp_next_scheduled(\$hook) with no args only matches events whose
     * args array is empty. We always schedule with the encoded payload
     * as a positional arg, so iterate the cron array manually and
     * return the earliest timestamp for the hook regardless of args.
     */
    private function findNextTimestamp(string $hook): ?int
    {
        $crons = _get_cron_array();

        foreach ($crons as $timestamp => $hooks) {
            if (isset($hooks[$hook]) && $hooks[$hook] !== []) {
                return (int) $timestamp;
            }
        }

        return null;
    }

    public function createScheduleRaw(
        string $scheduleId,
        string $expression,
        string $payload,
        bool $autoDelete = false,
    ): void {
        // createScheduleRaw takes an EventBridge-formatted expression
        // that doesn't map to WP-Cron's API. Use schedule() with a
        // ScheduledMessage instead.
        throw new LogicException(
            'WpCronScheduler does not implement createScheduleRaw (EventBridge-specific). Use schedule() with a ScheduledMessage.',
        );
    }

    /**
     * @param list<string> $args
     */
    private function scheduleSingle(DateTimeTrigger $trigger, string $hook, array $args): void
    {
        $timestamp = $trigger->getDateTime()->getTimestamp();

        // Don't pass \$wp_error here: WP's update_option returns false when
        // the new cron array is identical to the stored one (e.g. we just
        // cleared + re-wrote the same data), which is semantically "already
        // there" and would otherwise surface as a spurious 'could_not_set'
        // WP_Error. We verify via _get_cron_array below to distinguish a
        // real failure from a no-op write.
        wp_schedule_single_event($timestamp, $hook, $args);

        if ($this->findNextTimestamp($hook) === null) {
            throw new SchedulerException(sprintf(
                'Failed to schedule single event "%s": wp_schedule_single_event() did not persist the event.',
                $hook,
            ));
        }
    }

    /**
     * @param list<string> $args
     */
    private function scheduleInterval(IntervalTrigger $trigger, string $hook, array $args): void
    {
        $seconds = $trigger->getIntervalInSeconds();
        if ($seconds <= 0) {
            throw new LogicException('IntervalTrigger::getIntervalInSeconds() returned non-positive.');
        }

        $recurrence = $this->ensureRecurrenceRegistered($seconds);
        $start = $trigger->getNextRunDate(new \DateTimeImmutable())->getTimestamp();

        wp_schedule_event($start, $recurrence, $hook, $args);

        if ($this->findNextTimestamp($hook) === null) {
            throw new SchedulerException(sprintf(
                'Failed to schedule recurring event "%s": wp_schedule_event() did not persist the event.',
                $hook,
            ));
        }
    }

    /**
     * Dynamically register a cron_schedules entry for arbitrary intervals
     * so wp_schedule_event() can accept them. Returns the recurrence
     * name keyed to \$seconds.
     */
    private function ensureRecurrenceRegistered(int $seconds): string
    {
        $name = \sprintf('wppack_every_%ds', $seconds);

        if (isset($this->registeredIntervals[$seconds])) {
            return $name;
        }

        $this->registeredIntervals[$seconds] = true;

        if (!$this->filterRegistered) {
            add_filter('cron_schedules', function (array $schedules): array {
                foreach (array_keys($this->registeredIntervals) as $s) {
                    $key = \sprintf('wppack_every_%ds', $s);
                    if (!isset($schedules[$key])) {
                        $schedules[$key] = [
                            'interval' => $s,
                            'display' => \sprintf('Every %d seconds (WPPack)', $s),
                        ];
                    }
                }

                return $schedules;
            });
            $this->filterRegistered = true;
        }

        return $name;
    }

    private function encodePayload(object $message): string
    {
        return base64_encode(serialize($message));
    }
}
