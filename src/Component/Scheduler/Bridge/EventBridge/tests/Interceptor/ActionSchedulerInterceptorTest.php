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

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests\Interceptor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WpPack\Component\Scheduler\Bridge\EventBridge\Interceptor\ActionSchedulerInterceptor;
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;

final class ActionSchedulerInterceptorTest extends TestCase
{
    private ActionSchedulerInterceptor $interceptor;
    private SpyScheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new SpyScheduler();
        $this->interceptor = new ActionSchedulerInterceptor(
            $this->scheduler,
            new EventBridgeScheduleFactory(),
            new SqsPayloadFactory(),
        );
    }

    protected function tearDown(): void
    {
        $this->interceptor->unregister();
    }

    #[Test]
    public function registerAddsAllHooks(): void
    {
        $this->interceptor->register();

        self::assertIsInt(has_action('action_scheduler_stored_action', [$this->interceptor, 'onStoredAction']));
        self::assertIsInt(has_action('action_scheduler_canceled_action', [$this->interceptor, 'onCanceledAction']));
        self::assertIsInt(has_filter('action_scheduler_queue_runner_concurrent_batches', [$this->interceptor, 'onConcurrentBatches']));
    }

    #[Test]
    public function unregisterRemovesAllHooks(): void
    {
        $this->interceptor->register();
        $this->interceptor->unregister();

        self::assertFalse(has_action('action_scheduler_stored_action', [$this->interceptor, 'onStoredAction']));
        self::assertFalse(has_action('action_scheduler_canceled_action', [$this->interceptor, 'onCanceledAction']));
        self::assertFalse(has_filter('action_scheduler_queue_runner_concurrent_batches', [$this->interceptor, 'onConcurrentBatches']));
    }

    #[Test]
    public function onConcurrentBatchesReturnsZero(): void
    {
        self::assertSame(0, $this->interceptor->onConcurrentBatches(5));
    }

    #[Test]
    public function onConcurrentBatchesReturnsZeroForNullInput(): void
    {
        self::assertSame(0, $this->interceptor->onConcurrentBatches(null));
    }

    #[Test]
    public function onStoredActionReturnsEarlyWhenActionSchedulerNotAvailable(): void
    {
        if (class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('This test requires Action Scheduler to NOT be available.');
        }

        // Should return early without calling scheduler
        $this->interceptor->onStoredAction(123);

        self::assertCount(0, $this->scheduler->createScheduleRawCalls);
    }

    #[Test]
    public function onCanceledActionReturnsEarlyWhenActionSchedulerNotAvailable(): void
    {
        if (class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('This test requires Action Scheduler to NOT be available.');
        }

        // Should return early without calling scheduler
        $this->interceptor->onCanceledAction(456);

        self::assertCount(0, $this->scheduler->unscheduleCalls);
    }

    #[Test]
    public function synchronizeReturnsZeroWhenActionSchedulerNotAvailable(): void
    {
        if (class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('This test requires Action Scheduler to NOT be available.');
        }

        $count = $this->interceptor->synchronize();

        self::assertSame(0, $count);
        self::assertCount(0, $this->scheduler->createScheduleRawCalls);
    }

    #[Test]
    public function onStoredActionCreatesScheduleForIntervalAction(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $this->interceptor->register();

        $actionId = as_schedule_recurring_action(time(), 3600, 'test_eb_interval_hook', ['arg1'], 'test-group');

        self::assertCount(1, $this->scheduler->createScheduleRawCalls);

        $call = $this->scheduler->createScheduleRawCalls[0];
        self::assertStringStartsWith('as_', $call['scheduleId']);
        self::assertStringContainsString('rate(', $call['expression']);
        self::assertFalse($call['autoDelete']);
    }

    #[Test]
    public function onStoredActionCreatesScheduleForSingleAction(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $this->interceptor->register();

        $timestamp = time() + 3600;
        $actionId = as_schedule_single_action($timestamp, 'test_eb_single_hook', ['arg1'], 'test-group');

        self::assertCount(1, $this->scheduler->createScheduleRawCalls);

        $call = $this->scheduler->createScheduleRawCalls[0];
        self::assertStringStartsWith('as_', $call['scheduleId']);
        self::assertStringContainsString('at(', $call['expression']);
        self::assertTrue($call['autoDelete']);
    }

    #[Test]
    public function onStoredActionCreatesScheduleForCronAction(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $this->interceptor->register();

        $actionId = as_schedule_cron_action(time(), '*/15 * * * *', 'test_eb_cron_hook', ['arg1'], 'test-group');

        self::assertCount(1, $this->scheduler->createScheduleRawCalls);

        $call = $this->scheduler->createScheduleRawCalls[0];
        self::assertStringStartsWith('as_', $call['scheduleId']);
        self::assertStringContainsString('cron(', $call['expression']);
        self::assertFalse($call['autoDelete']);
    }

    #[Test]
    public function onCanceledActionDeletesSchedule(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $this->interceptor->register();

        $actionId = as_schedule_recurring_action(time(), 3600, 'test_eb_cancel_hook', [], 'test-group');

        // Reset spy to focus on cancel
        $this->scheduler->createScheduleRawCalls = [];

        \ActionScheduler::store()->cancel_action($actionId);

        self::assertCount(1, $this->scheduler->unscheduleCalls);
        self::assertStringStartsWith('as_', $this->scheduler->unscheduleCalls[0]);
    }

    #[Test]
    public function onStoredActionLogsErrorWhenEventBridgeFails(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $this->scheduler->shouldThrowOnCreate = true;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $interceptor = new ActionSchedulerInterceptor(
            $this->scheduler,
            new EventBridgeScheduleFactory(),
            new SqsPayloadFactory(),
            $logger,
        );
        $interceptor->register();

        try {
            as_schedule_single_action(time() + 3600, 'test_eb_fail_store_hook', [], 'test-group');
            // Should not throw - error is caught and logged
        } finally {
            $interceptor->unregister();
        }
    }

    #[Test]
    public function onCanceledActionLogsErrorWhenEventBridgeFails(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        // First, create with a working scheduler
        $workingScheduler = new SpyScheduler();
        $setupInterceptor = new ActionSchedulerInterceptor(
            $workingScheduler,
            new EventBridgeScheduleFactory(),
            new SqsPayloadFactory(),
        );
        $setupInterceptor->register();

        $actionId = as_schedule_recurring_action(time(), 3600, 'test_eb_fail_cancel_hook', [], 'test-group');
        $setupInterceptor->unregister();

        // Now set up interceptor that will fail on unschedule
        $this->scheduler->shouldThrowOnUnschedule = true;
        $interceptor = new ActionSchedulerInterceptor(
            $this->scheduler,
            new EventBridgeScheduleFactory(),
            new SqsPayloadFactory(),
            $logger,
        );
        $interceptor->register();

        try {
            \ActionScheduler::store()->cancel_action($actionId);
            // Should not throw - error is caught and logged
        } finally {
            $interceptor->unregister();
        }
    }

    #[Test]
    public function onStoredActionCreatesScheduleForAsyncAction(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $this->interceptor->register();

        // Async action (no schedule) - uses NullSchedule internally
        $actionId = as_enqueue_async_action('test_eb_async_hook', ['arg1'], 'test-group');

        self::assertNotEmpty($this->scheduler->createScheduleRawCalls);

        $call = $this->scheduler->createScheduleRawCalls[0];
        self::assertStringStartsWith('as_', $call['scheduleId']);
        self::assertStringContainsString('at(', $call['expression']);
        self::assertTrue($call['autoDelete']);
    }

    #[Test]
    public function synchronizeSyncsAllPendingActionsToEventBridge(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        // Create some pending AS actions first (without interceptor registered)
        $actionId1 = as_schedule_single_action(time() + 3600, 'test_eb_sync_single_hook', ['arg1'], 'sync-group');
        $actionId2 = as_schedule_recurring_action(time(), 3600, 'test_eb_sync_recurring_hook', [], 'sync-group');

        // Now sync
        $count = $this->interceptor->synchronize();

        self::assertGreaterThanOrEqual(2, $count);
        self::assertGreaterThanOrEqual(2, \count($this->scheduler->createScheduleRawCalls));
    }

    #[Test]
    public function synchronizeLogsErrorWhenEventBridgeFails(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $this->scheduler->shouldThrowOnCreate = true;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('error');

        $interceptor = new ActionSchedulerInterceptor(
            $this->scheduler,
            new EventBridgeScheduleFactory(),
            new SqsPayloadFactory(),
            $logger,
        );

        // Create a pending AS action
        as_schedule_single_action(time() + 3600, 'test_eb_sync_fail_hook', [], 'sync-group');

        $count = $interceptor->synchronize();

        self::assertSame(0, $count);
    }

    #[Test]
    public function synchronizeHandlesCronScheduleType(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        // Create a cron AS action (without interceptor registered)
        as_schedule_cron_action(time(), '*/30 * * * *', 'test_eb_sync_cron_hook', [], 'sync-group');

        $count = $this->interceptor->synchronize();

        self::assertGreaterThanOrEqual(1, $count);

        // Find the cron call
        $found = false;
        foreach ($this->scheduler->createScheduleRawCalls as $call) {
            if (str_contains($call['expression'], 'cron(')) {
                $found = true;
                self::assertFalse($call['autoDelete']);
                break;
            }
        }

        self::assertTrue($found, 'Cron schedule should be synced with cron expression');
    }
}
