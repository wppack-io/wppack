<?php

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
        if (!\function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

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
}

class TraitTestEvent extends Event
{
    public string $marker = '';
}
