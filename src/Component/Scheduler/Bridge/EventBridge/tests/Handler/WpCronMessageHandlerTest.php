<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\Handler\WpCronMessageHandler;
use WpPack\Component\Scheduler\Message\WpCronMessage;

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

        add_action('test_cron_hook', static function () use (&$called, &$receivedArgs): void {
            $called = true;
            $receivedArgs = \func_get_args();
        }, 10, 2);

        try {
            $message = new WpCronMessage(
                hook: 'test_cron_hook',
                args: ['value1', 'value2'],
                schedule: false,
                timestamp: time() + 3600,
            );

            ($this->handler)($message);

            self::assertTrue($called, 'do_action_ref_array should have called the hook callback');
            self::assertSame(['value1', 'value2'], $receivedArgs);
        } finally {
            remove_action('test_cron_hook', static function (): void {}, 10);
        }
    }

    #[Test]
    public function invokeSingleEventRemovesFromCronArray(): void
    {
        $timestamp = time() + 3600;
        $key = md5(serialize([]));

        $crons = _get_cron_array();
        $crons[$timestamp]['single_hook'][$key] = [
            'schedule' => false,
            'args' => [],
        ];
        _set_cron_array($crons);

        $message = new WpCronMessage(
            hook: 'single_hook',
            args: [],
            schedule: false,
            timestamp: $timestamp,
        );

        ($this->handler)($message);

        $crons = _get_cron_array();
        self::assertFalse(isset($crons[$timestamp]['single_hook'][$key]));
    }

    #[Test]
    public function invokeRecurringEventUpdatesNextRunTime(): void
    {
        $timestamp = time();
        $key = md5(serialize([]));

        $crons = _get_cron_array();
        $crons[$timestamp]['recurring_hook'][$key] = [
            'schedule' => 'hourly',
            'args' => [],
            'interval' => 3600,
        ];
        _set_cron_array($crons);

        $message = new WpCronMessage(
            hook: 'recurring_hook',
            args: [],
            schedule: 'hourly',
            timestamp: $timestamp,
        );

        ($this->handler)($message);

        $crons = _get_cron_array();

        // Old entry should be removed
        self::assertFalse(isset($crons[$timestamp]['recurring_hook'][$key]));

        // New entry should exist at a future timestamp
        $found = false;
        foreach ($crons as $ts => $hooks) {
            if (isset($hooks['recurring_hook'][$key])) {
                self::assertGreaterThan($timestamp, $ts);
                self::assertSame('hourly', $hooks['recurring_hook'][$key]['schedule']);
                self::assertSame(3600, $hooks['recurring_hook'][$key]['interval']);
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
        $attributes = $ref->getAttributes(\WpPack\Component\Messenger\Attribute\AsMessageHandler::class);

        self::assertCount(1, $attributes);
    }
}
