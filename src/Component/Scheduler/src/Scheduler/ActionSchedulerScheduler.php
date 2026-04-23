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
 * Scheduler backend that persists schedules via Action Scheduler.
 *
 * Covers all three trigger types natively:
 *   - CronExpressionTrigger → as_schedule_cron_action()
 *   - IntervalTrigger       → as_schedule_recurring_action()
 *   - DateTimeTrigger       → as_schedule_single_action()
 *
 * The scheduleId doubles as the Action Scheduler hook name; the caller is
 * responsible for registering `add_action($scheduleId, ...)` to handle
 * execution (typical Action Scheduler pattern).
 *
 * Message payload is PHP-serialized and base64-encoded into a single
 * positional arg so Action Scheduler's DB column accepts it cleanly.
 * Retrieve via base64_decode + unserialize inside your hook.
 *
 * Requires the action-scheduler plugin (woocommerce/action-scheduler) to
 * be loaded. Every public method throws SchedulerException if it isn't.
 */
final class ActionSchedulerScheduler implements SchedulerInterface
{
    public function __construct(
        private readonly string $group = 'wppack',
    ) {}

    public function schedule(string $scheduleId, ScheduledMessage $message): void
    {
        $this->assertAvailable();

        $trigger = $message->getTrigger();
        if ($trigger instanceof JitterTrigger) {
            // Action Scheduler has no native jitter; unwrap to the inner trigger.
            // The scheduleId itself should already include any jitter offset the
            // caller wanted to bake in.
            $trigger = $trigger->getInnerTrigger();
        }

        $args = [$this->encodePayload($message->getMessage())];

        // Idempotent: Action Scheduler has no upsert, so unschedule any
        // prior registration before re-creating. Matches the semantics
        // EventBridgeScheduler provides via create-then-update fallback.
        $this->unschedule($scheduleId);

        match (true) {
            $trigger instanceof CronExpressionTrigger => as_schedule_cron_action(
                time(),
                (string) $trigger,
                $scheduleId,
                $args,
                $this->group,
            ),
            $trigger instanceof IntervalTrigger => as_schedule_recurring_action(
                $this->resolveStart($trigger),
                $trigger->getIntervalInSeconds(),
                $scheduleId,
                $args,
                $this->group,
            ),
            $trigger instanceof DateTimeTrigger => as_schedule_single_action(
                $trigger->getDateTime()->getTimestamp(),
                $scheduleId,
                $args,
                $this->group,
            ),
            default => throw new LogicException(sprintf(
                'ActionSchedulerScheduler cannot schedule trigger of type %s.',
                $trigger::class,
            )),
        };
    }

    public function unschedule(string $scheduleId): void
    {
        $this->assertAvailable();

        // as_unschedule_all_actions returns void and is idempotent — it
        // removes every pending action matching the hook (we use one hook
        // per scheduleId, so this removes just our schedule).
        as_unschedule_all_actions($scheduleId, [], $this->group);
    }

    public function has(string $scheduleId): bool
    {
        $this->assertAvailable();

        return as_has_scheduled_action($scheduleId, null, $this->group);
    }

    public function getNextRunDate(string $scheduleId): ?\DateTimeImmutable
    {
        $this->assertAvailable();

        $timestamp = as_next_scheduled_action($scheduleId, null, $this->group);

        // as_next_scheduled_action returns false when there is no action,
        // true when there is one but the timestamp isn't known (rare),
        // or an int timestamp.
        if (!\is_int($timestamp) || $timestamp <= 0) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($timestamp);
    }

    public function createScheduleRaw(
        string $scheduleId,
        string $expression,
        string $payload,
        bool $autoDelete = false,
    ): void {
        // createScheduleRaw takes an EventBridge-formatted expression
        // (cron(...)/rate(...)/at(...)) that doesn't map 1:1 to Action
        // Scheduler's API. The EventBridge Interceptors call this
        // directly; for local backends, go through schedule() with a
        // ScheduledMessage instead.
        throw new LogicException(
            'ActionSchedulerScheduler does not implement createScheduleRaw (EventBridge-specific). Use schedule() with a ScheduledMessage, or use the EventBridge Interceptors only with EventBridgeScheduler.',
        );
    }

    /**
     * PHP-serialize + base64 so the payload survives Action Scheduler's
     * JSON column round-trip without escape artifacts.
     */
    private function encodePayload(object $message): string
    {
        return base64_encode(serialize($message));
    }

    private function resolveStart(IntervalTrigger $trigger): int
    {
        $next = $trigger->getNextRunDate(new \DateTimeImmutable());

        return $next->getTimestamp();
    }

    private function assertAvailable(): void
    {
        if (!\function_exists('as_schedule_single_action')) {
            throw new SchedulerException(
                'Action Scheduler is not loaded. Install woocommerce/action-scheduler or switch to WpCronScheduler / EventBridgeScheduler.',
            );
        }
    }
}
