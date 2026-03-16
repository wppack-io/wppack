<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Collector;

/**
 * Collects all WP-Cron events from the local cron array.
 *
 * Used for initial migration to EventBridge (e.g., on plugin activation
 * or via WP-CLI `wp wppack scheduler sync`).
 */
final class WpCronCollector
{
    /**
     * @return list<array{hook: string, args: array<mixed>, schedule: string|false, interval: int, timestamp: int}>
     */
    public function collect(): array
    {
        $crons = _get_cron_array();
        $events = [];

        foreach ($crons as $timestamp => $hooks) {
            foreach ($hooks as $hook => $entries) {
                foreach ($entries as $entry) {
                    $events[] = [
                        'hook' => $hook,
                        'args' => $entry['args'] ?? [],
                        'schedule' => $entry['schedule'] ?? false,
                        'interval' => $entry['interval'] ?? 0,
                        'timestamp' => (int) $timestamp,
                    ];
                }
            }
        }

        return $events;
    }
}
