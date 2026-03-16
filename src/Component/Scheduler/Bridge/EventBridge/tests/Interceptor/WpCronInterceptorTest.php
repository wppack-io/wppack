<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests\Interceptor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduler;
use WpPack\Component\Scheduler\Bridge\EventBridge\Interceptor\WpCronInterceptor;
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;
use WpPack\Component\Scheduler\Message\ScheduledMessage;
use WpPack\Component\Scheduler\Scheduler\SchedulerInterface;

final class WpCronInterceptorTest extends TestCase
{
    private WpCronInterceptor $interceptor;
    private SpyScheduler $scheduler;

    protected function setUp(): void
    {
        if (!\function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->scheduler = new SpyScheduler();
        $this->interceptor = new WpCronInterceptor(
            $this->scheduler,
            new EventBridgeScheduleFactory(),
            new SqsPayloadFactory(),
        );
    }

    protected function tearDown(): void
    {
        if (\function_exists('remove_filter')) {
            $this->interceptor->unregister();
        }
    }

    #[Test]
    public function registerAddsAllFilters(): void
    {
        $this->interceptor->register();

        self::assertIsInt(has_filter('pre_schedule_event', [$this->interceptor, 'onPreScheduleEvent']));
        self::assertIsInt(has_filter('pre_reschedule_event', [$this->interceptor, 'onPreRescheduleEvent']));
        self::assertIsInt(has_filter('pre_unschedule_event', [$this->interceptor, 'onPreUnscheduleEvent']));
        self::assertIsInt(has_filter('pre_clear_scheduled_hook', [$this->interceptor, 'onPreClearScheduledHook']));
        self::assertIsInt(has_filter('pre_unschedule_hook', [$this->interceptor, 'onPreUnscheduleHook']));
        self::assertIsInt(has_filter('pre_get_ready_cron_jobs', [$this->interceptor, 'onPreGetReadyCronJobs']));
    }

    #[Test]
    public function unregisterRemovesAllFilters(): void
    {
        $this->interceptor->register();
        $this->interceptor->unregister();

        self::assertFalse(has_filter('pre_schedule_event', [$this->interceptor, 'onPreScheduleEvent']));
        self::assertFalse(has_filter('pre_reschedule_event', [$this->interceptor, 'onPreRescheduleEvent']));
        self::assertFalse(has_filter('pre_unschedule_event', [$this->interceptor, 'onPreUnscheduleEvent']));
        self::assertFalse(has_filter('pre_clear_scheduled_hook', [$this->interceptor, 'onPreClearScheduledHook']));
        self::assertFalse(has_filter('pre_unschedule_hook', [$this->interceptor, 'onPreUnscheduleHook']));
        self::assertFalse(has_filter('pre_get_ready_cron_jobs', [$this->interceptor, 'onPreGetReadyCronJobs']));
    }

    #[Test]
    public function onPreScheduleEventCreatesScheduleAndReturnTrue(): void
    {
        $event = (object) [
            'hook' => 'my_hook',
            'args' => [],
            'timestamp' => time() + 3600,
            'schedule' => 'hourly',
            'interval' => 3600,
        ];

        $result = $this->interceptor->onPreScheduleEvent(null, $event);

        self::assertTrue($result);
        self::assertCount(1, $this->scheduler->createScheduleRawCalls);

        $call = $this->scheduler->createScheduleRawCalls[0];
        self::assertStringStartsWith('wpcron_', $call['scheduleId']);
        self::assertStringContainsString('rate(', $call['expression']);
        self::assertFalse($call['autoDelete']);
    }

    #[Test]
    public function onPreScheduleEventSingleEventUsesAtExpression(): void
    {
        $timestamp = time() + 3600;

        $event = (object) [
            'hook' => 'single_hook',
            'args' => [],
            'timestamp' => $timestamp,
            'schedule' => false,
        ];

        $result = $this->interceptor->onPreScheduleEvent(null, $event);

        self::assertTrue($result);
        self::assertCount(1, $this->scheduler->createScheduleRawCalls);

        $call = $this->scheduler->createScheduleRawCalls[0];
        self::assertStringStartsWith('wpcron_', $call['scheduleId']);
        self::assertStringContainsString('at(', $call['expression']);
        self::assertTrue($call['autoDelete']);
    }

    #[Test]
    public function onPreScheduleEventSkipsWhenPreIsNotNull(): void
    {
        $event = (object) ['hook' => 'hook', 'args' => [], 'timestamp' => time(), 'schedule' => false];
        $result = $this->interceptor->onPreScheduleEvent(true, $event);

        self::assertTrue($result);
        self::assertCount(0, $this->scheduler->createScheduleRawCalls);
    }

    #[Test]
    public function onPreRescheduleEventReturnsTrue(): void
    {
        $event = (object) [
            'hook' => 'my_hook',
            'args' => [],
            'timestamp' => time() + 3600,
            'schedule' => 'hourly',
            'interval' => 3600,
        ];

        $result = $this->interceptor->onPreRescheduleEvent(null, $event);

        self::assertTrue($result);
        // No EventBridge calls — rate() auto-repeats
        self::assertCount(0, $this->scheduler->createScheduleRawCalls);
        self::assertCount(0, $this->scheduler->unscheduleCalls);
    }

    #[Test]
    public function onPreUnscheduleEventDeletesScheduleAndReturnsTrue(): void
    {
        $timestamp = time() + 3600;
        $crons = _get_cron_array();
        $key = md5(serialize([]));
        $crons[$timestamp]['my_hook'][$key] = [
            'schedule' => 'hourly',
            'args' => [],
            'interval' => 3600,
        ];
        _set_cron_array($crons);

        $result = $this->interceptor->onPreUnscheduleEvent(null, $timestamp, 'my_hook', []);

        self::assertTrue($result);
        self::assertCount(1, $this->scheduler->unscheduleCalls);
        self::assertStringStartsWith('wpcron_', $this->scheduler->unscheduleCalls[0]);

        $crons = _get_cron_array();
        self::assertFalse(isset($crons[$timestamp]['my_hook'][$key]));
    }

    #[Test]
    public function onPreGetReadyCronJobsReturnsEmptyArray(): void
    {
        $result = $this->interceptor->onPreGetReadyCronJobs(null);

        self::assertSame([], $result);
    }

    #[Test]
    public function onPreClearScheduledHookRemovesAllMatchingEvents(): void
    {
        $ts1 = time() + 3600;
        $ts2 = time() + 7200;
        $args = ['arg1'];
        $key = md5(serialize($args));

        $crons = _get_cron_array();
        $crons[$ts1]['my_hook'][$key] = ['schedule' => 'hourly', 'args' => $args, 'interval' => 3600];
        $crons[$ts2]['my_hook'][$key] = ['schedule' => 'hourly', 'args' => $args, 'interval' => 3600];
        _set_cron_array($crons);

        $result = $this->interceptor->onPreClearScheduledHook(null, 'my_hook', $args);

        self::assertSame(2, $result);
        self::assertCount(2, $this->scheduler->unscheduleCalls);
    }

    #[Test]
    public function onPreUnscheduleHookRemovesAllEventsForHook(): void
    {
        $ts1 = time() + 3600;
        $ts2 = time() + 7200;
        $key1 = md5(serialize(['a']));
        $key2 = md5(serialize(['b']));

        $crons = _get_cron_array();
        $crons[$ts1]['target_hook'][$key1] = ['schedule' => 'hourly', 'args' => ['a'], 'interval' => 3600];
        $crons[$ts2]['target_hook'][$key2] = ['schedule' => 'daily', 'args' => ['b'], 'interval' => 86400];
        _set_cron_array($crons);

        $result = $this->interceptor->onPreUnscheduleHook(null, 'target_hook');

        self::assertSame(2, $result);
        self::assertCount(2, $this->scheduler->unscheduleCalls);
    }
}

/**
 * Spy implementation of EventBridgeScheduler for testing WpCronInterceptor.
 *
 * Records all method calls for assertion without requiring the real SchedulerClient.
 *
 * @internal
 */
final class SpyScheduler extends EventBridgeScheduler
{
    /** @var list<array{scheduleId: string, expression: string, payload: string, autoDelete: bool}> */
    public array $createScheduleRawCalls = [];

    /** @var list<string> */
    public array $unscheduleCalls = [];

    public function __construct()
    {
        // Skip parent constructor — we don't need real AWS client
    }

    public function schedule(string $scheduleId, ScheduledMessage $message): void
    {
        // Not used by interceptor
    }

    public function unschedule(string $scheduleId): void
    {
        $this->unscheduleCalls[] = $scheduleId;
    }

    public function has(string $scheduleId): bool
    {
        return false;
    }

    public function getNextRunDate(string $scheduleId): ?\DateTimeImmutable
    {
        return null;
    }

    public function createScheduleRaw(
        string $scheduleId,
        string $expression,
        string $payload,
        bool $autoDelete = false,
    ): void {
        $this->createScheduleRawCalls[] = [
            'scheduleId' => $scheduleId,
            'expression' => $expression,
            'payload' => $payload,
            'autoDelete' => $autoDelete,
        ];
    }
}
