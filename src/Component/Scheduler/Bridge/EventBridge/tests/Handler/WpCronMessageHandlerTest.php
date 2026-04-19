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

namespace WPPack\Component\Scheduler\Bridge\EventBridge\Tests\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WPPack\Component\Scheduler\Bridge\EventBridge\Handler\WpCronMessageHandler;
use WPPack\Component\Scheduler\Message\WpCronMessage;

final class WpCronMessageHandlerTest extends TestCase
{
    private WpCronMessageHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new WpCronMessageHandler();
    }

    #[Test]
    public function invokeCallsDoActionRefArray(): void
    {
        $called = false;
        $receivedArgs = [];

        $callback = static function () use (&$called, &$receivedArgs): void {
            $called = true;
            $receivedArgs = \func_get_args();
        };

        add_action('test_cron_handler_hook', $callback, 10, 2);

        try {
            $message = new WpCronMessage(
                hook: 'test_cron_handler_hook',
                args: ['value1', 'value2'],
                schedule: false,
                timestamp: time() + 3600,
            );

            ($this->handler)($message);

            self::assertTrue($called, 'do_action_ref_array should have called the hook callback');
            self::assertSame(['value1', 'value2'], $receivedArgs);
        } finally {
            remove_action('test_cron_handler_hook', $callback, 10);
        }
    }

    #[Test]
    public function invokeSingleEventRemovesFromCronArray(): void
    {
        $timestamp = time() + 3600;
        $key = md5(serialize([]));

        $crons = _get_cron_array();
        $crons[$timestamp]['single_handler_hook'][$key] = [
            'schedule' => false,
            'args' => [],
        ];
        _set_cron_array($crons);

        $message = new WpCronMessage(
            hook: 'single_handler_hook',
            args: [],
            schedule: false,
            timestamp: $timestamp,
        );

        ($this->handler)($message);

        $crons = _get_cron_array();
        self::assertFalse(isset($crons[$timestamp]['single_handler_hook'][$key]));
    }

    #[Test]
    public function invokeRecurringEventUpdatesNextRunTime(): void
    {
        $timestamp = time();
        $key = md5(serialize([]));

        $crons = _get_cron_array();
        $crons[$timestamp]['recurring_handler_hook'][$key] = [
            'schedule' => 'hourly',
            'args' => [],
            'interval' => 3600,
        ];
        _set_cron_array($crons);

        $message = new WpCronMessage(
            hook: 'recurring_handler_hook',
            args: [],
            schedule: 'hourly',
            timestamp: $timestamp,
        );

        ($this->handler)($message);

        $crons = _get_cron_array();

        // Old entry should be removed
        self::assertFalse(isset($crons[$timestamp]['recurring_handler_hook'][$key]));

        // New entry should exist at a future timestamp
        $found = false;
        foreach ($crons as $ts => $hooks) {
            if (isset($hooks['recurring_handler_hook'][$key])) {
                self::assertGreaterThan($timestamp, $ts);
                self::assertSame('hourly', $hooks['recurring_handler_hook'][$key]['schedule']);
                self::assertSame(3600, $hooks['recurring_handler_hook'][$key]['interval']);
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Recurring event should have a new entry at a future timestamp');
    }

    #[Test]
    public function handlerHasAsMessageHandlerAttribute(): void
    {
        $ref = new \ReflectionClass(WpCronMessageHandler::class);
        $attributes = $ref->getAttributes(\WPPack\Component\Messenger\Attribute\AsMessageHandler::class);

        self::assertCount(1, $attributes);
    }

    #[Test]
    public function invokeRecurringEventLogsWarningWhenScheduleNotFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('not found for hook'),
                self::callback(static function (array $context): bool {
                    return $context['schedule'] === 'nonexistent_schedule'
                        && $context['hook'] === 'unknown_schedule_hook';
                }),
            );

        $handler = new WpCronMessageHandler($logger);

        $timestamp = time();
        $message = new WpCronMessage(
            hook: 'unknown_schedule_hook',
            args: [],
            schedule: 'nonexistent_schedule',
            timestamp: $timestamp,
        );

        ($handler)($message);

        // Verify cron array was NOT modified (no new entry, no removal)
        $crons = _get_cron_array();
        $key = md5(serialize([]));
        self::assertFalse(isset($crons[$timestamp]['unknown_schedule_hook'][$key]));
    }

    #[Test]
    public function invokeRecurringEventSkipsUpdateWithoutLogger(): void
    {
        // Handler without logger - should still work, just no warning logged
        $handler = new WpCronMessageHandler();

        $timestamp = time();
        $message = new WpCronMessage(
            hook: 'no_logger_unknown_schedule_hook',
            args: [],
            schedule: 'nonexistent_schedule_no_logger',
            timestamp: $timestamp,
        );

        // Should not throw
        ($handler)($message);

        self::assertTrue(true);
    }

    #[Test]
    public function invokeRecurringEventHandlesPastNextTimestamp(): void
    {
        // Use a timestamp far in the past so nextTimestamp < now
        $pastTimestamp = time() - 86400; // 24 hours ago
        $key = md5(serialize([]));

        $crons = _get_cron_array();
        $crons[$pastTimestamp]['past_recurring_hook'][$key] = [
            'schedule' => 'hourly',
            'args' => [],
            'interval' => 3600,
        ];
        _set_cron_array($crons);

        $message = new WpCronMessage(
            hook: 'past_recurring_hook',
            args: [],
            schedule: 'hourly',
            timestamp: $pastTimestamp,
        );

        ($this->handler)($message);

        $crons = _get_cron_array();

        // Old entry should be removed
        self::assertFalse(isset($crons[$pastTimestamp]['past_recurring_hook'][$key]));

        // New entry should exist at a future timestamp (now + interval)
        $now = time();
        $found = false;
        foreach ($crons as $ts => $hooks) {
            if (isset($hooks['past_recurring_hook'][$key])) {
                self::assertGreaterThanOrEqual($now, $ts);
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Overdue recurring event should be rescheduled to now + interval');
    }
}
