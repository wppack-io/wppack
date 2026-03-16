<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Handler;

use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Component\Scheduler\Message\ActionSchedulerMessage;

/**
 * Handles Action Scheduler messages dispatched via EventBridge → SQS → Lambda.
 *
 * Executes the WordPress action and updates the AS store status to complete.
 * For recurring/cron actions, EventBridge rate()/cron() handles repetition —
 * AS does not auto-reschedule on mark_complete() (only via queue runner).
 */
#[AsMessageHandler]
final class ActionSchedulerMessageHandler
{
    public function __invoke(ActionSchedulerMessage $message): void
    {
        do_action_ref_array($message->hook, $message->args);

        if ($message->actionId > 0 && class_exists(\ActionScheduler::class)) {
            try {
                \ActionScheduler::store()->mark_complete($message->actionId);
            } catch (\Exception) {
                // Action already completed or deleted — safe to ignore
            }
        }
    }
}
