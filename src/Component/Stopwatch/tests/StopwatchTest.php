<?php

declare(strict_types=1);

namespace WpPack\Component\Stopwatch\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Stopwatch\Stopwatch;
use WpPack\Component\Stopwatch\StopwatchEvent;

final class StopwatchTest extends TestCase
{
    private Stopwatch $stopwatch;

    protected function setUp(): void
    {
        $this->stopwatch = new Stopwatch();
    }

    #[Test]
    public function startAndStopReturnsEventWithPositiveDuration(): void
    {
        $this->stopwatch->start('test_event');
        $event = $this->stopwatch->stop('test_event');

        self::assertInstanceOf(StopwatchEvent::class, $event);
        self::assertSame('test_event', $event->name);
        self::assertGreaterThanOrEqual(0.0, $event->duration);
    }

    #[Test]
    public function stopWithoutStartThrowsLogicException(): void
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Event "nonexistent" is not started.');

        $this->stopwatch->stop('nonexistent');
    }

    #[Test]
    public function isStartedReturnsTrueAfterStart(): void
    {
        $this->stopwatch->start('test_event');

        self::assertTrue($this->stopwatch->isStarted('test_event'));
    }

    #[Test]
    public function isStartedReturnsFalseAfterStop(): void
    {
        $this->stopwatch->start('test_event');
        $this->stopwatch->stop('test_event');

        self::assertFalse($this->stopwatch->isStarted('test_event'));
    }

    #[Test]
    public function getEventReturnsEventAfterStop(): void
    {
        $this->stopwatch->start('test_event', 'my_category');
        $this->stopwatch->stop('test_event');

        $event = $this->stopwatch->getEvent('test_event');

        self::assertInstanceOf(StopwatchEvent::class, $event);
        self::assertSame('test_event', $event->name);
        self::assertSame('my_category', $event->category);
    }

    #[Test]
    public function getEventForNonExistentThrowsLogicException(): void
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Event "unknown" is not available. Did you forget to stop it?');

        $this->stopwatch->getEvent('unknown');
    }

    #[Test]
    public function getEventsReturnsAllStoppedEvents(): void
    {
        $this->stopwatch->start('event_a');
        $this->stopwatch->stop('event_a');

        $this->stopwatch->start('event_b');
        $this->stopwatch->stop('event_b');

        $events = $this->stopwatch->getEvents();

        self::assertCount(2, $events);
        self::assertArrayHasKey('event_a', $events);
        self::assertArrayHasKey('event_b', $events);
    }

    #[Test]
    public function resetClearsAllEventsAndStartedTimers(): void
    {
        $this->stopwatch->start('running');
        $this->stopwatch->start('completed');
        $this->stopwatch->stop('completed');

        $this->stopwatch->reset();

        self::assertFalse($this->stopwatch->isStarted('running'));
        self::assertEmpty($this->stopwatch->getEvents());
    }

    #[Test]
    public function multipleConcurrentTimersWorkIndependently(): void
    {
        $this->stopwatch->start('timer_a', 'category_a');
        $this->stopwatch->start('timer_b', 'category_b');

        self::assertTrue($this->stopwatch->isStarted('timer_a'));
        self::assertTrue($this->stopwatch->isStarted('timer_b'));

        $eventB = $this->stopwatch->stop('timer_b');
        self::assertFalse($this->stopwatch->isStarted('timer_b'));
        self::assertTrue($this->stopwatch->isStarted('timer_a'));

        $eventA = $this->stopwatch->stop('timer_a');
        self::assertFalse($this->stopwatch->isStarted('timer_a'));

        self::assertSame('timer_a', $eventA->name);
        self::assertSame('category_a', $eventA->category);
        self::assertSame('timer_b', $eventB->name);
        self::assertSame('category_b', $eventB->category);
    }

    #[Test]
    public function startWithDefaultCategorySetsDefaultInEvent(): void
    {
        $this->stopwatch->start('evt');
        $event = $this->stopwatch->stop('evt');

        self::assertSame('default', $event->category);
    }

    #[Test]
    public function isStartedReturnsFalseForNeverStartedEvent(): void
    {
        self::assertFalse($this->stopwatch->isStarted('never'));
    }

    #[Test]
    public function stopReturnsEventWithValidTimingAndMemory(): void
    {
        $this->stopwatch->start('timing');
        $event = $this->stopwatch->stop('timing');

        self::assertLessThan($event->endTime, $event->startTime);
        self::assertGreaterThan(0, $event->memory);
    }

    #[Test]
    public function stopReturnsSameInstanceAsGetEvent(): void
    {
        $this->stopwatch->start('x');
        $event = $this->stopwatch->stop('x');

        self::assertSame($event, $this->stopwatch->getEvent('x'));
    }

    #[Test]
    public function getEventsReturnsEmptyArrayInitially(): void
    {
        self::assertSame([], (new Stopwatch())->getEvents());
    }

    #[Test]
    public function startOverwritesPreviouslyStartedTimer(): void
    {
        $this->stopwatch->start('x', 'first');
        $this->stopwatch->start('x', 'second');
        $event = $this->stopwatch->stop('x');

        self::assertSame('second', $event->category);
        self::assertCount(1, $this->stopwatch->getEvents());
    }
}
