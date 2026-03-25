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

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\CronArrayHelper;

#[CoversClass(CronArrayHelper::class)]
final class CronArrayHelperTest extends TestCase
{
    protected function setUp(): void
    {
        // Start with a clean cron array
        _set_cron_array([]);
    }

    protected function tearDown(): void
    {
        // Clean up to avoid leaking into other tests
        _set_cron_array([]);
    }

    #[Test]
    public function addEntryPersistsRecurringEvent(): void
    {
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['arg1'], 'hourly', 3600);

        $crons = _get_cron_array();
        $key = md5(serialize(['arg1']));

        self::assertArrayHasKey(1700000000, $crons);
        self::assertArrayHasKey('my_hook', $crons[1700000000]);
        self::assertArrayHasKey($key, $crons[1700000000]['my_hook']);
        self::assertSame('hourly', $crons[1700000000]['my_hook'][$key]['schedule']);
        self::assertSame(['arg1'], $crons[1700000000]['my_hook'][$key]['args']);
        self::assertSame(3600, $crons[1700000000]['my_hook'][$key]['interval']);
    }

    #[Test]
    public function addEntrySingleEventHasNoInterval(): void
    {
        CronArrayHelper::addEntry(1700000000, 'single_hook', [], false, 0);

        $crons = _get_cron_array();
        $key = md5(serialize([]));

        self::assertSame(false, $crons[1700000000]['single_hook'][$key]['schedule']);
        self::assertArrayNotHasKey('interval', $crons[1700000000]['single_hook'][$key]);
    }

    #[Test]
    public function addEntryWithFalseScheduleAndPositiveIntervalDoesNotAddInterval(): void
    {
        // schedule=false means interval should not be added even if > 0
        CronArrayHelper::addEntry(1700000000, 'false_sched_hook', [], false, 3600);

        $crons = _get_cron_array();
        $key = md5(serialize([]));

        self::assertArrayNotHasKey('interval', $crons[1700000000]['false_sched_hook'][$key]);
    }

    #[Test]
    public function addEntryWithScheduleAndZeroIntervalDoesNotAddInterval(): void
    {
        CronArrayHelper::addEntry(1700000000, 'zero_interval_hook', [], 'hourly', 0);

        $crons = _get_cron_array();
        $key = md5(serialize([]));

        self::assertArrayNotHasKey('interval', $crons[1700000000]['zero_interval_hook'][$key]);
    }

    #[Test]
    public function addEntrySortsCronsByTimestamp(): void
    {
        CronArrayHelper::addEntry(1700003600, 'hook_b', [], 'hourly', 3600);
        CronArrayHelper::addEntry(1700000000, 'hook_a', [], 'hourly', 3600);

        $crons = _get_cron_array();
        $timestamps = array_keys($crons);

        self::assertSame(1700000000, $timestamps[0]);
        self::assertSame(1700003600, $timestamps[1]);
    }

    #[Test]
    public function addEntryMultipleHooksAtSameTimestamp(): void
    {
        CronArrayHelper::addEntry(1700000000, 'hook_a', [], 'hourly', 3600);
        CronArrayHelper::addEntry(1700000000, 'hook_b', ['x'], 'daily', 86400);

        $crons = _get_cron_array();

        self::assertArrayHasKey('hook_a', $crons[1700000000]);
        self::assertArrayHasKey('hook_b', $crons[1700000000]);
    }

    #[Test]
    public function addEntryMultipleArgSetsForSameHook(): void
    {
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['a'], 'hourly', 3600);
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['b'], 'hourly', 3600);

        $crons = _get_cron_array();

        $keyA = md5(serialize(['a']));
        $keyB = md5(serialize(['b']));

        self::assertArrayHasKey($keyA, $crons[1700000000]['my_hook']);
        self::assertArrayHasKey($keyB, $crons[1700000000]['my_hook']);
    }

    #[Test]
    public function removeEntryRemovesSpecificEntry(): void
    {
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['arg1'], 'hourly', 3600);
        CronArrayHelper::removeEntry(1700000000, 'my_hook', ['arg1']);

        $crons = _get_cron_array();
        $key = md5(serialize(['arg1']));

        self::assertFalse(isset($crons[1700000000]['my_hook'][$key]));
    }

    #[Test]
    public function removeEntryCleansUpEmptyHookKey(): void
    {
        CronArrayHelper::addEntry(1700000000, 'cleanup_hook', [], 'hourly', 3600);
        CronArrayHelper::removeEntry(1700000000, 'cleanup_hook', []);

        $crons = _get_cron_array();

        self::assertFalse(isset($crons[1700000000]['cleanup_hook']));
    }

    #[Test]
    public function removeEntryCleansUpEmptyTimestamp(): void
    {
        CronArrayHelper::addEntry(1700000000, 'only_hook', [], false, 0);
        CronArrayHelper::removeEntry(1700000000, 'only_hook', []);

        $crons = _get_cron_array();

        self::assertFalse(isset($crons[1700000000]));
    }

    #[Test]
    public function removeEntryDoesNotAffectOtherEntries(): void
    {
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['a'], 'hourly', 3600);
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['b'], 'hourly', 3600);

        CronArrayHelper::removeEntry(1700000000, 'my_hook', ['a']);

        $crons = _get_cron_array();
        $keyA = md5(serialize(['a']));
        $keyB = md5(serialize(['b']));

        self::assertFalse(isset($crons[1700000000]['my_hook'][$keyA]));
        self::assertTrue(isset($crons[1700000000]['my_hook'][$keyB]));
    }

    #[Test]
    public function removeEntryPreservesOtherHooksAtSameTimestamp(): void
    {
        CronArrayHelper::addEntry(1700000000, 'hook_a', [], false, 0);
        CronArrayHelper::addEntry(1700000000, 'hook_b', [], false, 0);

        CronArrayHelper::removeEntry(1700000000, 'hook_a', []);

        $crons = _get_cron_array();

        self::assertFalse(isset($crons[1700000000]['hook_a']));
        self::assertTrue(isset($crons[1700000000]['hook_b']));
    }

    #[Test]
    public function removeEntryNonExistentEntryDoesNotFail(): void
    {
        CronArrayHelper::removeEntry(1700000000, 'nonexistent_hook', ['arg']);

        $crons = _get_cron_array();

        // Should not throw, cron array remains empty
        self::assertEmpty($crons);
    }

    #[Test]
    public function getScheduleNameReturnsScheduleForExistingEntry(): void
    {
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['arg1'], 'hourly', 3600);

        $schedule = CronArrayHelper::getScheduleName(1700000000, 'my_hook', ['arg1']);

        self::assertSame('hourly', $schedule);
    }

    #[Test]
    public function getScheduleNameReturnsFalseForSingleEvent(): void
    {
        CronArrayHelper::addEntry(1700000000, 'single_hook', [], false, 0);

        $schedule = CronArrayHelper::getScheduleName(1700000000, 'single_hook', []);

        self::assertFalse($schedule);
    }

    #[Test]
    public function getScheduleNameReturnsFalseForNonExistentEntry(): void
    {
        $schedule = CronArrayHelper::getScheduleName(1700000000, 'nonexistent', []);

        self::assertFalse($schedule);
    }

    #[Test]
    public function getScheduleNameReturnsFalseForNonExistentTimestamp(): void
    {
        CronArrayHelper::addEntry(1700000000, 'my_hook', [], 'hourly', 3600);

        $schedule = CronArrayHelper::getScheduleName(9999999999, 'my_hook', []);

        self::assertFalse($schedule);
    }

    #[Test]
    public function getScheduleNameReturnsFalseForNonExistentArgs(): void
    {
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['a'], 'hourly', 3600);

        $schedule = CronArrayHelper::getScheduleName(1700000000, 'my_hook', ['b']);

        self::assertFalse($schedule);
    }

    #[Test]
    public function addEntryOverwritesExistingEntryWithSameKey(): void
    {
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['arg1'], 'hourly', 3600);
        CronArrayHelper::addEntry(1700000000, 'my_hook', ['arg1'], 'daily', 86400);

        $crons = _get_cron_array();
        $key = md5(serialize(['arg1']));

        self::assertSame('daily', $crons[1700000000]['my_hook'][$key]['schedule']);
        self::assertSame(86400, $crons[1700000000]['my_hook'][$key]['interval']);
    }
}
