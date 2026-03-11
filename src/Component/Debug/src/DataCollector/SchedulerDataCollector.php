<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'scheduler', priority: 50)]
final class SchedulerDataCollector extends AbstractDataCollector
{
    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'scheduler';
    }

    public function getLabel(): string
    {
        return 'Scheduler';
    }

    public function collect(): void
    {
        if (!function_exists('_get_cron_array')) {
            $this->data = [
                'cron_events' => [],
                'cron_total' => 0,
                'cron_overdue' => 0,
                'action_scheduler_available' => false,
                'action_scheduler_version' => '',
                'as_pending' => 0,
                'as_failed' => 0,
                'as_complete' => 0,
                'as_recent_actions' => [],
                'cron_disabled' => false,
                'alternate_cron' => false,
            ];

            return;
        }

        /** @var array<int, array<string, array<string, array{schedule: string|false, args: list<mixed>}>>>|false $cronArray */
        $cronArray = _get_cron_array();
        $now = time();

        $cronEvents = [];
        $overdueCount = 0;

        if ($cronArray !== false) {
            foreach ($cronArray as $timestamp => $hooks) {
                foreach ($hooks as $hook => $events) {
                    foreach ($events as $key => $event) {
                        $schedule = $event['schedule'];
                        $isOverdue = (int) $timestamp < $now;

                        if ($isOverdue) {
                            $overdueCount++;
                        }

                        $diff = (int) $timestamp - $now;
                        $relativeTime = match (true) {
                            $diff < -3600 => sprintf('%d hours ago', abs(intdiv($diff, 3600))),
                            $diff < -60 => sprintf('%d minutes ago', abs(intdiv($diff, 60))),
                            $diff < 0 => 'overdue',
                            $diff < 60 => 'in less than a minute',
                            $diff < 3600 => sprintf('in %d minutes', intdiv($diff, 60)),
                            $diff < 86400 => sprintf('in %d hours', intdiv($diff, 3600)),
                            default => sprintf('in %d days', intdiv($diff, 86400)),
                        };

                        $callbackCount = 0;
                        global $wp_filter;
                        if (isset($wp_filter[$hook]) && is_object($wp_filter[$hook]) && isset($wp_filter[$hook]->callbacks)) {
                            foreach ($wp_filter[$hook]->callbacks as $priority => $funcs) {
                                $callbackCount += count($funcs);
                            }
                        }

                        $cronEvents[] = [
                            'hook' => $hook,
                            'schedule' => $schedule ?: 'single',
                            'next_run' => (int) $timestamp,
                            'next_run_relative' => $relativeTime,
                            'is_overdue' => $isOverdue,
                            'callbacks' => $callbackCount,
                        ];
                    }
                }
            }
        }

        // Sort by next_run
        usort($cronEvents, static fn(array $a, array $b): int => $a['next_run'] <=> $b['next_run']);

        // Action Scheduler detection
        $asAvailable = class_exists('ActionScheduler_Versions', false)
            || function_exists('as_get_scheduled_actions');
        $asVersion = '';
        $asPending = 0;
        $asFailed = 0;
        $asComplete = 0;
        $asRecentActions = [];

        if ($asAvailable && function_exists('as_get_scheduled_actions')) {
            $asVersion = defined('ActionScheduler_Versions::AS_VERSION')
                ? constant('ActionScheduler_Versions::AS_VERSION')
                : '';

            $pendingActions = as_get_scheduled_actions(['status' => 'pending', 'per_page' => 0], 'ARRAY_A');
            $asPending = is_array($pendingActions) ? count($pendingActions) : 0;

            $failedActions = as_get_scheduled_actions(['status' => 'failed', 'per_page' => 0], 'ARRAY_A');
            $asFailed = is_array($failedActions) ? count($failedActions) : 0;

            $completeActions = as_get_scheduled_actions(['status' => 'complete', 'per_page' => 0], 'ARRAY_A');
            $asComplete = is_array($completeActions) ? count($completeActions) : 0;
        }

        $this->data = [
            'cron_events' => $cronEvents,
            'cron_total' => count($cronEvents),
            'cron_overdue' => $overdueCount,
            'action_scheduler_available' => $asAvailable,
            'action_scheduler_version' => $asVersion,
            'as_pending' => $asPending,
            'as_failed' => $asFailed,
            'as_complete' => $asComplete,
            'as_recent_actions' => $asRecentActions,
            'cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'alternate_cron' => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON,
        ];
    }

    public function getBadgeValue(): string
    {
        $total = $this->data['cron_total'] ?? 0;

        return $total > 0 ? (string) $total : '';
    }

    public function getBadgeColor(): string
    {
        $overdue = $this->data['cron_overdue'] ?? 0;
        $total = $this->data['cron_total'] ?? 0;

        if ($overdue > 0) {
            return 'red';
        }

        if ($total >= 50) {
            return 'yellow';
        }

        return 'green';
    }

    public function reset(): void
    {
        parent::reset();
    }

    private function registerHooks(): void
    {
        // No hooks needed — data is collected at collect() time from _get_cron_array()
    }
}
