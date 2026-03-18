<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests\Interceptor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WpPack\Component\Scheduler\Bridge\EventBridge\Interceptor\ActionSchedulerInterceptor;
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;

final class ActionSchedulerInterceptorTest extends TestCase
{
    private ActionSchedulerInterceptor $interceptor;
    private SpyScheduler $scheduler;

    protected function setUp(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $this->scheduler = new SpyScheduler();
        $this->interceptor = new ActionSchedulerInterceptor(
            $this->scheduler,
            new EventBridgeScheduleFactory(),
            new SqsPayloadFactory(),
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->interceptor)) {
            $this->interceptor->unregister();
        }
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
    public function onStoredActionCreatesScheduleForIntervalAction(): void
    {
        $actionId = as_schedule_recurring_action(time(), 3600, 'test_interval_hook', ['arg1'], 'test-group');

        self::assertCount(1, $this->scheduler->createScheduleRawCalls);

        $call = $this->scheduler->createScheduleRawCalls[0];
        self::assertStringStartsWith('as_', $call['scheduleId']);
        self::assertStringContainsString('rate(', $call['expression']);
        self::assertFalse($call['autoDelete']);
    }

    #[Test]
    public function onStoredActionCreatesScheduleForSingleAction(): void
    {
        $timestamp = time() + 3600;
        $actionId = as_schedule_single_action($timestamp, 'test_single_hook', ['arg1'], 'test-group');

        self::assertCount(1, $this->scheduler->createScheduleRawCalls);

        $call = $this->scheduler->createScheduleRawCalls[0];
        self::assertStringStartsWith('as_', $call['scheduleId']);
        self::assertStringContainsString('at(', $call['expression']);
        self::assertTrue($call['autoDelete']);
    }

    #[Test]
    public function onStoredActionCreatesScheduleForCronAction(): void
    {
        $actionId = as_schedule_cron_action(time(), '*/15 * * * *', 'test_cron_hook', ['arg1'], 'test-group');

        self::assertCount(1, $this->scheduler->createScheduleRawCalls);

        $call = $this->scheduler->createScheduleRawCalls[0];
        self::assertStringStartsWith('as_', $call['scheduleId']);
        self::assertStringContainsString('cron(', $call['expression']);
        self::assertFalse($call['autoDelete']);
    }

    #[Test]
    public function onCanceledActionDeletesSchedule(): void
    {
        $this->interceptor->register();

        $actionId = as_schedule_recurring_action(time(), 3600, 'test_cancel_hook', [], 'test-group');

        // Reset spy to focus on cancel
        $this->scheduler->createScheduleRawCalls = [];

        \ActionScheduler::store()->cancel_action($actionId);

        self::assertCount(1, $this->scheduler->unscheduleCalls);
        self::assertStringStartsWith('as_', $this->scheduler->unscheduleCalls[0]);
    }
}
