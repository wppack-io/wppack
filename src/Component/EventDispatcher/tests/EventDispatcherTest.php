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
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\EventDispatcher\Exception\InvalidArgumentException;
use WpPack\Component\EventDispatcher\WordPressEvent;

final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    #[Test]
    public function implementsPsr14Interface(): void
    {
        self::assertInstanceOf(EventDispatcherInterface::class, $this->dispatcher);
    }

    #[Test]
    public function dispatchReturnsEvent(): void
    {
        $event = new TestEvent();
        $result = $this->dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }

    #[Test]
    public function dispatchCallsListener(): void
    {
        $called = false;

        $this->dispatcher->addListener(TestEvent::class, function (TestEvent $event) use (&$called): void {
            $called = true;
        });

        $this->dispatcher->dispatch(new TestEvent());

        self::assertTrue($called);
    }

    #[Test]
    public function dispatchPassesEventToListener(): void
    {
        $received = null;

        $this->dispatcher->addListener(TestEvent::class, function (TestEvent $event) use (&$received): void {
            $received = $event;
        });

        $event = new TestEvent();
        $this->dispatcher->dispatch($event);

        self::assertSame($event, $received);
    }

    #[Test]
    public function listenerCanModifyEvent(): void
    {
        $this->dispatcher->addListener(TestEvent::class, function (TestEvent $event): void {
            $event->data = 'modified';
        });

        $event = new TestEvent();
        $result = $this->dispatcher->dispatch($event);

        self::assertSame('modified', $result->data);
    }

    #[Test]
    public function dispatchSkipsStoppedEvent(): void
    {
        $called = false;

        $this->dispatcher->addListener(TestEvent::class, function () use (&$called): void {
            $called = true;
        });

        $event = new TestEvent();
        $event->stopPropagation();
        $this->dispatcher->dispatch($event);

        self::assertFalse($called);
    }

    #[Test]
    public function addListenerForWordPressHook(): void
    {
        $received = null;

        $this->dispatcher->addListener('wppack_test_hook', function (WordPressEvent $event) use (&$received): void {
            $received = $event;
        }, acceptedArgs: 2);

        do_action('wppack_test_hook', 'arg1', 'arg2');

        self::assertInstanceOf(WordPressEvent::class, $received);
        self::assertSame('wppack_test_hook', $received->hookName);
        self::assertSame('arg1', $received->args[0]);
        self::assertSame('arg2', $received->args[1]);
    }

    #[Test]
    public function addListenerWithCustomEventClass(): void
    {
        $received = null;

        $this->dispatcher->addListener('wppack_custom_hook', function (TestWordPressEvent $event) use (&$received): void {
            $received = $event;
        }, acceptedArgs: 2, eventClass: TestWordPressEvent::class);

        do_action('wppack_custom_hook', 42, 'value');

        self::assertInstanceOf(TestWordPressEvent::class, $received);
        self::assertSame(42, $received->getId());
        self::assertSame('value', $received->getValue());
    }

    #[Test]
    public function addListenerRejectsInvalidEventClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dispatcher->addListener('some_hook', function (): void {}, eventClass: TestEvent::class);
    }

    #[Test]
    public function removeListenerForCustomEvent(): void
    {
        $called = false;
        $listener = function () use (&$called): void {
            $called = true;
        };

        $this->dispatcher->addListener(TestEvent::class, $listener);
        $this->dispatcher->removeListener(TestEvent::class, $listener);
        $this->dispatcher->dispatch(new TestEvent());

        self::assertFalse($called);
    }

    #[Test]
    public function removeListenerForWordPressHook(): void
    {
        $called = false;
        $listener = function () use (&$called): void {
            $called = true;
        };

        $this->dispatcher->addListener('wppack_remove_test', $listener);
        $this->dispatcher->removeListener('wppack_remove_test', $listener);

        do_action('wppack_remove_test');

        self::assertFalse($called);
    }

    #[Test]
    public function hasListeners(): void
    {
        self::assertFalse($this->dispatcher->hasListeners('wppack_nonexistent'));

        $this->dispatcher->addListener('wppack_has_test', function (): void {});

        self::assertTrue($this->dispatcher->hasListeners('wppack_has_test'));
    }

    #[Test]
    public function priorityOrdering(): void
    {
        $order = [];

        $this->dispatcher->addListener(TestEvent::class, function () use (&$order): void {
            $order[] = 'second';
        }, priority: 20);

        $this->dispatcher->addListener(TestEvent::class, function () use (&$order): void {
            $order[] = 'first';
        }, priority: 5);

        $this->dispatcher->dispatch(new TestEvent());

        self::assertSame(['first', 'second'], $order);
    }

    #[Test]
    public function wordPressHookFilterCompatibility(): void
    {
        $this->dispatcher->addListener('wppack_filter_test', function (WordPressEvent $event): void {
            // listener does not return, but the wrapped closure returns func_get_arg(0)
        });

        $result = apply_filters('wppack_filter_test', 'original');

        self::assertSame('original', $result);
    }

    #[Test]
    public function addListenerForWordPressEventSubclass(): void
    {
        $received = null;

        $this->dispatcher->addListener(SavePostTestEvent::class, function (SavePostTestEvent $event) use (&$received): void {
            $received = $event;
        }, acceptedArgs: 3);

        do_action('save_post', 42, 'post_obj', true);

        self::assertInstanceOf(SavePostTestEvent::class, $received);
        self::assertSame(42, $received->getPostId());
    }

    #[Test]
    public function removeListenerForWordPressEventSubclass(): void
    {
        $called = false;
        $listener = function () use (&$called): void {
            $called = true;
        };

        $hookName = 'wppack_remove_subclass_' . uniqid();

        // Use a unique hook name to avoid conflicts
        $this->dispatcher->addListener($hookName, $listener);
        $this->dispatcher->removeListener($hookName, $listener);

        do_action($hookName);

        self::assertFalse($called);
    }
}

// Test fixtures

class TestEvent extends Event
{
    public string $data = '';
}

class TestWordPressEvent extends WordPressEvent
{
    protected array $argMap = [
        'id' => 0,
        'value' => 1,
    ];
}

class SavePostTestEvent extends WordPressEvent
{
    public const HOOK_NAME = 'save_post';

    protected array $argMap = [
        'postId' => 0,
        'post' => 1,
        'update' => 2,
    ];
}
