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

namespace WPPack\Component\EventDispatcher\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\EventDispatcher\Event;
use WPPack\Component\EventDispatcher\EventDispatcher;
use WPPack\Component\EventDispatcher\EventSubscriberInterface;
use WPPack\Component\EventDispatcher\WordPressEvent;

/**
 * WordPress action / filter integration tests.
 *
 * Verifies that EventDispatcher listeners are actually called by
 * WordPress's do_action() / apply_filters() and can read / modify values.
 */
final class WordPressIntegrationTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    // ---------------------------------------------------------------
    // Action: do_action() → EventDispatcher listener
    // ---------------------------------------------------------------

    #[Test]
    public function actionListenerReceivesSingleArg(): void
    {
        $received = null;

        $this->dispatcher->addListener('wppack_action_single', function (WordPressEvent $e) use (&$received): void {
            $received = $e;
        });

        do_action('wppack_action_single', 'hello');

        self::assertInstanceOf(WordPressEvent::class, $received);
        self::assertSame('wppack_action_single', $received->hookName);
        self::assertSame('hello', $received->args[0]);
    }

    #[Test]
    public function actionListenerReceivesMultipleArgs(): void
    {
        $received = null;

        $this->dispatcher->addListener('wppack_action_multi', function (WordPressEvent $e) use (&$received): void {
            $received = $e;
        });

        do_action('wppack_action_multi', 42, 'post_title', ['tag1', 'tag2']);

        self::assertSame(42, $received->args[0]);
        self::assertSame('post_title', $received->args[1]);
        self::assertSame(['tag1', 'tag2'], $received->args[2]);
    }

    #[Test]
    public function multipleActionListenersCalledInPriorityOrder(): void
    {
        $order = [];

        $this->dispatcher->addListener('wppack_action_order', function () use (&$order): void {
            $order[] = 'late';
        }, priority: 99);

        $this->dispatcher->addListener('wppack_action_order', function () use (&$order): void {
            $order[] = 'early';
        }, priority: 1);

        $this->dispatcher->addListener('wppack_action_order', function () use (&$order): void {
            $order[] = 'default';
        }, priority: 10);

        do_action('wppack_action_order');

        self::assertSame(['early', 'default', 'late'], $order);
    }

    #[Test]
    public function actionListenerCoexistsWithNativeAddAction(): void
    {
        $calls = [];

        // native
        add_action('wppack_action_coexist', function () use (&$calls): void {
            $calls[] = 'native';
        }, 5);

        // EventDispatcher
        $this->dispatcher->addListener('wppack_action_coexist', function () use (&$calls): void {
            $calls[] = 'dispatcher';
        }, priority: 15);

        do_action('wppack_action_coexist');

        self::assertSame(['native', 'dispatcher'], $calls);
    }

    #[Test]
    public function removedActionListenerIsNotCalled(): void
    {
        $called = false;
        $listener = function () use (&$called): void {
            $called = true;
        };

        $this->dispatcher->addListener('wppack_action_rm', $listener);
        $this->dispatcher->removeListener('wppack_action_rm', $listener);

        do_action('wppack_action_rm');

        self::assertFalse($called);
    }

    // ---------------------------------------------------------------
    // Filter: apply_filters() → EventDispatcher listener → value change
    // ---------------------------------------------------------------

    #[Test]
    public function filterListenerReceivesOriginalValue(): void
    {
        $received = null;

        $this->dispatcher->addListener('wppack_filter_read', function (WordPressEvent $e) use (&$received): void {
            $received = $e->filterValue;
        });

        apply_filters('wppack_filter_read', 'original_value');

        self::assertSame('original_value', $received);
    }

    #[Test]
    public function filterListenerModifiesValue(): void
    {
        $this->dispatcher->addListener('wppack_filter_modify', function (WordPressEvent $e): void {
            $e->filterValue = strtoupper($e->filterValue);
        });

        $result = apply_filters('wppack_filter_modify', 'hello');

        self::assertSame('HELLO', $result);
    }

    #[Test]
    public function filterChainPassesModifiedValueThrough(): void
    {
        $this->dispatcher->addListener('wppack_filter_chain', function (WordPressEvent $e): void {
            $e->filterValue .= '_first';
        }, priority: 10);

        $this->dispatcher->addListener('wppack_filter_chain', function (WordPressEvent $e): void {
            $e->filterValue .= '_second';
        }, priority: 20);

        $result = apply_filters('wppack_filter_chain', 'start');

        self::assertSame('start_first_second', $result);
    }

    #[Test]
    public function filterListenerWithMultipleArgs(): void
    {
        $receivedEvent = null;

        $this->dispatcher->addListener('wppack_filter_args', function (WordPressEvent $e) use (&$receivedEvent): void {
            $receivedEvent = $e;
            // Modify based on additional args
            $e->filterValue = $e->args[0] . ':' . $e->args[1] . ':' . $e->args[2];
        });

        $result = apply_filters('wppack_filter_args', 'base', 'extra1', 'extra2');

        self::assertSame('base:extra1:extra2', $result);
        self::assertSame('base', $receivedEvent->args[0]);
        self::assertSame('extra1', $receivedEvent->args[1]);
        self::assertSame('extra2', $receivedEvent->args[2]);
    }

    #[Test]
    public function filterListenerPreservesValueWhenNotModified(): void
    {
        $this->dispatcher->addListener('wppack_filter_passthrough', function (WordPressEvent $e): void {
            // Read only, no modification
        });

        $result = apply_filters('wppack_filter_passthrough', 'unchanged');

        self::assertSame('unchanged', $result);
    }

    #[Test]
    public function filterCoexistsWithNativeAddFilter(): void
    {
        // native filter (first)
        add_filter('wppack_filter_coexist', function (string $value): string {
            return $value . '_native';
        }, 5);

        // EventDispatcher filter (second)
        $this->dispatcher->addListener('wppack_filter_coexist', function (WordPressEvent $e): void {
            $e->filterValue .= '_dispatcher';
        }, priority: 15);

        $result = apply_filters('wppack_filter_coexist', 'start');

        self::assertSame('start_native_dispatcher', $result);
    }

    #[Test]
    public function filterReturnsNonStringTypes(): void
    {
        $this->dispatcher->addListener('wppack_filter_array', function (WordPressEvent $e): void {
            $e->filterValue[] = 'added';
        });

        $result = apply_filters('wppack_filter_array', ['original']);

        self::assertSame(['original', 'added'], $result);
    }

    #[Test]
    public function filterReplacesValueEntirely(): void
    {
        $this->dispatcher->addListener('wppack_filter_replace', function (WordPressEvent $e): void {
            $e->filterValue = 42;
        });

        $result = apply_filters('wppack_filter_replace', 'was_string');

        self::assertSame(42, $result);
    }

    #[Test]
    public function removedFilterListenerDoesNotModifyValue(): void
    {
        $listener = function (WordPressEvent $e): void {
            $e->filterValue = 'modified';
        };

        $this->dispatcher->addListener('wppack_filter_rm', $listener);
        $this->dispatcher->removeListener('wppack_filter_rm', $listener);

        $result = apply_filters('wppack_filter_rm', 'original');

        self::assertSame('original', $result);
    }

    // ---------------------------------------------------------------
    // WordPressEvent subclass with action / filter
    // ---------------------------------------------------------------

    #[Test]
    public function wordPressEventSubclassReceivesActionArgs(): void
    {
        $received = null;

        $this->dispatcher->addListener(IntegrationSavePostEvent::class, function (IntegrationSavePostEvent $e) use (&$received): void {
            $received = $e;
        });

        do_action('save_post', 123, (object) ['post_title' => 'Test'], false);

        self::assertInstanceOf(IntegrationSavePostEvent::class, $received);
        self::assertSame(123, $received->getPostId());
        self::assertSame('Test', $received->getPost()->post_title);
        self::assertFalse($received->getUpdate());
    }

    #[Test]
    public function wordPressEventSubclassModifiesFilterValue(): void
    {
        $this->dispatcher->addListener('wppack_typed_filter', function (IntegrationFilterEvent $e): void {
            $e->filterValue = $e->filterValue * 2 + $e->getMultiplier();
        }, eventClass: IntegrationFilterEvent::class);

        $result = apply_filters('wppack_typed_filter', 10, 5);

        self::assertSame(25, $result);
    }

    // ---------------------------------------------------------------
    // Subscriber with WordPress hooks
    // ---------------------------------------------------------------

    #[Test]
    public function subscriberListensToWordPressAction(): void
    {
        $subscriber = new IntegrationActionSubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        do_action('wppack_int_sub_action', 'action_data');

        self::assertTrue($subscriber->called);
        self::assertSame('action_data', $subscriber->receivedArgs[0]);
    }

    #[Test]
    public function subscriberModifiesWordPressFilter(): void
    {
        $subscriber = new IntegrationFilterSubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        $result = apply_filters('wppack_int_sub_filter', 'input');

        self::assertSame('input_filtered', $result);
    }

    // ---------------------------------------------------------------
    // dispatch() + native add_action()
    // ---------------------------------------------------------------

    #[Test]
    public function nativeAddActionReceivesDispatchedEvent(): void
    {
        $received = null;

        add_action(IntegrationCustomEvent::class, function (IntegrationCustomEvent $event) use (&$received): void {
            $received = $event;
        });

        $event = new IntegrationCustomEvent('payload');
        $this->dispatcher->dispatch($event);

        self::assertSame($event, $received);
        self::assertSame('payload', $received->data);
    }

    #[Test]
    public function nativeAddActionAndDispatcherListenerBothCalled(): void
    {
        $calls = [];

        add_action(IntegrationCustomEvent::class, function () use (&$calls): void {
            $calls[] = 'native';
        }, 5);

        $this->dispatcher->addListener(IntegrationCustomEvent::class, function () use (&$calls): void {
            $calls[] = 'dispatcher';
        }, priority: 15);

        $this->dispatcher->dispatch(new IntegrationCustomEvent('test'));

        self::assertSame(['native', 'dispatcher'], $calls);
    }

    #[Test]
    public function dispatchedEventMutationVisibleToCaller(): void
    {
        $this->dispatcher->addListener(IntegrationCustomEvent::class, function (IntegrationCustomEvent $e): void {
            $e->data = 'mutated';
        });

        $event = new IntegrationCustomEvent('original');
        $result = $this->dispatcher->dispatch($event);

        self::assertSame('mutated', $result->data);
    }
}

// ---------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------

class IntegrationSavePostEvent extends WordPressEvent
{
    public const HOOK_NAME = 'save_post';

    protected array $argMap = [
        'postId' => 0,
        'post' => 1,
        'update' => 2,
    ];
}

class IntegrationFilterEvent extends WordPressEvent
{
    protected array $argMap = [
        'value' => 0,
        'multiplier' => 1,
    ];
}

class IntegrationCustomEvent extends Event
{
    public function __construct(
        public string $data,
    ) {}
}

class IntegrationActionSubscriber implements EventSubscriberInterface
{
    public bool $called = false;

    /** @var list<mixed> */
    public array $receivedArgs = [];

    public static function getSubscribedEvents(): array
    {
        return [
            'wppack_int_sub_action' => ['onAction', 10, 1],
        ];
    }

    public function onAction(WordPressEvent $e): void
    {
        $this->called = true;
        $this->receivedArgs = $e->args;
    }
}

class IntegrationFilterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'wppack_int_sub_filter' => ['onFilter', 10, 1],
        ];
    }

    public function onFilter(WordPressEvent $e): void
    {
        $e->filterValue .= '_filtered';
    }
}
