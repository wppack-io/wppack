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

namespace WpPack\Component\EventDispatcher\Tests\Test;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\EventDispatcher\Test\EventDispatcherTestTrait;

final class EventDispatcherTestTraitTest extends TestCase
{
    use EventDispatcherTestTrait;

    protected function setUp(): void
    {
        $this->resetDispatchedEvents();
        $this->eventDispatcher = null;
    }

    #[Test]
    public function getEventDispatcherReturnsInstance(): void
    {
        $dispatcher = $this->getEventDispatcher();

        self::assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    #[Test]
    public function getEventDispatcherReturnsSameInstance(): void
    {
        $first = $this->getEventDispatcher();
        $second = $this->getEventDispatcher();

        self::assertSame($first, $second);
    }

    #[Test]
    public function dispatchReturnsEvent(): void
    {
        $event = new TraitTestEvent();
        $result = $this->dispatch($event);

        self::assertSame($event, $result);
    }

    #[Test]
    public function assertEventDispatchedPasses(): void
    {
        $this->dispatch(new TraitTestEvent());

        $this->assertEventDispatched(TraitTestEvent::class);
    }

    #[Test]
    public function assertEventNotDispatchedPasses(): void
    {
        $this->assertEventNotDispatched(TraitTestEvent::class);
    }

    #[Test]
    public function getLastDispatchedEventReturnsEvent(): void
    {
        $event = new TraitTestEvent();
        $this->dispatch($event);

        $last = $this->getLastDispatchedEvent(TraitTestEvent::class);

        self::assertSame($event, $last);
    }

    #[Test]
    public function getLastDispatchedEventReturnsNull(): void
    {
        $result = $this->getLastDispatchedEvent(TraitTestEvent::class);

        self::assertNull($result);
    }

    #[Test]
    public function getLastDispatchedEventReturnsLatest(): void
    {
        $first = new TraitTestEvent();
        $first->marker = 'first';
        $this->dispatch($first);

        $second = new TraitTestEvent();
        $second->marker = 'second';
        $this->dispatch($second);

        $last = $this->getLastDispatchedEvent(TraitTestEvent::class);

        self::assertSame('second', $last->marker);
    }

    #[Test]
    public function resetClearsDispatchedEvents(): void
    {
        $this->dispatch(new TraitTestEvent());
        $this->resetDispatchedEvents();

        $this->assertEventNotDispatched(TraitTestEvent::class);
    }

    #[Test]
    public function assertEventDispatchedFailsWhenNotDispatched(): void
    {
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessage('was not dispatched');

        $this->assertEventDispatched(TraitTestEvent::class);
    }

    #[Test]
    public function assertEventNotDispatchedFailsWhenDispatched(): void
    {
        $this->dispatch(new TraitTestEvent());

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessage('was dispatched but should not have been');

        $this->assertEventNotDispatched(TraitTestEvent::class);
    }

    #[Test]
    public function getLastDispatchedEventWithMultipleDifferentEvents(): void
    {
        $event1 = new TraitTestEvent();
        $event1->marker = 'first';
        $this->dispatch($event1);

        $otherEvent = new class extends Event {};
        $this->dispatch($otherEvent);

        $event2 = new TraitTestEvent();
        $event2->marker = 'last';
        $this->dispatch($event2);

        $last = $this->getLastDispatchedEvent(TraitTestEvent::class);

        self::assertNotNull($last);
        self::assertSame('last', $last->marker);
    }
}

class TraitTestEvent extends Event
{
    public string $marker = '';
}
