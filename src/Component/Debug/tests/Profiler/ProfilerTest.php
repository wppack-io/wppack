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

namespace WPPack\Component\Debug\Tests\Profiler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\Profiler\Profiler;
use WPPack\Component\Stopwatch\Stopwatch;

final class ProfilerTest extends TestCase
{
    private Profiler $profiler;
    private Stopwatch $stopwatch;

    protected function setUp(): void
    {
        $this->stopwatch = new Stopwatch();
        $this->profiler = new Profiler($this->stopwatch);
    }

    #[Test]
    public function profileReturnsCallbackResult(): void
    {
        $result = $this->profiler->profile('test', fn() => 42);

        self::assertSame(42, $result);
    }

    #[Test]
    public function profileRegistersEventInStopwatch(): void
    {
        $this->profiler->profile('my_operation', fn() => 'done');

        $event = $this->stopwatch->getEvent('my_operation');

        self::assertSame('my_operation', $event->name);
        self::assertSame('default', $event->category);
        self::assertGreaterThanOrEqual(0.0, $event->duration);
    }

    #[Test]
    public function profileRecordsTimingEvenIfCallbackThrows(): void
    {
        try {
            $this->profiler->profile('failing_operation', function (): never {
                throw new \RuntimeException('Test error');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        // The stopwatch should have recorded the event via the finally block
        $event = $this->stopwatch->getEvent('failing_operation');
        self::assertSame('failing_operation', $event->name);
        self::assertGreaterThanOrEqual(0.0, $event->duration);
    }

    #[Test]
    public function getStopwatchReturnsInjectedStopwatch(): void
    {
        self::assertSame($this->stopwatch, $this->profiler->getStopwatch());
    }
}
