<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge;

/**
 * Shared helper for WordPress cron array (wp_options.cron) manipulation.
 *
 * Used by WpCronInterceptor (write path) and WpCronMessageHandler (execution path)
 * to avoid duplicating cron array operations.
 */
final class CronArrayHelper
{
    /**
     * Add an entry to the cron array and persist.
     *
     * @param array<mixed> $args
     */
    public static function addEntry(int $timestamp, string $hook, array $args, string|false $schedule, int $interval): void
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
        ksort($crons, \SORT_NUMERIC);

        _set_cron_array($crons);
    }

    /**
     * Remove a specific entry from the cron array and persist.
     *
     * Cleans up empty parent keys (hook / timestamp) automatically.
     *
     * @param array<mixed> $args
     */
    public static function removeEntry(int $timestamp, string $hook, array $args): void
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
     * Look up the schedule name for a specific event in the cron array.
     *
     * @param array<mixed> $args
     */
    public static function getScheduleName(int $timestamp, string $hook, array $args): string|false
    {
        $crons = _get_cron_array();
        $key = md5(serialize($args));

        return $crons[$timestamp][$hook][$key]['schedule'] ?? false;
    }
}
