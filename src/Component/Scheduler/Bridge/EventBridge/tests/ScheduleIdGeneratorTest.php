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

namespace WPPack\Component\Scheduler\Bridge\EventBridge\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scheduler\Bridge\EventBridge\ScheduleIdGenerator;

final class ScheduleIdGeneratorTest extends TestCase
{
    private ScheduleIdGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ScheduleIdGenerator();
    }

    #[Test]
    public function recurringEventIdIsDeterministic(): void
    {
        $id1 = $this->generator->forWpCronEvent('my_hook', ['arg1'], 'hourly', 1700000000);
        $id2 = $this->generator->forWpCronEvent('my_hook', ['arg1'], 'hourly', 1700000000);

        self::assertSame($id1, $id2);
    }

    #[Test]
    public function recurringEventIdExcludesTimestamp(): void
    {
        $id1 = $this->generator->forWpCronEvent('my_hook', ['arg1'], 'hourly', 1700000000);
        $id2 = $this->generator->forWpCronEvent('my_hook', ['arg1'], 'hourly', 1700003600);

        // Same hook+args+schedule → same ID (no timestamp in recurring)
        self::assertSame($id1, $id2);
    }

    #[Test]
    public function singleEventIdIncludesTimestamp(): void
    {
        $id1 = $this->generator->forWpCronEvent('my_hook', ['arg1'], false, 1700000000);
        $id2 = $this->generator->forWpCronEvent('my_hook', ['arg1'], false, 1700003600);

        self::assertNotSame($id1, $id2);
    }

    #[Test]
    public function recurringEventIdStartsWithPrefix(): void
    {
        $id = $this->generator->forWpCronEvent('my_hook', [], 'daily', 1700000000);

        self::assertStringStartsWith('wpcron_', $id);
    }

    #[Test]
    public function singleEventIdStartsWithPrefix(): void
    {
        $id = $this->generator->forWpCronEvent('my_hook', [], false, 1700000000);

        self::assertStringStartsWith('wpcron_', $id);
    }

    #[Test]
    public function recurringEventIdContainsHookAndArgsHash(): void
    {
        $id = $this->generator->forWpCronEvent('my_hook', ['arg1'], 'hourly', 1700000000);

        $argsHash = substr(md5(json_encode(['arg1'])), 0, 8);

        self::assertSame('wpcron_my_hook_' . $argsHash, $id);
    }

    #[Test]
    public function singleEventIdContainsHookArgsHashAndTimestamp(): void
    {
        $id = $this->generator->forWpCronEvent('my_hook', ['arg1'], false, 1700000000);

        $argsHash = substr(md5(json_encode(['arg1'])), 0, 8);

        self::assertSame('wpcron_my_hook_' . $argsHash . '_1700000000', $id);
    }

    #[Test]
    public function idNeverExceeds64Characters(): void
    {
        // Very long hook name
        $longHook = str_repeat('a', 100);
        $id = $this->generator->forWpCronEvent($longHook, ['arg1', 'arg2'], 'daily', 1700000000);

        self::assertLessThanOrEqual(64, \strlen($id));
        self::assertStringStartsWith('wpcron_', $id);
    }

    #[Test]
    public function longSingleEventIdFallsBackToHash(): void
    {
        $longHook = str_repeat('x', 60);
        $id = $this->generator->forWpCronEvent($longHook, [], false, 1700000000);

        self::assertLessThanOrEqual(64, \strlen($id));
        self::assertStringStartsWith('wpcron_', $id);
        // Fallback format: wpcron_ + 32-char md5 = 39 chars
        self::assertSame(39, \strlen($id));
    }

    #[Test]
    public function differentArgsProduceDifferentIds(): void
    {
        $id1 = $this->generator->forWpCronEvent('hook', ['a'], 'hourly', 1700000000);
        $id2 = $this->generator->forWpCronEvent('hook', ['b'], 'hourly', 1700000000);

        self::assertNotSame($id1, $id2);
    }

    #[Test]
    public function emptyArgsProduceDeterministicId(): void
    {
        $id1 = $this->generator->forWpCronEvent('hook', [], 'daily', 1700000000);
        $id2 = $this->generator->forWpCronEvent('hook', [], 'daily', 1700000000);

        self::assertSame($id1, $id2);
    }

    #[Test]
    public function actionSchedulerIdIsDeterministic(): void
    {
        $id1 = $this->generator->forActionScheduler('my_hook', ['arg1'], 42);
        $id2 = $this->generator->forActionScheduler('my_hook', ['arg1'], 42);

        self::assertSame($id1, $id2);
    }

    #[Test]
    public function actionSchedulerIdStartsWithPrefix(): void
    {
        $id = $this->generator->forActionScheduler('my_hook', [], 1);

        self::assertStringStartsWith('as_', $id);
    }

    #[Test]
    public function actionSchedulerIdContainsHashAndActionId(): void
    {
        $id = $this->generator->forActionScheduler('my_hook', ['arg1'], 42);

        $hash = substr(md5('my_hook' . json_encode(['arg1'])), 0, 16);

        self::assertSame('as_' . $hash . '_42', $id);
    }

    #[Test]
    public function actionSchedulerIdNeverExceeds64Characters(): void
    {
        $longHook = str_repeat('b', 100);
        $id = $this->generator->forActionScheduler($longHook, ['arg1'], 999999999);

        self::assertLessThanOrEqual(64, \strlen($id));
        self::assertStringStartsWith('as_', $id);
    }

    #[Test]
    public function differentActionIdsProduceDifferentIds(): void
    {
        $id1 = $this->generator->forActionScheduler('hook', ['a'], 1);
        $id2 = $this->generator->forActionScheduler('hook', ['a'], 2);

        self::assertNotSame($id1, $id2);
    }
}
