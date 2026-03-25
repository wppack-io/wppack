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

namespace WpPack\Component\EventDispatcher\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\EventDispatcher\EventSubscriberInterface;
use WpPack\Component\EventDispatcher\WordPressEvent;

final class EventSubscriberTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    #[Test]
    public function addSubscriberWithStringMethod(): void
    {
        $subscriber = new StringMethodSubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        $event = $this->dispatcher->dispatch(new SubscriberTestEvent());

        self::assertTrue($subscriber->called);
    }

    #[Test]
    public function addSubscriberWithPriorityArray(): void
    {
        $subscriber = new PrioritySubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        $event = $this->dispatcher->dispatch(new SubscriberTestEvent());

        self::assertTrue($subscriber->called);
    }

    #[Test]
    public function addSubscriberWithAcceptedArgs(): void
    {
        $subscriber = new AcceptedArgsSubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        do_action('wppack_subscriber_hook', 'arg1', 'arg2', 'arg3');

        self::assertTrue($subscriber->called);
        self::assertSame('arg1', $subscriber->receivedArgs[0]);
    }

    #[Test]
    public function addSubscriberWithMultipleListeners(): void
    {
        $subscriber = new MultipleListenersSubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        $this->dispatcher->dispatch(new SubscriberTestEvent());

        self::assertTrue($subscriber->firstCalled);
        self::assertTrue($subscriber->secondCalled);
    }

    #[Test]
    public function removeSubscriber(): void
    {
        $subscriber = new StringMethodSubscriber();
        $this->dispatcher->addSubscriber($subscriber);
        $this->dispatcher->removeSubscriber($subscriber);

        $this->dispatcher->dispatch(new SubscriberTestEvent());

        self::assertFalse($subscriber->called);
    }

    #[Test]
    public function removeSubscriberWithPriority(): void
    {
        $subscriber = new PrioritySubscriber();
        $this->dispatcher->addSubscriber($subscriber);
        $this->dispatcher->removeSubscriber($subscriber);

        $this->dispatcher->dispatch(new SubscriberTestEvent());

        self::assertFalse($subscriber->called);
    }

    #[Test]
    public function removeSubscriberWithMultipleListeners(): void
    {
        $subscriber = new MultipleListenersSubscriber();
        $this->dispatcher->addSubscriber($subscriber);
        $this->dispatcher->removeSubscriber($subscriber);

        $this->dispatcher->dispatch(new SubscriberTestEvent());

        self::assertFalse($subscriber->firstCalled);
        self::assertFalse($subscriber->secondCalled);
    }

    #[Test]
    public function subscriberWithEventClassParam(): void
    {
        $subscriber = new EventClassSubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        do_action('wppack_event_class_hook', 99, 'test_value');

        self::assertInstanceOf(SubscriberTestWordPressEvent::class, $subscriber->receivedEvent);
        self::assertSame(99, $subscriber->receivedEvent->getId());
    }
}

// Test fixtures

class SubscriberTestEvent extends Event {}

class SubscriberTestWordPressEvent extends WordPressEvent
{
    protected array $argMap = [
        'id' => 0,
        'value' => 1,
    ];
}

class StringMethodSubscriber implements EventSubscriberInterface
{
    public bool $called = false;

    public static function getSubscribedEvents(): array
    {
        return [
            SubscriberTestEvent::class => 'onEvent',
        ];
    }

    public function onEvent(SubscriberTestEvent $event): void
    {
        $this->called = true;
    }
}

class PrioritySubscriber implements EventSubscriberInterface
{
    public bool $called = false;

    public static function getSubscribedEvents(): array
    {
        return [
            SubscriberTestEvent::class => ['onEvent', 20],
        ];
    }

    public function onEvent(SubscriberTestEvent $event): void
    {
        $this->called = true;
    }
}

class AcceptedArgsSubscriber implements EventSubscriberInterface
{
    public bool $called = false;

    /** @var list<mixed> */
    public array $receivedArgs = [];

    public static function getSubscribedEvents(): array
    {
        return [
            'wppack_subscriber_hook' => ['onHook', 10, 3],
        ];
    }

    public function onHook(WordPressEvent $event): void
    {
        $this->called = true;
        $this->receivedArgs = $event->args;
    }
}

class MultipleListenersSubscriber implements EventSubscriberInterface
{
    public bool $firstCalled = false;
    public bool $secondCalled = false;

    public static function getSubscribedEvents(): array
    {
        return [
            SubscriberTestEvent::class => [
                ['onFirst', 10],
                ['onSecond', 20],
            ],
        ];
    }

    public function onFirst(SubscriberTestEvent $event): void
    {
        $this->firstCalled = true;
    }

    public function onSecond(SubscriberTestEvent $event): void
    {
        $this->secondCalled = true;
    }
}

class EventClassSubscriber implements EventSubscriberInterface
{
    public ?SubscriberTestWordPressEvent $receivedEvent = null;

    public static function getSubscribedEvents(): array
    {
        return [
            'wppack_event_class_hook' => ['onHook', 10, 2, SubscriberTestWordPressEvent::class],
        ];
    }

    public function onHook(SubscriberTestWordPressEvent $event): void
    {
        $this->receivedEvent = $event;
    }
}
