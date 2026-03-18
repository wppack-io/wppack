# EventDispatcher Component

**パッケージ:** `wppack/event-dispatcher`
**名前空間:** `WpPack\Component\EventDispatcher\`
**レイヤー:** Infrastructure

PSR-14 準拠のイベントディスパッチャー。WordPress フックシステム（`$wp_filter`）をバックエンドとして使用し、型安全なイベントオブジェクト、アトリビュートベースのリスナー登録、イベント伝播制御、WordPress フックとの双方向連携を提供します。

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
use WpPack\Component\EventDispatcher\Attribute\AsEventListener;

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

    #[AsEventListener(priority: 10)]
    public function sendWelcomeEmail(UserRegisteredEvent $event): void
    {
        $this->mailer->send(
            $event->user->email,
            'Welcome!',
            'Welcome to our site!'
        );
    }

    #[AsEventListener(priority: 20)]
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

### カスタムイベント

強い型付けのオブジェクトとしてイベントを定義します。`Event` 基底クラスは PSR-14 の `StoppableEventInterface` を実装済みです：

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

### WordPressEvent

WordPress フックを PSR-14 イベントとしてラップするクラスです。フック名と引数を保持し、マジック getter でタイプセーフなアクセスを提供します：

```php
use WpPack\Component\EventDispatcher\WordPressEvent;

// 汎用的な WordPressEvent として WordPress フックをリッスン
$dispatcher->addListener('save_post', function (WordPressEvent $event): void {
    [$postId, $post, $update] = $event->args;
    // フック名: $event->hookName
}, acceptedArgs: 3);
```

#### カスタム WordPressEvent サブクラス

`HOOK_NAME` 定数と `$argMap` でタイプセーフなアクセサを定義できます：

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

// WordPressEvent サブクラスをイベント名として使用
$dispatcher->addListener(
    SavePostEvent::class,
    function (SavePostEvent $event): void {
        $event->getPostId();  // → int（args[0]）
        $event->getPost();    // → \WP_Post（args[1]）
        $event->getUpdate();  // → bool（args[2]）
    },
    acceptedArgs: 3,
);
```

#### フィルター値の変更

`WordPressEvent` の `$filterValue` プロパティで `apply_filters()` の戻り値を変更できます：

```php
$dispatcher->addListener('the_content', function (WordPressEvent $event): void {
    // filterValue は args[0]（フィルター対象の値）で初期化される
    $event->filterValue = $event->filterValue . '<p>Appended content</p>';
}, acceptedArgs: 1);
```

## イベントリスナー

### `#[AsEventListener]` アトリビュート

クラスまたはメソッドに付与可能（`IS_REPEATABLE`）。DI コンテナの `RegisterEventListenersPass` で自動検出されます。

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|---------|------|
| `event` | `?string` | `null` | イベントクラス FQCN（省略時はメソッドの第1引数の型から自動解決） |
| `method` | `?string` | `null` | 呼び出すメソッド名（省略時はクラスレベルで `__invoke`、メソッドレベルで付与先メソッド） |
| `priority` | `int` | `10` | 実行優先度（WordPress 準拠: 小さい = 早い） |
| `acceptedArgs` | `int` | `1` | WordPress フック用の引数数 |

#### クラスレベル（単一イベント Invokable リスナー）

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

`event` パラメータ省略時、`__invoke` メソッドの第1引数の型ヒントからイベントクラスが自動解決されます。

#### メソッドレベル（複数イベント処理）

```php
final class OrderEventHandler
{
    #[AsEventListener(priority: 10)]
    public function sendOrderConfirmation(OrderPlacedEvent $event): void
    {
        // メソッド引数の型からイベントクラスが自動解決される
    }

    #[AsEventListener(priority: 20)]
    public function updateInventory(OrderPlacedEvent $event): void
    {
        // ...
    }

    #[AsEventListener(event: 'save_post', acceptedArgs: 3)]
    public function onSavePost(WordPressEvent $event): void
    {
        // WordPress フックの場合は event パラメータでフック名を指定
    }
}
```

### 手動登録

```php
use WpPack\Component\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher();

// カスタムイベントリスナー
$dispatcher->addListener(
    UserRegisteredEvent::class,
    function (UserRegisteredEvent $event): void {
        // イベントを処理
    },
    priority: 10,
);

// WordPress フックリスナー
$dispatcher->addListener(
    'save_post',
    function (WordPressEvent $event): void {
        [$postId, $post, $update] = $event->args;
    },
    priority: 10,
    acceptedArgs: 3,
);

// WordPress フックリスナー（カスタムイベントクラス指定）
$dispatcher->addListener(
    'save_post',
    function (SavePostEvent $event): void {
        $event->getPostId();
    },
    priority: 10,
    acceptedArgs: 3,
    eventClass: SavePostEvent::class,
);
```

## イベントサブスクライバー

`EventSubscriberInterface` を実装して、関連するイベントハンドラーを1つのクラスにまとめます。DI コンテナの `RegisterEventListenersPass` で自動検出されます（`#[AsEventListener]` アトリビュートが付与されている場合はサブスクライバーとしての登録はスキップされます）。

```php
use WpPack\Component\EventDispatcher\EventSubscriberInterface;

final class UserLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly CacheService $cache,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // メソッド名のみ（priority: 10）
            UserUpdatedEvent::class => 'syncUserData',
            // [メソッド名, priority]
            UserDeletedEvent::class => ['cleanupUserData', 5],
            // [メソッド名, priority, acceptedArgs]（WordPress フック用）
            'save_post' => ['onSavePost', 10, 3],
            // 同一イベントに複数リスナー
            UserRegisteredEvent::class => [
                ['trackRegistration', 20],
                ['clearUserCache', 10],
            ],
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

    public function syncUserData(UserUpdatedEvent $event): void { /* ... */ }
    public function cleanupUserData(UserDeletedEvent $event): void { /* ... */ }
    public function onSavePost(WordPressEvent $event): void { /* ... */ }
}
```

### `getSubscribedEvents()` の値フォーマット

| フォーマット | 例 | 説明 |
|-------------|------|------|
| `string` | `'methodName'` | メソッド名のみ（priority: 10） |
| `[string, int]` | `['methodName', 20]` | メソッド名 + 優先度 |
| `[string, int, int]` | `['methodName', 10, 3]` | + acceptedArgs（WordPress フック用） |
| `[string, int, int, class-string]` | `['methodName', 10, 1, SavePostEvent::class]` | + eventClass（WordPressEvent サブクラス指定） |
| `list<above>` | `[['method1', 10], ['method2', 20]]` | 同一イベントに複数リスナー |

## イベントのディスパッチ

```php
use WpPack\Component\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher();
$event = $dispatcher->dispatch(new OrderPlacedEvent($order, $customer));
```

内部的に `do_action(EventClass::class, $event)` を呼び出すため、WordPress の `add_action()` で登録したリスナーも連携可能です。

## イベント伝播の停止

`Event` 基底クラスは `StoppableEventInterface` を実装済みです。`stopPropagation()` を呼び出すと後続リスナーの実行がスキップされます：

```php
use WpPack\Component\EventDispatcher\Event;

final class PaymentProcessingEvent extends Event
{
    public function __construct(
        public readonly Payment $payment,
        public readonly Order $order,
    ) {}
}

final class PaymentHandler
{
    #[AsEventListener(priority: 5)]
    public function validatePayment(PaymentProcessingEvent $event): void
    {
        if ($this->security->isFraudulent($event->payment)) {
            $event->stopPropagation(); // 以降のリスナーは実行されない
            throw new FraudulentPaymentException();
        }
    }

    #[AsEventListener(priority: 15)]
    public function processPayment(PaymentProcessingEvent $event): void
    {
        // validatePayment で stopPropagation() が呼ばれた場合、実行されない
        $this->paymentGateway->charge($event->payment);
    }
}
```

## DI 統合

```php
use WpPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;

$builder->addServiceProvider(new EventDispatcherServiceProvider());
$builder->addCompilerPass(new RegisterEventListenersPass());
```

`RegisterEventListenersPass` は以下を自動検出します：

1. `#[AsEventListener]` アトリビュートが付与されたクラス / メソッド
2. `EventSubscriberInterface` を実装したクラス

両方が該当する場合、`#[AsEventListener]` が優先され、サブスクライバーとしての重複登録は行われません。

## テスト

### EventDispatcherTestTrait

テスト用のヘルパートレイト。イベントのディスパッチとアサーションを簡潔に記述できます：

```php
use WpPack\Component\EventDispatcher\Test\EventDispatcherTestTrait;

class OrderServiceTest extends TestCase
{
    use EventDispatcherTestTrait;

    public function testOrderPlacedEventIsDispatched(): void
    {
        $service = new OrderService($this->getEventDispatcher());
        $service->placeOrder([
            'customer_id' => 123,
            'total' => 99.99,
        ]);

        $this->assertEventDispatched(OrderPlacedEvent::class);
    }

    public function testEventNotDispatched(): void
    {
        $this->assertEventNotDispatched(OrderPlacedEvent::class);
    }

    public function testLastDispatchedEvent(): void
    {
        $this->dispatch(new OrderPlacedEvent($order, $customer));

        $event = $this->getLastDispatchedEvent(OrderPlacedEvent::class);
        self::assertSame($order, $event->order);
    }
}
```

| メソッド | 説明 |
|---------|------|
| `getEventDispatcher()` | `EventDispatcher` シングルトンインスタンスを取得 |
| `dispatch(object $event)` | イベントをディスパッチし、履歴に記録 |
| `getLastDispatchedEvent(class-string)` | 指定クラスの最後のイベントを取得（`null` 可） |
| `assertEventDispatched(class-string)` | イベントがディスパッチされたことをアサート |
| `assertEventNotDispatched(class-string)` | イベントがディスパッチされていないことをアサート |
| `resetDispatchedEvents()` | ディスパッチ履歴をリセット |

### リスナーの単体テスト

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
| `EventDispatcher` | PSR-14 準拠イベントディスパッチャー（WordPress フックシステムをバックエンドに使用） |
| `Event` | 基底イベントクラス（`StoppableEventInterface` 実装済み） |
| `WordPressEvent` | WordPress フック用イベント（`$hookName`, `$args`, `$filterValue`, マジック getter） |
| `EventSubscriberInterface` | サブスクライバーインターフェース（`getSubscribedEvents()` を定義） |
| `Attribute\AsEventListener` | リスナー登録アトリビュート（クラス / メソッドに付与可能、`IS_REPEATABLE`） |
| `DependencyInjection\EventDispatcherServiceProvider` | DI サービスプロバイダー |
| `DependencyInjection\RegisterEventListenersPass` | アトリビュート / サブスクライバーの自動検出コンパイラーパス |
| `Test\EventDispatcherTestTrait` | テスト用トレイト（ディスパッチ履歴・アサーション） |
