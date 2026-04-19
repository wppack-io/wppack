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

namespace WPPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DataCollector\SchedulerDataCollector;

final class SchedulerDataCollectorTest extends TestCase
{
    private SchedulerDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new SchedulerDataCollector();
    }

    #[Test]
    public function getNameReturnsScheduler(): void
    {
        self::assertSame('scheduler', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsScheduler(): void
    {
        self::assertSame('Scheduler', $this->collector->getLabel());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenNoEvents(): void
    {
        // Directly set data to simulate empty state
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 0]);

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsTotalWhenEventsExist(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 15]);

        self::assertSame('15', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsGreenWhenNoOverdueAndFewEvents(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 10, 'cron_overdue' => 0]);

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowWhenManyEvents(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 60, 'cron_overdue' => 0]);

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedWhenOverdue(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 5, 'cron_overdue' => 2]);

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();
        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function collectWithWordPressGathersCronData(): void
    {

        // Schedule a test cron event
        $hookName = 'test_debug_cron_' . uniqid();
        wp_schedule_single_event(time() + 3600, $hookName);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertGreaterThan(0, $data['cron_total']);
            self::assertNotEmpty($data['cron_events']);

            // Find our test event
            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    self::assertSame('single', $event['schedule']);
                    self::assertFalse($event['is_overdue']);
                    self::assertStringContainsString('in ', $event['next_run_relative']);
                    break;
                }
            }
            self::assertTrue($found, "Test cron event '$hookName' should be found");
        } finally {
            wp_unschedule_event(wp_next_scheduled($hookName), $hookName);
        }
    }

    #[Test]
    public function collectEventsSortedByNextRun(): void
    {

        $this->collector->collect();
        $data = $this->collector->getData();

        $nextRuns = array_column($data['cron_events'], 'next_run');
        $sorted = $nextRuns;
        sort($sorted);
        self::assertSame($sorted, $nextRuns, 'Cron events should be sorted by next_run');
    }

    #[Test]
    public function collectDetectsCronDisabledState(): void
    {

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertIsBool($data['cron_disabled']);
        self::assertIsBool($data['alternate_cron']);
        // DISABLE_WP_CRON is defined in tests/wp-config.php if applicable
        if (defined('DISABLE_WP_CRON')) {
            self::assertSame((bool) DISABLE_WP_CRON, $data['cron_disabled']);
        }
    }

    #[Test]
    public function collectCronEventStructureIsCorrect(): void
    {

        $this->collector->collect();
        $data = $this->collector->getData();

        foreach ($data['cron_events'] as $event) {
            self::assertArrayHasKey('hook', $event);
            self::assertArrayHasKey('schedule', $event);
            self::assertArrayHasKey('next_run', $event);
            self::assertArrayHasKey('next_run_relative', $event);
            self::assertArrayHasKey('is_overdue', $event);
            self::assertArrayHasKey('callbacks', $event);
            self::assertIsString($event['hook']);
            self::assertIsString($event['schedule']);
            self::assertIsInt($event['next_run']);
            self::assertIsBool($event['is_overdue']);
            self::assertIsInt($event['callbacks']);
            break; // Just check first event
        }
    }

    #[Test]
    public function collectWithCronArrayReturnsEvents(): void
    {

        $hook1 = 'test_cron_events_a_' . uniqid();
        $hook2 = 'test_cron_events_b_' . uniqid();

        wp_schedule_single_event(time() + 1800, $hook1);
        wp_schedule_event(time() + 3600, 'hourly', $hook2);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertNotEmpty($data['cron_events']);
            self::assertGreaterThanOrEqual(2, $data['cron_total']);

            // Verify both hooks appear in the events
            $hooks = array_column($data['cron_events'], 'hook');
            self::assertContains($hook1, $hooks);
            self::assertContains($hook2, $hooks);

            // Verify schedule types
            $eventsMap = [];
            foreach ($data['cron_events'] as $event) {
                $eventsMap[$event['hook']] = $event;
            }
            self::assertSame('single', $eventsMap[$hook1]['schedule']);
            self::assertSame('hourly', $eventsMap[$hook2]['schedule']);
        } finally {
            wp_unschedule_event(wp_next_scheduled($hook1), $hook1);
            wp_unschedule_event(wp_next_scheduled($hook2), $hook2);
        }
    }

    #[Test]
    public function collectDetectsOverdueEvents(): void
    {

        $hookName = 'test_overdue_cron_' . uniqid();

        // Schedule an event in the past (1 hour ago)
        wp_schedule_single_event(time() - 3600, $hookName);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertGreaterThan(0, $data['cron_overdue']);

            // Find our overdue event
            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    self::assertTrue($event['is_overdue']);
                    break;
                }
            }
            self::assertTrue($found, "Overdue cron event '$hookName' should be found");
        } finally {
            wp_unschedule_event(wp_next_scheduled($hookName), $hookName);
        }
    }

    #[Test]
    public function collectRelativeTimeFormatting(): void
    {

        $now = time();

        // Schedule events at various offsets
        $hookMinutes = 'test_rel_minutes_' . uniqid();
        $hookHours = 'test_rel_hours_' . uniqid();
        $hookDays = 'test_rel_days_' . uniqid();
        $hookPast = 'test_rel_past_' . uniqid();

        wp_schedule_single_event($now + 300, $hookMinutes);     // in 5 minutes
        wp_schedule_single_event($now + 7200, $hookHours);      // in 2 hours
        wp_schedule_single_event($now + 172800, $hookDays);     // in 2 days
        wp_schedule_single_event($now - 7200, $hookPast);       // 2 hours ago

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $eventsMap = [];
            foreach ($data['cron_events'] as $event) {
                $eventsMap[$event['hook']] = $event;
            }

            // "in X minutes"
            self::assertArrayHasKey($hookMinutes, $eventsMap);
            self::assertStringContainsString('in ', $eventsMap[$hookMinutes]['next_run_relative']);
            self::assertStringContainsString('minutes', $eventsMap[$hookMinutes]['next_run_relative']);

            // "in X hours"
            self::assertArrayHasKey($hookHours, $eventsMap);
            self::assertStringContainsString('in ', $eventsMap[$hookHours]['next_run_relative']);
            self::assertStringContainsString('hours', $eventsMap[$hookHours]['next_run_relative']);

            // "in X days"
            self::assertArrayHasKey($hookDays, $eventsMap);
            self::assertStringContainsString('in ', $eventsMap[$hookDays]['next_run_relative']);
            self::assertStringContainsString('days', $eventsMap[$hookDays]['next_run_relative']);

            // "X hours ago"
            self::assertArrayHasKey($hookPast, $eventsMap);
            self::assertStringContainsString('hours ago', $eventsMap[$hookPast]['next_run_relative']);
        } finally {
            foreach ([$hookMinutes, $hookHours, $hookDays, $hookPast] as $hook) {
                $next = wp_next_scheduled($hook);
                if ($next !== false) {
                    wp_unschedule_event($next, $hook);
                }
            }
        }
    }

    #[Test]
    public function collectCountsCallbacksPerHook(): void
    {

        $hookName = 'test_callback_count_cron_' . uniqid();
        $callback1 = static function (): void {};
        $callback2 = static function (): void {};

        wp_schedule_single_event(time() + 3600, $hookName);
        add_action($hookName, $callback1);
        add_action($hookName, $callback2);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    self::assertGreaterThanOrEqual(2, $event['callbacks']);
                    break;
                }
            }
            self::assertTrue($found, "Cron event '$hookName' should be found");
        } finally {
            remove_action($hookName, $callback1);
            remove_action($hookName, $callback2);
            wp_unschedule_event(wp_next_scheduled($hookName), $hookName);
        }
    }

    #[Test]
    public function collectDisabledCronDetection(): void
    {

        $this->collector->collect();
        $data = $this->collector->getData();

        // cron_disabled should reflect the DISABLE_WP_CRON constant
        $expected = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        self::assertSame($expected, $data['cron_disabled']);

        // alternate_cron should reflect the ALTERNATE_WP_CRON constant
        $expectedAlt = defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
        self::assertSame($expectedAlt, $data['alternate_cron']);
    }

    #[Test]
    public function getIndicatorColorReturnsRedWhenOverdueViaReflection(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 5, 'cron_overdue' => 3]);

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowWhenManyEventsViaReflection(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 50, 'cron_overdue' => 0]);

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsGreenWhenNormalViaReflection(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['cron_total' => 10, 'cron_overdue' => 0]);

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function collectWithOverdueCronEventSetsRelativeTimeStrings(): void
    {

        $hookName = 'test_overdue_relative_' . uniqid();

        // Schedule event 2 hours in the past
        wp_schedule_single_event(time() - 7200, $hookName);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    self::assertTrue($event['is_overdue']);
                    self::assertStringContainsString('hours ago', $event['next_run_relative']);
                    break;
                }
            }
            self::assertTrue($found, "Overdue cron event '$hookName' should be found");
        } finally {
            $next = wp_next_scheduled($hookName);
            if ($next !== false) {
                wp_unschedule_event($next, $hookName);
            }
        }
    }

    #[Test]
    public function collectWithFutureCronEventSetsRelativeTimeStrings(): void
    {

        $now = time();
        $hookLessThanMinute = 'test_future_sec_' . uniqid();
        $hookMinutes = 'test_future_min_' . uniqid();
        $hookHours = 'test_future_hr_' . uniqid();
        $hookDays = 'test_future_day_' . uniqid();

        wp_schedule_single_event($now + 30, $hookLessThanMinute);   // in less than a minute
        wp_schedule_single_event($now + 600, $hookMinutes);         // in 10 minutes
        wp_schedule_single_event($now + 7200, $hookHours);          // in 2 hours
        wp_schedule_single_event($now + 172800, $hookDays);         // in 2 days

        $hooks = [$hookLessThanMinute, $hookMinutes, $hookHours, $hookDays];

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $eventsMap = [];
            foreach ($data['cron_events'] as $event) {
                $eventsMap[$event['hook']] = $event;
            }

            // "in less than a minute"
            self::assertArrayHasKey($hookLessThanMinute, $eventsMap);
            self::assertSame('in less than a minute', $eventsMap[$hookLessThanMinute]['next_run_relative']);

            // "in X minutes"
            self::assertArrayHasKey($hookMinutes, $eventsMap);
            self::assertStringContainsString('in ', $eventsMap[$hookMinutes]['next_run_relative']);
            self::assertStringContainsString('minutes', $eventsMap[$hookMinutes]['next_run_relative']);

            // "in X hours"
            self::assertArrayHasKey($hookHours, $eventsMap);
            self::assertStringContainsString('in ', $eventsMap[$hookHours]['next_run_relative']);
            self::assertStringContainsString('hours', $eventsMap[$hookHours]['next_run_relative']);

            // "in X days"
            self::assertArrayHasKey($hookDays, $eventsMap);
            self::assertStringContainsString('in ', $eventsMap[$hookDays]['next_run_relative']);
            self::assertStringContainsString('days', $eventsMap[$hookDays]['next_run_relative']);
        } finally {
            foreach ($hooks as $hook) {
                $next = wp_next_scheduled($hook);
                if ($next !== false) {
                    wp_unschedule_event($next, $hook);
                }
            }
        }
    }

    #[Test]
    public function collectCountsCallbacksFromWpFilter(): void
    {

        $hookName = 'test_callback_wp_filter_' . uniqid();
        $callback1 = static function (): void {};
        $callback2 = static function (): void {};
        $callback3 = static function (): void {};

        wp_schedule_single_event(time() + 3600, $hookName);
        add_action($hookName, $callback1, 10);
        add_action($hookName, $callback2, 20);
        add_action($hookName, $callback3, 20);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    // 3 callbacks registered across priorities 10 and 20
                    self::assertSame(3, $event['callbacks']);
                    break;
                }
            }
            self::assertTrue($found, "Cron event '$hookName' should be found with callbacks counted");
        } finally {
            remove_action($hookName, $callback1, 10);
            remove_action($hookName, $callback2, 20);
            remove_action($hookName, $callback3, 20);
            $next = wp_next_scheduled($hookName);
            if ($next !== false) {
                wp_unschedule_event($next, $hookName);
            }
        }
    }

    #[Test]
    public function collectWithMinutesAgoCronEvent(): void
    {

        $hookName = 'test_minutes_ago_' . uniqid();

        // Schedule event 5 minutes in the past (less than 1 hour)
        wp_schedule_single_event(time() - 300, $hookName);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    self::assertTrue($event['is_overdue']);
                    self::assertStringContainsString('minutes ago', $event['next_run_relative']);
                    break;
                }
            }
            self::assertTrue($found, "Overdue cron event '$hookName' should be found");
        } finally {
            $next = wp_next_scheduled($hookName);
            if ($next !== false) {
                wp_unschedule_event($next, $hookName);
            }
        }
    }

    #[Test]
    public function collectWithJustOverdueCronEvent(): void
    {

        $hookName = 'test_just_overdue_' . uniqid();

        // Schedule event just a few seconds in the past (less than 60 seconds)
        wp_schedule_single_event(time() - 10, $hookName);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    self::assertTrue($event['is_overdue']);
                    self::assertSame('overdue', $event['next_run_relative']);
                    break;
                }
            }
            self::assertTrue($found, "Just overdue cron event '$hookName' should be found");
        } finally {
            $next = wp_next_scheduled($hookName);
            if ($next !== false) {
                wp_unschedule_event($next, $hookName);
            }
        }
    }

    #[Test]
    public function collectIteratesCronArrayAndBuildsEventDetails(): void
    {

        // Schedule multiple events with different schedules to thoroughly cover the loop
        $hookSingle = 'test_cron_detail_single_' . uniqid();
        $hookRecurring = 'test_cron_detail_recurring_' . uniqid();

        $now = time();
        wp_schedule_single_event($now + 120, $hookSingle);
        wp_schedule_event($now + 3600, 'hourly', $hookRecurring);

        // Add a callback to the recurring hook to test callback counting
        $callback = static function (): void {};
        add_action($hookRecurring, $callback, 10);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertGreaterThanOrEqual(2, $data['cron_total']);

            $singleEvent = null;
            $recurringEvent = null;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookSingle) {
                    $singleEvent = $event;
                }
                if ($event['hook'] === $hookRecurring) {
                    $recurringEvent = $event;
                }
            }

            // Verify single event details
            self::assertNotNull($singleEvent, 'Single event should be found');
            self::assertSame('single', $singleEvent['schedule']);
            self::assertFalse($singleEvent['is_overdue']);
            self::assertIsInt($singleEvent['next_run']);
            self::assertIsString($singleEvent['next_run_relative']);
            self::assertIsInt($singleEvent['callbacks']);

            // Verify recurring event details
            self::assertNotNull($recurringEvent, 'Recurring event should be found');
            self::assertSame('hourly', $recurringEvent['schedule']);
            self::assertGreaterThanOrEqual(1, $recurringEvent['callbacks']);
        } finally {
            remove_action($hookRecurring, $callback, 10);
            $next = wp_next_scheduled($hookSingle);
            if ($next !== false) {
                wp_unschedule_event($next, $hookSingle);
            }
            $next = wp_next_scheduled($hookRecurring);
            if ($next !== false) {
                wp_unschedule_event($next, $hookRecurring);
            }
        }
    }

    #[Test]
    public function collectWithCronEventNoCallbacksReturnsZeroCallbacks(): void
    {

        $hookName = 'test_no_callbacks_' . uniqid();
        wp_schedule_single_event(time() + 3600, $hookName);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    // No callbacks registered for this hook
                    self::assertSame(0, $event['callbacks']);
                    break;
                }
            }
            self::assertTrue($found, "Cron event '$hookName' should be found");
        } finally {
            $next = wp_next_scheduled($hookName);
            if ($next !== false) {
                wp_unschedule_event($next, $hookName);
            }
        }
    }

    #[Test]
    public function collectActionSchedulerDetectionWhenNotAvailable(): void
    {

        // When Action Scheduler is not installed, the AS fields should be defaults
        if (function_exists('as_get_scheduled_actions')) {
            self::markTestSkipped('Action Scheduler is available; testing the unavailable case.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        // Action Scheduler should not be detected if not installed
        // (class_exists check may or may not pass depending on environment)
        self::assertIsBool($data['action_scheduler_available']);
        self::assertSame('', $data['action_scheduler_version']);
        self::assertSame(0, $data['as_pending']);
        self::assertSame(0, $data['as_failed']);
        self::assertSame(0, $data['as_complete']);
        self::assertSame([], $data['as_recent_actions']);
    }

    #[Test]
    public function collectActionSchedulerFieldsWhenAvailable(): void
    {

        if (!function_exists('as_get_scheduled_actions')) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertTrue($data['action_scheduler_available']);
        self::assertIsString($data['action_scheduler_version']);
        self::assertIsInt($data['as_pending']);
        self::assertIsInt($data['as_failed']);
        self::assertIsInt($data['as_complete']);
        self::assertGreaterThanOrEqual(0, $data['as_pending']);
        self::assertGreaterThanOrEqual(0, $data['as_failed']);
        self::assertGreaterThanOrEqual(0, $data['as_complete']);
    }

    #[Test]
    public function collectCronEventsContainScheduleFieldForRecurring(): void
    {

        $hookName = 'test_schedule_field_' . uniqid();
        wp_schedule_event(time() + 3600, 'daily', $hookName);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    self::assertSame('daily', $event['schedule']);
                    break;
                }
            }
            self::assertTrue($found, "Cron event '$hookName' should be found with 'daily' schedule");
        } finally {
            $next = wp_next_scheduled($hookName);
            if ($next !== false) {
                wp_unschedule_event($next, $hookName);
            }
        }
    }

    #[Test]
    public function collectCronEventsRelativeTimeInLessThanMinute(): void
    {

        $hookName = 'test_less_than_min_' . uniqid();
        // Schedule 20 seconds in the future
        wp_schedule_single_event(time() + 20, $hookName);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    self::assertSame('in less than a minute', $event['next_run_relative']);
                    break;
                }
            }
            self::assertTrue($found, "Cron event '$hookName' should be found");
        } finally {
            $next = wp_next_scheduled($hookName);
            if ($next !== false) {
                wp_unschedule_event($next, $hookName);
            }
        }
    }

    #[Test]
    public function collectCronEventsCallbackCountAcrossMultiplePriorities(): void
    {

        $hookName = 'test_multi_priority_' . uniqid();
        $cb1 = static function (): void {};
        $cb2 = static function (): void {};
        $cb3 = static function (): void {};

        wp_schedule_single_event(time() + 3600, $hookName);
        add_action($hookName, $cb1, 5);
        add_action($hookName, $cb2, 10);
        add_action($hookName, $cb3, 15);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            $found = false;
            foreach ($data['cron_events'] as $event) {
                if ($event['hook'] === $hookName) {
                    $found = true;
                    // 3 callbacks across 3 different priorities
                    self::assertSame(3, $event['callbacks']);
                    break;
                }
            }
            self::assertTrue($found, "Cron event '$hookName' should be found");
        } finally {
            remove_action($hookName, $cb1, 5);
            remove_action($hookName, $cb2, 10);
            remove_action($hookName, $cb3, 15);
            $next = wp_next_scheduled($hookName);
            if ($next !== false) {
                wp_unschedule_event($next, $hookName);
            }
        }
    }
}
