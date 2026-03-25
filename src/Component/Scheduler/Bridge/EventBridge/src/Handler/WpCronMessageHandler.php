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

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Component\Scheduler\Bridge\EventBridge\CronArrayHelper;
use WpPack\Component\Scheduler\Message\WpCronMessage;

/**
 * Handles WP-Cron messages dispatched via EventBridge → SQS → Lambda.
 *
 * Executes the WordPress action (same as wp-cron.php) and updates the local
 * wp_options.cron state so admin tools remain accurate.
 */
#[AsMessageHandler]
final class WpCronMessageHandler
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(WpCronMessage $message): void
    {
        // Execute the WordPress action — identical to wp-cron.php behavior
        do_action_ref_array($message->hook, $message->args);

        // Update local state
        if ($message->schedule !== false) {
            $this->updateNextRunTime($message);
        } else {
            $this->removeSingleEvent($message);
        }
    }

    /**
     * For recurring events: update wp_options.cron with the next execution timestamp.
     */
    private function updateNextRunTime(WpCronMessage $message): void
    {
        $schedules = wp_get_schedules();
        $interval = $schedules[$message->schedule]['interval'] ?? 0;

        if ($interval <= 0) {
            $this->logger?->warning(
                'WP-Cron schedule "{schedule}" not found for hook "{hook}"; skipping next-run update.',
                ['schedule' => $message->schedule, 'hook' => $message->hook],
            );

            return;
        }

        // Remove old entry
        CronArrayHelper::removeEntry($message->timestamp, $message->hook, $message->args);

        // Calculate next run time (skip past if behind)
        $nextTimestamp = $message->timestamp + $interval;
        $now = time();
        if ($nextTimestamp < $now) {
            $nextTimestamp = $now + $interval;
        }

        // Add new entry
        CronArrayHelper::addEntry(
            $nextTimestamp,
            $message->hook,
            $message->args,
            $message->schedule,
            $interval,
        );
    }

    /**
     * For single events: remove from wp_options.cron after execution.
     */
    private function removeSingleEvent(WpCronMessage $message): void
    {
        CronArrayHelper::removeEntry($message->timestamp, $message->hook, $message->args);
    }
}
