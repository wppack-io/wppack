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

namespace WpPack\Component\Stopwatch\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Stopwatch\StopwatchEvent;

final class StopwatchEventTest extends TestCase
{
    #[Test]
    public function constructorSetsAllReadonlyProperties(): void
    {
        $event = new StopwatchEvent(
            name: 'test_event',
            category: 'default',
            duration: 123.45,
            memory: 1048576,
            startTime: 1000.0,
            endTime: 1123.45,
        );

        self::assertSame('test_event', $event->name);
        self::assertSame('default', $event->category);
        self::assertSame(123.45, $event->duration);
        self::assertSame(1048576, $event->memory);
        self::assertSame(1000.0, $event->startTime);
        self::assertSame(1123.45, $event->endTime);
    }

    #[Test]
    public function propertiesAreAccessible(): void
    {
        $event = new StopwatchEvent(
            name: 'db_query',
            category: 'database',
            duration: 5.67,
            memory: 2097152,
            startTime: 500.0,
            endTime: 505.67,
        );

        self::assertIsString($event->name);
        self::assertIsString($event->category);
        self::assertIsFloat($event->duration);
        self::assertIsInt($event->memory);
        self::assertIsFloat($event->startTime);
        self::assertIsFloat($event->endTime);
    }
}
