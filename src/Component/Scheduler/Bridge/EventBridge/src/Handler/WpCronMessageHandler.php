<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
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

        $crons = _get_cron_array();
        $key = md5(serialize($message->args));

        // Remove old entry
        unset($crons[$message->timestamp][$message->hook][$key]);
        if (isset($crons[$message->timestamp][$message->hook]) && empty($crons[$message->timestamp][$message->hook])) {
            unset($crons[$message->timestamp][$message->hook]);
        }
        if (isset($crons[$message->timestamp]) && empty($crons[$message->timestamp])) {
            unset($crons[$message->timestamp]);
        }

        // Calculate next run time (skip past if behind)
        $nextTimestamp = $message->timestamp + $interval;
        $now = time();
        if ($nextTimestamp < $now) {
            $nextTimestamp = $now + $interval;
        }

        // Add new entry
        $crons[$nextTimestamp][$message->hook][$key] = [
            'schedule' => $message->schedule,
            'args' => $message->args,
            'interval' => $interval,
        ];

        uksort($crons, 'strcmp');
        _set_cron_array($crons);
    }

    /**
     * For single events: remove from wp_options.cron after execution.
     */
    private function removeSingleEvent(WpCronMessage $message): void
    {
        $crons = _get_cron_array();
        $key = md5(serialize($message->args));

        if (!isset($crons[$message->timestamp][$message->hook][$key])) {
            return;
        }

        unset($crons[$message->timestamp][$message->hook][$key]);
        if (empty($crons[$message->timestamp][$message->hook])) {
            unset($crons[$message->timestamp][$message->hook]);
        }
        if (empty($crons[$message->timestamp])) {
            unset($crons[$message->timestamp]);
        }

        _set_cron_array($crons);
    }
}
