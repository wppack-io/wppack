<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\Collector\WpCronCollector;

#[CoversClass(WpCronCollector::class)]
final class WpCronCollectorTest extends TestCase
{
    private WpCronCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new WpCronCollector();
        _set_cron_array([]);
    }

    protected function tearDown(): void
    {
        _set_cron_array([]);
    }

    #[Test]
    public function collectReturnsEmptyArrayWhenNoCronEvents(): void
    {
        self::assertSame([], $this->collector->collect());
    }

    #[Test]
    public function collectReturnsSingleRecurringEvent(): void
    {
        $timestamp = 1700000000;
        $key = md5(serialize(['arg1']));

        $crons = [
            $timestamp => [
                'my_hook' => [
                    $key => [
                        'schedule' => 'hourly',
                        'args' => ['arg1'],
                        'interval' => 3600,
                    ],
                ],
            ],
        ];
        _set_cron_array($crons);

        $events = $this->collector->collect();

        self::assertCount(1, $events);
        self::assertSame('my_hook', $events[0]['hook']);
        self::assertSame(['arg1'], $events[0]['args']);
        self::assertSame('hourly', $events[0]['schedule']);
        self::assertSame(3600, $events[0]['interval']);
        self::assertSame($timestamp, $events[0]['timestamp']);
    }

    #[Test]
    public function collectReturnsSingleEvent(): void
    {
        $timestamp = 1700000000;
        $key = md5(serialize([]));

        $crons = [
            $timestamp => [
                'single_hook' => [
                    $key => [
                        'schedule' => false,
                        'args' => [],
                    ],
                ],
            ],
        ];
        _set_cron_array($crons);

        $events = $this->collector->collect();

        self::assertCount(1, $events);
        self::assertSame('single_hook', $events[0]['hook']);
        self::assertSame([], $events[0]['args']);
        self::assertFalse($events[0]['schedule']);
        self::assertSame(0, $events[0]['interval']);
        self::assertSame($timestamp, $events[0]['timestamp']);
    }

    #[Test]
    public function collectReturnsMultipleEventsAtDifferentTimestamps(): void
    {
        $ts1 = 1700000000;
        $ts2 = 1700003600;
        $key = md5(serialize([]));

        $crons = [
            $ts1 => [
                'hook_a' => [
                    $key => [
                        'schedule' => 'hourly',
                        'args' => [],
                        'interval' => 3600,
                    ],
                ],
            ],
            $ts2 => [
                'hook_b' => [
                    $key => [
                        'schedule' => 'daily',
                        'args' => [],
                        'interval' => 86400,
                    ],
                ],
            ],
        ];
        _set_cron_array($crons);

        $events = $this->collector->collect();

        self::assertCount(2, $events);

        $hooks = array_column($events, 'hook');
        self::assertContains('hook_a', $hooks);
        self::assertContains('hook_b', $hooks);
    }

    #[Test]
    public function collectReturnsMultipleEntriesForSameHookDifferentArgs(): void
    {
        $timestamp = 1700000000;
        $keyA = md5(serialize(['a']));
        $keyB = md5(serialize(['b']));

        $crons = [
            $timestamp => [
                'my_hook' => [
                    $keyA => [
                        'schedule' => 'hourly',
                        'args' => ['a'],
                        'interval' => 3600,
                    ],
                    $keyB => [
                        'schedule' => 'hourly',
                        'args' => ['b'],
                        'interval' => 3600,
                    ],
                ],
            ],
        ];
        _set_cron_array($crons);

        $events = $this->collector->collect();

        self::assertCount(2, $events);
        self::assertSame('my_hook', $events[0]['hook']);
        self::assertSame('my_hook', $events[1]['hook']);

        $argsList = array_column($events, 'args');
        self::assertContains(['a'], $argsList);
        self::assertContains(['b'], $argsList);
    }

    #[Test]
    public function collectHandsMissingArgsKeyGracefully(): void
    {
        $timestamp = 1700000000;
        $key = md5(serialize([]));

        $crons = [
            $timestamp => [
                'no_args_hook' => [
                    $key => [
                        'schedule' => 'daily',
                    ],
                ],
            ],
        ];
        _set_cron_array($crons);

        $events = $this->collector->collect();

        self::assertCount(1, $events);
        self::assertSame([], $events[0]['args']);
        self::assertSame('daily', $events[0]['schedule']);
    }

    #[Test]
    public function collectHandsMissingScheduleKeyGracefully(): void
    {
        $timestamp = 1700000000;
        $key = md5(serialize(['x']));

        $crons = [
            $timestamp => [
                'no_schedule_hook' => [
                    $key => [
                        'args' => ['x'],
                    ],
                ],
            ],
        ];
        _set_cron_array($crons);

        $events = $this->collector->collect();

        self::assertCount(1, $events);
        self::assertSame(['x'], $events[0]['args']);
        self::assertFalse($events[0]['schedule']);
    }

    #[Test]
    public function collectHandsMissingIntervalKeyGracefully(): void
    {
        $timestamp = 1700000000;
        $key = md5(serialize([]));

        $crons = [
            $timestamp => [
                'no_interval_hook' => [
                    $key => [
                        'schedule' => 'custom',
                        'args' => [],
                    ],
                ],
            ],
        ];
        _set_cron_array($crons);

        $events = $this->collector->collect();

        self::assertCount(1, $events);
        self::assertSame(0, $events[0]['interval']);
    }

    #[Test]
    public function collectCastsTimestampToInt(): void
    {
        // WordPress may store timestamps as string keys in some edge cases
        $crons = [
            1700000000 => [
                'cast_hook' => [
                    md5(serialize([])) => [
                        'schedule' => false,
                        'args' => [],
                    ],
                ],
            ],
        ];
        _set_cron_array($crons);

        $events = $this->collector->collect();

        self::assertCount(1, $events);
        self::assertIsInt($events[0]['timestamp']);
        self::assertSame(1700000000, $events[0]['timestamp']);
    }

    #[Test]
    public function collectReturnsMultipleHooksAtSameTimestamp(): void
    {
        $timestamp = 1700000000;
        $key = md5(serialize([]));

        $crons = [
            $timestamp => [
                'hook_x' => [
                    $key => ['schedule' => 'hourly', 'args' => [], 'interval' => 3600],
                ],
                'hook_y' => [
                    $key => ['schedule' => 'daily', 'args' => [], 'interval' => 86400],
                ],
            ],
        ];
        _set_cron_array($crons);

        $events = $this->collector->collect();

        self::assertCount(2, $events);

        $hooks = array_column($events, 'hook');
        self::assertContains('hook_x', $hooks);
        self::assertContains('hook_y', $hooks);
    }
}
