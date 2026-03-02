# EventDispatcher Component

**パッケージ:** `wppack/event-dispatcher`
**名前空間:** `WpPack\Component\EventDispatcher\`
**レイヤー:** Infrastructure

PSR-14 準拠のイベントディスパッチャー。型安全なイベントオブジェクト、アトリビュートベースのイベントリスナー・サブスクライバー、イベント伝播の停止、WordPress フックとの連携を提供します。

## インストール

```bash
composer require wppack/event-dispatcher
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('user_registered', 'send_welcome_email', 10, 2);
add_action('user_registered', 'add_to_newsletter', 20, 2);

function send_welcome_email($user_id, $userdata) {
    $user = get_user_by('id', $user_id);
    wp_mail($user->user_email, 'Welcome!', 'Welcome to our site!');
}

function add_to_newsletter($user_id, $userdata) {
    // 伝播を停止する手段がない
    $api = new NewsletterAPI();
    $api->subscribe($userdata['user_email']);
}

// 任意のデータでイベントを発火
do_action('user_registered', $user_id, $userdata);
```

### After（WpPack）

```php
use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\EventDispatcher\Attribute\EventListener;

final readonly class UserRegisteredEvent extends Event
{
    public function __construct(
        public User $user,
        public string $source = 'unknown',
    ) {}
}

final class UserEventHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly NewsletterService $newsletter,
    ) {}

    #[EventListener(UserRegisteredEvent::class, priority: 10)]
    public function sendWelcomeEmail(UserRegisteredEvent $event): void
    {
        $this->mailer->send(
            $event->user->email,
            'Welcome!',
            'Welcome to our site!'
        );
    }

    #[EventListener(UserRegisteredEvent::class, priority: 20)]
    public function addToNewsletter(UserRegisteredEvent $event): void
    {
        $this->newsletter->subscribe($event->user->email);
    }
}

// 型安全なイベントディスパッチ
$event = new UserRegisteredEvent($user, 'registration_form');
$this->dispatcher->dispatch($event);
```

## イベントクラス

強い型付けのオブジェクトとしてイベントを定義します：

```php
use WpPack\Component\EventDispatcher\Event;

final readonly class OrderPlacedEvent extends Event
{
    public function __construct(
        public Order $order,
        public Customer $customer,
        public \DateTimeImmutable $placedAt = new \DateTimeImmutable(),
    ) {}
}
```

## イベントリスナー

### アトリビュートベースの登録

単一イベントを処理するリスナーを `#[AsEventListener]` でマークします：

```php
use WpPack\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class SendWelcomeEmailListener
{
    public function __construct(
        private readonly Mailer $mailer,
    ) {}

    public function __invoke(UserRegisteredEvent $event): void
    {
        $this->mailer->send(/* ... */);
    }
}
```

### 優先度付きリスナー

```php
#[AsEventListener(priority: -10)]
final class AuditLogListener
{
    public function __invoke(UserRegisteredEvent $event): void
    {
        // 他のリスナーの後に実行される
    }
}
```

### 複数イベントの処理

`#[EventListener]` を使って1つのクラスで複数のイベントを処理できます：

```php
final class OrderEventHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly InventoryService $inventory,
    ) {}

    #[EventListener(OrderPlacedEvent::class, priority: 10)]
    public function sendOrderConfirmation(OrderPlacedEvent $event): void
    {
        $this->mailer->send(
            $event->customer->email,
            'Order Confirmation #' . $event->order->number,
            $this->renderOrderConfirmation($event->order)
        );
    }

    #[EventListener(OrderPlacedEvent::class, priority: 20)]
    public function updateInventory(OrderPlacedEvent $event): void
    {
        foreach ($event->order->getItems() as $item) {
            $this->inventory->reserve($item->sku, $item->quantity);
        }
    }
}
```

### 手動登録

```php
use WpPack\Component\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher();

$dispatcher->addListener(
    UserRegisteredEvent::class,
    function (UserRegisteredEvent $event): void {
        // イベントを処理
    },
    priority: 10,
);
```

## イベントサブスクライバー

関連するイベントハンドラーを1つのクラスにまとめます：

```php
use WpPack\Component\EventDispatcher\Attribute\AsEventSubscriber;
use WpPack\Component\EventDispatcher\EventSubscriberInterface;

#[AsEventSubscriber]
final class UserLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly CacheService $cache,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UserRegisteredEvent::class => [
                ['trackRegistration', 20],
                ['clearUserCache', 10],
            ],
            UserUpdatedEvent::class => 'syncUserData',
            UserDeletedEvent::class => 'cleanupUserData',
        ];
    }

    public function trackRegistration(UserRegisteredEvent $event): void
    {
        $this->analytics->track('user.registered', [
            'user_id' => $event->user->id,
            'source' => $event->source,
        ]);
    }

    public function clearUserCache(UserRegisteredEvent $event): void
    {
        $this->cache->forget("user:{$event->user->id}");
    }

    public function syncUserData(UserUpdatedEvent $event): void
    {
        // ユーザー更新を処理
    }

    public function cleanupUserData(UserDeletedEvent $event): void
    {
        // ユーザー削除を処理
    }
}
```

## イベントのディスパッチ

```php
use WpPack\Component\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher();

$event = $dispatcher->dispatch(new UserRegisteredEvent(
    user: $user,
));
```

```php
final class OrderService
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
    ) {}

    public function placeOrder(array $orderData): Order
    {
        $order = $this->createOrder($orderData);
        $customer = $this->getCustomer($orderData['customer_id']);

        $this->dispatcher->dispatch(new OrderPlacedEvent($order, $customer));

        return $order;
    }
}
```

## イベント伝播の停止

PSR-14 の `StoppableEventInterface` でイベントの伝播を制御できます：

```php
use Psr\EventDispatcher\StoppableEventInterface;
use WpPack\Component\EventDispatcher\Event;

final class PaymentProcessingEvent extends Event implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly Payment $payment,
        public readonly Order $order,
    ) {}

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}

final class PaymentHandler
{
    #[EventListener(PaymentProcessingEvent::class, priority: 100)]
    public function validatePayment(PaymentProcessingEvent $event): void
    {
        if ($this->security->isFraudulent($event->payment)) {
            $event->stopPropagation();
            throw new FraudulentPaymentException();
        }
    }

    #[EventListener(PaymentProcessingEvent::class, priority: 90)]
    public function processPayment(PaymentProcessingEvent $event): void
    {
        $this->paymentGateway->charge($event->payment);
    }
}
```

## WordPress フック連携

### HookBridge

WordPress フックと EventDispatcher 間のブリッジを提供します：

```php
use WpPack\Component\EventDispatcher\WordPress\HookBridge;

$bridge = new HookBridge($dispatcher);

// WordPress アクションを EventDispatcher にブリッジ
$bridge->bridgeAction('save_post', SavePostEvent::class);

// EventDispatcher イベントを WordPress フックにブリッジ
$bridge->bridgeEvent(UserRegisteredEvent::class, 'wppack_user_registered');
```

### WordPress フックからイベントへの変換

```php
final class WordPressEventBridge
{
    public function __construct(
        private readonly HookBridge $bridge,
    ) {}

    #[Action('init', priority: 10)]
    public function setupBridging(): void
    {
        // WordPress フックをイベントに変換
        $this->bridge->bridgeHookToEvent('save_post', PostSavedEvent::class,
            function ($post_id, $post, $update) {
                if ($post->post_type === 'post' && $update) {
                    return new PostSavedEvent(
                        Post::fromWpPost($post),
                        $update
                    );
                }
                return null; // 条件を満たさない場合はイベントをスキップ
            }
        );

        // イベントを WordPress フックとして公開
        $this->bridge->exposeEventAsHook(OrderPlacedEvent::class, 'wppack_order_placed',
            function (OrderPlacedEvent $event) {
                return [$event->order->id, $event->order->toArray()];
            }
        );
    }
}
```

## エラーハンドリング

```php
final class RobustEventHandler
{
    #[EventListener(OrderPlacedEvent::class, priority: 10)]
    public function processOrder(OrderPlacedEvent $event): void
    {
        try {
            $this->paymentProcessor->charge($event->order);
        } catch (PaymentException $e) {
            $this->logger->error('Payment failed', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
            return; // 他のリスナーの実行を妨げない
        }
    }
}
```

## テスト

### イベントディスパッチのテスト

```php
use WpPack\Component\EventDispatcher\Test\EventDispatcherTestTrait;

class OrderServiceTest extends TestCase
{
    use EventDispatcherTestTrait;

    public function testOrderPlacedEventIsDispatched(): void
    {
        $service = new OrderService($this->dispatcher);
        $service->placeOrder([
            'customer_id' => 123,
            'total' => 99.99,
        ]);

        $this->assertEventDispatched(OrderPlacedEvent::class);
        $this->assertEventDispatchedTimes(OrderPlacedEvent::class, 1);
    }
}
```

### イベントリスナーのテスト

```php
class WelcomeEmailHandlerTest extends TestCase
{
    public function testSendsWelcomeEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with('user@example.com', 'Welcome!', $this->anything());

        $handler = new SendWelcomeEmailListener($mailer);
        $user = new User(['id' => 1, 'email' => 'user@example.com']);
        $event = new UserRegisteredEvent($user);

        $handler($event);
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `EventDispatcher` | イベントディスパッチャー |
| `EventDispatcherInterface` | ディスパッチャーインターフェース（PSR-14） |
| `Event` | 基底イベントクラス |
| `Attribute\AsEventListener` | リスナーマーカー |
| `Attribute\EventListener` | イベントクラス指定付きリスナー |
| `Attribute\AsEventSubscriber` | サブスクライバーマーカー |
| `EventSubscriberInterface` | サブスクライバーインターフェース |
| `WordPress\HookBridge` | WordPress フックブリッジ |
| `Test\EventDispatcherTestTrait` | テスト用トレイト |
