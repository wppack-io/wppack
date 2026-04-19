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

namespace WPPack\Component\Scheduler\Bridge\EventBridge\Interceptor;

use Psr\Log\LoggerInterface;
use WPPack\Component\Scheduler\Bridge\EventBridge\Collector\WpCronCollector;
use WPPack\Component\Scheduler\Bridge\EventBridge\CronArrayHelper;
use WPPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WPPack\Component\Scheduler\Bridge\EventBridge\ScheduleIdGenerator;
use WPPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;
use WPPack\Component\Scheduler\Scheduler\SchedulerInterface;

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
        private readonly SchedulerInterface $scheduler,
        private readonly EventBridgeScheduleFactory $scheduleFactory,
        private readonly SqsPayloadFactory $payloadFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->idGenerator = new ScheduleIdGenerator();
    }

    public function register(): void
    {
        if (!\defined('DISABLE_WP_CRON')) {
            \define('DISABLE_WP_CRON', true); // @codeCoverageIgnore
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
     * Synchronize all existing WP-Cron events to EventBridge.
     *
     * Collects events from the local cron array via WpCronCollector and creates
     * corresponding EventBridge schedules. Intended for initial migration
     * (e.g., plugin activation or WP-CLI `wp wppack scheduler sync`).
     *
     * @return int Number of events synchronized
     */
    public function synchronize(): int
    {
        $collector = new WpCronCollector();
        $count = 0;

        foreach ($collector->collect() as $event) {
            $scheduleId = $this->idGenerator->forWpCronEvent(
                $event['hook'],
                $event['args'],
                $event['schedule'],
                $event['timestamp'],
            );

            if ($event['schedule'] !== false && $event['interval'] > 0) {
                $expression = $this->scheduleFactory->fromWpCronInterval($event['interval']);
            } else {
                $expression = $this->scheduleFactory->fromTimestamp($event['timestamp']);
            }

            $payload = $this->payloadFactory->createForWpCronEvent(
                $event['hook'],
                $event['args'],
                $event['schedule'],
                $event['timestamp'],
            );

            try {
                $this->scheduler->createScheduleRaw(
                    $scheduleId,
                    $expression['expression'],
                    $payload,
                    $expression['type'] === 'at',
                );
                $count++;
            } catch (\Throwable $e) {
                $this->logger?->error('Failed to sync WP-Cron event "{hook}" to EventBridge: {error}', [
                    'hook' => $event['hook'],
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        return $count;
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

        // DB first — always persist
        CronArrayHelper::addEntry($timestamp, $hook, $args, $schedule, (int) ($event->interval ?? 0));

        // EventBridge — best-effort
        try {
            $this->scheduler->createScheduleRaw(
                $scheduleId,
                $expression['expression'],
                $payload,
                $expression['type'] === 'at',
            );
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to create EventBridge schedule for WP-Cron event "{hook}": {error}', [
                'hook' => $hook,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

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

        CronArrayHelper::addEntry(
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

        $schedule = CronArrayHelper::getScheduleName($timestamp, $hook, $args);
        $scheduleId = $this->idGenerator->forWpCronEvent($hook, $args, $schedule, $timestamp);

        // DB first — always persist
        CronArrayHelper::removeEntry($timestamp, $hook, $args);

        // EventBridge — best-effort
        try {
            $this->scheduler->unschedule($scheduleId);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to delete EventBridge schedule for WP-Cron event "{hook}": {error}', [
                'hook' => $hook,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

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

            try {
                $this->scheduler->unschedule($scheduleId);
            } catch (\Throwable $e) {
                $this->logger?->error('Failed to delete EventBridge schedule for WP-Cron hook "{hook}": {error}', [
                    'hook' => $hook,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }

            unset($crons[$timestamp][$hook][$key]);
            if (empty($crons[$timestamp][$hook])) {
                unset($crons[$timestamp][$hook]);
            }
            if (empty($crons[$timestamp])) {
                unset($crons[$timestamp]);
            }
            $count++;
        }

        _set_cron_array($crons);

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

                try {
                    $this->scheduler->unschedule($scheduleId);
                } catch (\Throwable $e) {
                    $this->logger?->error('Failed to delete EventBridge schedule for WP-Cron hook "{hook}": {error}', [
                        'hook' => $hook,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
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

}
