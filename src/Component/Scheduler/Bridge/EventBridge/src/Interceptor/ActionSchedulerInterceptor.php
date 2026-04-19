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
use WPPack\Component\Scheduler\Bridge\EventBridge\Collector\ActionSchedulerCollector;
use WPPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WPPack\Component\Scheduler\Bridge\EventBridge\ScheduleIdGenerator;
use WPPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;
use WPPack\Component\Scheduler\Scheduler\SchedulerInterface;

/**
 * Intercepts Action Scheduler via post-store hooks and delegates to EventBridge Scheduler.
 *
 * AS has no pre_* filters like WP-Cron. Instead, we hook into action_scheduler_stored_action
 * (post-store) to create EventBridge schedules after AS saves actions to DB. The AS local
 * store is kept intact for admin UI compatibility.
 *
 * Store:  action_scheduler_stored_action → EventBridge schedule creation
 * Cancel: action_scheduler_canceled_action → EventBridge schedule deletion
 * Exec:   Queue runner disabled via action_scheduler_queue_runner_concurrent_batches → 0
 */
final class ActionSchedulerInterceptor
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
        add_action('action_scheduler_stored_action', [$this, 'onStoredAction'], 10, 1);
        add_action('action_scheduler_canceled_action', [$this, 'onCanceledAction'], 10, 1);
        add_filter('action_scheduler_queue_runner_concurrent_batches', [$this, 'onConcurrentBatches'], 10, 1);
    }

    public function unregister(): void
    {
        remove_action('action_scheduler_stored_action', [$this, 'onStoredAction'], 10);
        remove_action('action_scheduler_canceled_action', [$this, 'onCanceledAction'], 10);
        remove_filter('action_scheduler_queue_runner_concurrent_batches', [$this, 'onConcurrentBatches'], 10);
    }

    /**
     * Synchronize all pending Action Scheduler actions to EventBridge.
     *
     * Collects actions from the AS data store via ActionSchedulerCollector and creates
     * corresponding EventBridge schedules. Intended for initial migration
     * (e.g., plugin activation or WP-CLI `wp wppack scheduler sync`).
     *
     * @return int Number of actions synchronized
     */
    public function synchronize(): int
    {
        $collector = new ActionSchedulerCollector();
        $count = 0;

        // @codeCoverageIgnoreStart
        foreach ($collector->collect() as $action) {
            $scheduleId = $this->idGenerator->forActionScheduler(
                $action['hook'],
                $action['args'],
                $action['actionId'],
            );

            $payload = $this->payloadFactory->createForActionSchedulerAction(
                $action['hook'],
                $action['args'],
                $action['group'],
                $action['actionId'],
            );

            $expression = match ($action['scheduleType']) {
                'cron' => $this->scheduleFactory->fromCronExpression($action['cronExpression'])['expression'],
                'interval' => $this->scheduleFactory->fromWpCronInterval($action['interval'])['expression'],
                'single' => $this->scheduleFactory->fromTimestamp(
                    $action['scheduledDate']?->getTimestamp() ?? time(),
                )['expression'],
                default => $this->scheduleFactory->fromTimestamp(time())['expression'],
            };

            $autoDelete = \in_array($action['scheduleType'], ['single', 'async'], true);

            try {
                $this->scheduler->createScheduleRaw($scheduleId, $expression, $payload, $autoDelete);
                $count++;
            } catch (\Throwable $e) {
                $this->logger?->error('Failed to sync Action Scheduler action #{actionId} "{hook}" to EventBridge: {error}', [
                    'actionId' => $action['actionId'],
                    'hook' => $action['hook'],
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }
        // @codeCoverageIgnoreEnd

        return $count;
    }

    /**
     * Called after Action Scheduler stores an action in its data store.
     *
     * Fetches the action details, determines the schedule type, and creates
     * a corresponding EventBridge schedule.
     */
    public function onStoredAction(int $actionId): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            return;
        }

        // @codeCoverageIgnoreStart
        $action = \ActionScheduler::store()->fetch_action($actionId);

        if (!$action instanceof \ActionScheduler_Action) {
            return;
        }

        $hook = $action->get_hook();
        $args = $action->get_args();
        $group = $action->get_group();
        $schedule = $action->get_schedule();

        $scheduleId = $this->idGenerator->forActionScheduler($hook, $args, $actionId);
        $payload = $this->payloadFactory->createForActionSchedulerAction($hook, $args, $group, $actionId);

        [$expression, $autoDelete] = $this->resolveExpression($schedule);

        try {
            $this->scheduler->createScheduleRaw($scheduleId, $expression, $payload, $autoDelete);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to create EventBridge schedule for Action Scheduler action #{actionId} "{hook}": {error}', [
                'actionId' => $actionId,
                'hook' => $hook,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Called when Action Scheduler cancels an action.
     *
     * The action is still accessible from the store after cancellation,
     * so we can retrieve its details to reconstruct the schedule ID.
     */
    public function onCanceledAction(int $actionId): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            return;
        }

        // @codeCoverageIgnoreStart
        $action = \ActionScheduler::store()->fetch_action($actionId);

        if (!$action instanceof \ActionScheduler_Action) {
            return;
        }

        $scheduleId = $this->idGenerator->forActionScheduler(
            $action->get_hook(),
            $action->get_args(),
            $actionId,
        );

        try {
            $this->scheduler->unschedule($scheduleId);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to delete EventBridge schedule for Action Scheduler action #{actionId}: {error}', [
                'actionId' => $actionId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Disable AS queue runner by returning 0 concurrent batches.
     *
     * AS's get_allowed_concurrent_batches() returns 0, causing run() to return immediately.
     */
    public function onConcurrentBatches(mixed $batches): int
    {
        return 0;
    }

    /**
     * Determine EventBridge expression and auto-delete flag from AS schedule type.
     *
     * @codeCoverageIgnore — requires Action Scheduler plugin class definitions
     *
     * @return array{0: string, 1: bool} [expression, autoDelete]
     */
    private function resolveExpression(\ActionScheduler_Schedule $schedule): array
    {
        return match (true) {
            $schedule instanceof \ActionScheduler_CronSchedule => [
                $this->scheduleFactory->fromCronExpression($schedule->get_recurrence())['expression'],
                false,
            ],
            $schedule instanceof \ActionScheduler_IntervalSchedule => [
                $this->scheduleFactory->fromWpCronInterval((int) $schedule->get_recurrence())['expression'],
                false,
            ],
            $schedule instanceof \ActionScheduler_SimpleSchedule => [
                $this->scheduleFactory->fromTimestamp($schedule->get_date()->getTimestamp())['expression'],
                true,
            ],
            // NullSchedule = async action, fire immediately
            default => [
                $this->scheduleFactory->fromTimestamp(time())['expression'],
                true,
            ],
        };
    }
}
