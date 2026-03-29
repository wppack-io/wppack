# EventDispatcher Component

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=event_dispatcher)](https://codecov.io/github/wppack-io/wppack)

A PSR-14 compliant event dispatcher that uses the WordPress hook system (`$wp_filter`) as its backend.

> **Recommended:** EventDispatcher is the recommended approach for new implementations. It handles both custom application events and WordPress hooks (actions/filters) via `WordPressEvent` / Extended Event classes with full type safety. See the [Plugin Development Guide](../../../docs/guides/plugin-development.md#イベントフック登録) for usage patterns.

## Installation

```bash
composer require wppack/event-dispatcher
```

## Basic Usage

### Dispatching Custom Events

```php
use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\EventDispatcher\EventDispatcher;

// Define an event class
class OrderPlacedEvent extends Event
{
    public function __construct(
        public readonly int $orderId,
    ) {}
}

$dispatcher = new EventDispatcher();

// Register a listener
$dispatcher->addListener(OrderPlacedEvent::class, function (OrderPlacedEvent $event): void {
    // Handle the order...
});

// Dispatch the event
$event = $dispatcher->dispatch(new OrderPlacedEvent(orderId: 42));
```

### Listening to WordPress Hooks

```php
use WpPack\Component\EventDispatcher\WordPressEvent;

$dispatcher->addListener('save_post', function (WordPressEvent $event): void {
    [$postId, $post, $update] = $event->args;
    // Handle save...
});
```

### Extended Event Classes

```php
class SavePostEvent extends WordPressEvent
{
    public const HOOK_NAME = 'save_post';

    protected array $argMap = [
        'postId' => 0,
        'post'   => 1,
        'update' => 2,
    ];
}

$dispatcher->addListener(
    SavePostEvent::class,
    function (SavePostEvent $event): void {
        $event->getPostId();  // → int
        $event->getPost();    // → \WP_Post
        $event->getUpdate();  // → bool
    },
);
```

### `#[AsEventListener]` Attribute

```php
use WpPack\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrderPlacedEvent::class)]
final class SendOrderConfirmation
{
    public function __invoke(OrderPlacedEvent $event): void
    {
        // Send email...
    }
}

final class OrderHandler
{
    #[AsEventListener(event: OrderPlacedEvent::class, priority: 10)]
    public function onPlaced(OrderPlacedEvent $event): void { ... }

    #[AsEventListener(event: 'save_post')]
    public function onSavePost(WordPressEvent $event): void { ... }
}
```

### Subscribers

```php
use WpPack\Component\EventDispatcher\EventSubscriberInterface;

class OrderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlacedEvent::class => 'onPlaced',
            OrderCancelledEvent::class => ['onCancelled', 20],
            'save_post' => 'onSavePost',
        ];
    }

    public function onPlaced(OrderPlacedEvent $event): void { ... }
    public function onCancelled(OrderCancelledEvent $event): void { ... }
    public function onSavePost(WordPressEvent $event): void { ... }
}
```

## DI Integration

```php
use WpPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;

$builder->addServiceProvider(new EventDispatcherServiceProvider());
$builder->addCompilerPass(new RegisterEventListenersPass());
```

`RegisterEventListenersPass` automatically discovers:
- Services annotated with the `#[AsEventListener]` attribute
- Services implementing `EventSubscriberInterface`

## Test Utilities

```php
use WpPack\Component\EventDispatcher\Test\EventDispatcherTestTrait;

class MyTest extends TestCase
{
    use EventDispatcherTestTrait;

    public function testOrderPlaced(): void
    {
        $this->getEventDispatcher()->addListener(
            OrderPlacedEvent::class,
            function (OrderPlacedEvent $event): void { ... },
        );

        $this->dispatch(new OrderPlacedEvent(orderId: 1));

        $this->assertEventDispatched(OrderPlacedEvent::class);
    }
}
```
