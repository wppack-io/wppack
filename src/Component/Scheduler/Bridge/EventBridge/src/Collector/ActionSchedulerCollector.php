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

namespace WPPack\Component\Scheduler\Bridge\EventBridge\Collector;

/**
 * Collects all pending Action Scheduler actions from the AS data store.
 *
 * Used for initial migration to EventBridge (e.g., on plugin activation
 * or via WP-CLI `wp wppack scheduler sync`).
 */
final class ActionSchedulerCollector
{
    /**
     * @return list<array{hook: string, args: array<mixed>, group: string, actionId: int, scheduleType: string, interval: int, cronExpression: string, scheduledDate: \DateTimeImmutable|null}>
     */
    public function collect(): array
    {
        if (!class_exists(\ActionScheduler::class)) {
            return [];
        }

        // @codeCoverageIgnoreStart
        $store = \ActionScheduler::store();
        // per_page: -1 fetches all pending actions at once.
        // This is intended for migration/sync operations (e.g., plugin activation, WP-CLI).
        // For large-scale sites, consider batching with pagination instead.
        $actionIds = $store->query_actions([
            'status' => \ActionScheduler_Store::STATUS_PENDING,
            'per_page' => -1,
        ]);

        $actions = [];

        foreach ($actionIds as $actionId) {
            $action = $store->fetch_action($actionId);

            if (!$action instanceof \ActionScheduler_Action) {
                continue;
            }

            $schedule = $action->get_schedule();
            $scheduledDate = null;

            if (method_exists($schedule, 'get_date') && $schedule->get_date() !== null) {
                $scheduledDate = \DateTimeImmutable::createFromMutable(
                    \DateTime::createFromFormat('U', (string) $schedule->get_date()->getTimestamp()),
                );
            }

            $actions[] = [
                'hook' => $action->get_hook(),
                'args' => $action->get_args(),
                'group' => $action->get_group(),
                'actionId' => (int) $actionId,
                'scheduleType' => $this->resolveScheduleType($schedule),
                'interval' => $this->resolveInterval($schedule),
                'cronExpression' => $this->resolveCronExpression($schedule),
                'scheduledDate' => $scheduledDate,
            ];
        }

        return $actions;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @codeCoverageIgnore — requires Action Scheduler plugin class definitions
     */
    private function resolveScheduleType(\ActionScheduler_Schedule $schedule): string
    {
        return match (true) {
            $schedule instanceof \ActionScheduler_CronSchedule => 'cron',
            $schedule instanceof \ActionScheduler_IntervalSchedule => 'interval',
            $schedule instanceof \ActionScheduler_SimpleSchedule => 'single',
            default => 'async',
        };
    }

    /**
     * @codeCoverageIgnore — requires Action Scheduler plugin class definitions
     */
    private function resolveInterval(\ActionScheduler_Schedule $schedule): int
    {
        if ($schedule instanceof \ActionScheduler_IntervalSchedule) {
            return (int) $schedule->get_recurrence();
        }

        return 0;
    }

    /**
     * @codeCoverageIgnore — requires Action Scheduler plugin class definitions
     */
    private function resolveCronExpression(\ActionScheduler_Schedule $schedule): string
    {
        if ($schedule instanceof \ActionScheduler_CronSchedule) {
            return (string) $schedule->get_recurrence();
        }

        return '';
    }
}
