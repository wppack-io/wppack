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
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(ActionSchedulerMessage $message): void
    {
        do_action_ref_array($message->hook, $message->args);

        if ($message->actionId > 0 && class_exists(\ActionScheduler::class)) {
            // @codeCoverageIgnoreStart
            try {
                \ActionScheduler::store()->mark_complete($message->actionId);
            } catch (\Exception $e) {
                $this->logger?->warning('Failed to mark Action Scheduler action {actionId} as complete: {error}', [
                    'actionId' => $message->actionId,
                    'error' => $e->getMessage(),
                    'hook' => $message->hook,
                ]);
            }
            // @codeCoverageIgnoreEnd
        }
    }
}
