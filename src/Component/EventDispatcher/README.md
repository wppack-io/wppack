# EventDispatcher コンポーネント

WordPress フックシステム（`$wp_filter`）をバックエンドとして使う PSR-14 準拠のイベントディスパッチャー。

## インストール

```bash
composer require wppack/event-dispatcher
```

## 基本的な使い方

### カスタムイベントのディスパッチ

```php
use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\EventDispatcher\EventDispatcher;

// イベントクラスを定義
class OrderPlacedEvent extends Event
{
    public function __construct(
        public readonly int $orderId,
    ) {}
}

$dispatcher = new EventDispatcher();

// リスナーを登録
$dispatcher->addListener(OrderPlacedEvent::class, function (OrderPlacedEvent $event): void {
    // 注文処理...
});

// イベントをディスパッチ
$event = $dispatcher->dispatch(new OrderPlacedEvent(orderId: 42));
```

### WordPress フックのリスニング

```php
use WpPack\Component\EventDispatcher\WordPressEvent;

$dispatcher->addListener('save_post', function (WordPressEvent $event): void {
    [$postId, $post, $update] = $event->args;
    // 保存処理...
}, acceptedArgs: 3);
```

### 拡張イベントクラス

```php
class SavePostEvent extends WordPressEvent
{
    public const string HOOK_NAME = 'save_post';

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
    acceptedArgs: 3,
);
```

### `#[AsEventListener]` 属性

```php
use WpPack\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrderPlacedEvent::class)]
final class SendOrderConfirmation
{
    public function __invoke(OrderPlacedEvent $event): void
    {
        // メール送信...
    }
}

final class OrderHandler
{
    #[AsEventListener(event: OrderPlacedEvent::class, priority: 10)]
    public function onPlaced(OrderPlacedEvent $event): void { ... }

    #[AsEventListener(event: 'save_post', acceptedArgs: 3)]
    public function onSavePost(WordPressEvent $event): void { ... }
}
```

### サブスクライバー

```php
use WpPack\Component\EventDispatcher\EventSubscriberInterface;

class OrderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlacedEvent::class => 'onPlaced',
            OrderCancelledEvent::class => ['onCancelled', 20],
            'save_post' => ['onSavePost', 10, 3],
        ];
    }

    public function onPlaced(OrderPlacedEvent $event): void { ... }
    public function onCancelled(OrderCancelledEvent $event): void { ... }
    public function onSavePost(WordPressEvent $event): void { ... }
}
```

## DI 統合

```php
use WpPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;

$builder->addServiceProvider(new EventDispatcherServiceProvider());
$builder->addCompilerPass(new RegisterEventListenersPass());
```

`RegisterEventListenersPass` は以下を自動検出します:
- `#[AsEventListener]` 属性が付与されたサービス
- `EventSubscriberInterface` を実装したサービス

## テストユーティリティ

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
