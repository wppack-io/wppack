<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Interceptor;

use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduler;
use WpPack\Component\Scheduler\Bridge\EventBridge\ScheduleIdGenerator;
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;

/**
 * Intercepts WP-Cron API via pre_* filters and delegates to EventBridge Scheduler.
 *
 * Same approach as Cavalcade (humanmade/Cavalcade): hook into pre_schedule_event etc.
 * to replace the execution backend while keeping the WP-Cron API surface intact.
 *
 * Write: pre_* filters → EventBridge + local wp_options.cron (dual-write)
 * Read:  wp_options.cron directly (fast, admin tool compatible)
 * Exec:  pre_get_ready_cron_jobs → [] (disable local execution, EventBridge is sole executor)
 */
final class WpCronInterceptor
{
    private readonly ScheduleIdGenerator $idGenerator;

    public function __construct(
        private readonly EventBridgeScheduler $scheduler,
        private readonly EventBridgeScheduleFactory $scheduleFactory,
        private readonly SqsPayloadFactory $payloadFactory,
    ) {
        $this->idGenerator = new ScheduleIdGenerator();
    }

    public function register(): void
    {
        if (!\defined('DISABLE_WP_CRON')) {
            \define('DISABLE_WP_CRON', true);
        }

        add_filter('pre_schedule_event', [$this, 'onPreScheduleEvent'], 10, 2);
        add_filter('pre_reschedule_event', [$this, 'onPreRescheduleEvent'], 10, 2);
        add_filter('pre_unschedule_event', [$this, 'onPreUnscheduleEvent'], 10, 4);
        add_filter('pre_clear_scheduled_hook', [$this, 'onPreClearScheduledHook'], 10, 3);
        add_filter('pre_unschedule_hook', [$this, 'onPreUnscheduleHook'], 10, 2);
        add_filter('pre_get_ready_cron_jobs', [$this, 'onPreGetReadyCronJobs'], 10, 1);
    }

    public function unregister(): void
    {
        remove_filter('pre_schedule_event', [$this, 'onPreScheduleEvent'], 10);
        remove_filter('pre_reschedule_event', [$this, 'onPreRescheduleEvent'], 10);
        remove_filter('pre_unschedule_event', [$this, 'onPreUnscheduleEvent'], 10);
        remove_filter('pre_clear_scheduled_hook', [$this, 'onPreClearScheduledHook'], 10);
        remove_filter('pre_unschedule_hook', [$this, 'onPreUnscheduleHook'], 10);
        remove_filter('pre_get_ready_cron_jobs', [$this, 'onPreGetReadyCronJobs'], 10);
    }

    /**
     * Intercept wp_schedule_event() / wp_schedule_single_event().
     *
     * Creates an EventBridge schedule and writes to local cron array for admin visibility.
     *
     * @param null|bool $pre  Short-circuit value from prior filters
     * @param \stdClass $event WP-Cron event object {hook, args, timestamp, schedule, interval?}
     */
    public function onPreScheduleEvent(null|bool $pre, \stdClass $event): bool
    {
        if ($pre !== null) {
            return $pre;
        }

        $hook = $event->hook;
        /** @var array<mixed> $args */
        $args = $event->args;
        $timestamp = $event->timestamp;
        /** @var string|false $schedule */
        $schedule = $event->schedule ?? false;

        $scheduleId = $this->idGenerator->forWpCronEvent($hook, $args, $schedule, $timestamp);

        if ($schedule !== false && isset($event->interval)) {
            $expression = $this->scheduleFactory->fromWpCronInterval((int) $event->interval);
        } else {
            $expression = $this->scheduleFactory->fromTimestamp($timestamp);
        }

        $payload = $this->payloadFactory->createForWpCronEvent($hook, $args, $schedule, $timestamp);

        $this->scheduler->createScheduleRaw(
            $scheduleId,
            $expression['expression'],
            $payload,
            $expression['type'] === 'at',
        );

        $this->addToCronArray($timestamp, $hook, $args, $schedule, (int) ($event->interval ?? 0));

        return true;
    }

    /**
     * Intercept wp_reschedule_event().
     *
     * For recurring events, EventBridge rate() handles repetition automatically.
     * Only updates the local cron array with the new timestamp.
     *
     * @param null|bool $pre  Short-circuit value
     * @param \stdClass $event WP-Cron event object {hook, args, timestamp, schedule, interval}
     */
    public function onPreRescheduleEvent(null|bool $pre, \stdClass $event): bool
    {
        if ($pre !== null) {
            return $pre;
        }

        $this->addToCronArray(
            $event->timestamp,
            $event->hook,
            $event->args,
            $event->schedule ?? false,
            (int) ($event->interval ?? 0),
        );

        return true;
    }

    /**
     * Intercept wp_unschedule_event().
     *
     * Deletes the EventBridge schedule and removes from local cron array.
     *
     * @param null|bool   $pre       Short-circuit value
     * @param int         $timestamp Event timestamp
     * @param string      $hook      Hook name
     * @param array<mixed> $args     Hook arguments
     */
    public function onPreUnscheduleEvent(null|bool $pre, int $timestamp, string $hook, array $args): bool
    {
        if ($pre !== null) {
            return $pre;
        }

        $schedule = $this->getScheduleFromCronArray($timestamp, $hook, $args);
        $scheduleId = $this->idGenerator->forWpCronEvent($hook, $args, $schedule, $timestamp);

        $this->scheduler->unschedule($scheduleId);
        $this->removeFromCronArray($timestamp, $hook, $args);

        return true;
    }

    /**
     * Intercept wp_clear_scheduled_hook().
     *
     * Removes all schedules matching hook+args from EventBridge and local cron array.
     *
     * @param null|int    $pre  Short-circuit value
     * @param string      $hook Hook name
     * @param array<mixed> $args Hook arguments
     *
     * @return int Number of unscheduled events
     */
    public function onPreClearScheduledHook(null|int $pre, string $hook, array $args): int
    {
        if ($pre !== null) {
            return $pre;
        }

        $count = 0;
        $crons = _get_cron_array();
        $key = md5(serialize($args));

        foreach ($crons as $timestamp => $cronHooks) {
            if (!isset($cronHooks[$hook][$key])) {
                continue;
            }

            $schedule = $cronHooks[$hook][$key]['schedule'] ?? false;
            $scheduleId = $this->idGenerator->forWpCronEvent($hook, $args, $schedule, (int) $timestamp);

            $this->scheduler->unschedule($scheduleId);
            $this->removeFromCronArray((int) $timestamp, $hook, $args);
            $count++;
        }

        return $count;
    }

    /**
     * Intercept wp_unschedule_hook().
     *
     * Removes ALL schedules for a hook (regardless of args) from EventBridge and local cron.
     *
     * @param null|int $pre  Short-circuit value
     * @param string   $hook Hook name
     *
     * @return int Number of unscheduled events
     */
    public function onPreUnscheduleHook(null|int $pre, string $hook): int
    {
        if ($pre !== null) {
            return $pre;
        }

        $count = 0;
        $crons = _get_cron_array();

        foreach ($crons as $timestamp => $cronHooks) {
            if (!isset($cronHooks[$hook])) {
                continue;
            }

            foreach ($cronHooks[$hook] as $key => $event) {
                $args = $event['args'] ?? [];
                $schedule = $event['schedule'] ?? false;
                $scheduleId = $this->idGenerator->forWpCronEvent($hook, $args, $schedule, (int) $timestamp);

                $this->scheduler->unschedule($scheduleId);
                $count++;
            }

            unset($crons[$timestamp][$hook]);
            if (empty($crons[$timestamp])) {
                unset($crons[$timestamp]);
            }
        }

        _set_cron_array($crons);

        return $count;
    }

    /**
     * Prevent local cron execution — EventBridge is the sole executor.
     *
     * @return array<empty> Empty array
     */
    public function onPreGetReadyCronJobs(mixed $pre): array
    {
        return [];
    }

    /**
     * @param array<mixed> $args
     */
    private function addToCronArray(int $timestamp, string $hook, array $args, string|false $schedule, int $interval): void
    {
        $crons = _get_cron_array();
        $key = md5(serialize($args));

        $entry = [
            'schedule' => $schedule,
            'args' => $args,
        ];

        if ($schedule !== false && $interval > 0) {
            $entry['interval'] = $interval;
        }

        $crons[$timestamp][$hook][$key] = $entry;
        uksort($crons, 'strcmp');

        _set_cron_array($crons);
    }

    /**
     * @param array<mixed> $args
     */
    private function removeFromCronArray(int $timestamp, string $hook, array $args): void
    {
        $crons = _get_cron_array();
        $key = md5(serialize($args));

        unset($crons[$timestamp][$hook][$key]);

        if (isset($crons[$timestamp][$hook]) && empty($crons[$timestamp][$hook])) {
            unset($crons[$timestamp][$hook]);
        }

        if (isset($crons[$timestamp]) && empty($crons[$timestamp])) {
            unset($crons[$timestamp]);
        }

        _set_cron_array($crons);
    }

    /**
     * Look up the schedule name for a specific event in the local cron array.
     *
     * @param array<mixed> $args
     */
    private function getScheduleFromCronArray(int $timestamp, string $hook, array $args): string|false
    {
        $crons = _get_cron_array();
        $key = md5(serialize($args));

        return $crons[$timestamp][$hook][$key]['schedule'] ?? false;
    }
}
