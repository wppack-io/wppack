# Messenger コンポーネント

**パッケージ:** `wppack/messenger`
**名前空間:** `WPPack\Component\Messenger\`
**Category:** Substrate (Async & Delivery)

トランスポート非依存のメッセージバス。Symfony Messenger ライクなアーキテクチャで、メッセージの定義・ディスパッチ・ミドルウェアチェーン・ハンドラー解決・シリアライゼーションを提供します。トランスポート（SQS、同期処理など）は Bridge パッケージとして分離されています。

## インストール

```bash
composer require wppack/messenger
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 直接的な遅延処理 — グローバル関数とフック名の文字列管理
add_action('wp_ajax_nopriv_process_image', 'myplugin_process_image');
add_action('shutdown', function () {
    // shutdown フックで非同期っぽく処理するハック
    wp_remote_post(admin_url('admin-ajax.php'), [
        'body' => ['action' => 'process_image', 'id' => 42],
        'timeout' => 0.01,
        'blocking' => false,
    ]);
});

function myplugin_process_image() {
    $id = (int) ($_POST['id'] ?? 0);
    // 画像処理...
    wp_die();
}
```

### After（WPPack Messenger）

```php
use WPPack\Component\Messenger\Attribute\AsMessageHandler;

// メッセージクラス（プレーン POPO）
final readonly class ProcessImageMessage
{
    public function __construct(
        public int $attachmentId,
        public string $size = 'thumbnail',
    ) {}
}

// ハンドラー
#[AsMessageHandler]
final class ProcessImageHandler
{
    public function __invoke(ProcessImageMessage $message): void
    {
        // 画像処理...
    }
}

// ディスパッチ（トランスポートに応じて同期 or 非同期）
$messageBus->dispatch(new ProcessImageMessage(attachmentId: 42));
```

## アーキテクチャ

```
┌─ アプリケーション ─────────────────────────────────┐
│                                                    │
│  $messageBus->dispatch(new SendEmailMessage(...))  │
│                                                    │
└────────────────────┬───────────────────────────────┘
                     ↓
┌─ MessageBus ───────────────────────────────────────┐
│                                                    │
│  Envelope::wrap($message, $stamps)                 │
│                                                    │
│  MiddlewareStack                                   │
│  ├── AddBusNameStampMiddleware                     │
│  ├── AddMultisiteStampMiddleware                   │
│  ├── SendMessageMiddleware → TransportInterface    │
│  └── HandleMessageMiddleware → HandlerLocator      │
│                                                    │
└────────────────────┬───────────────────────────────┘
                     ↓
┌─ Transport ────────────────────────────────────────┐
│  SyncTransport（同期）                              │
│  SqsTransport（SQS Bridge）                        │
│  ...（拡張可能）                                    │
└────────────────────────────────────────────────────┘
```

## メッセージ定義

メッセージクラスはプレーンな POPO（Plain Old PHP Object）/ DTO として定義します。特別なアトリビュートやインターフェースは不要です。`readonly class` と constructor promotion を推奨します。

```php
final readonly class SendEmailMessage
{
    public function __construct(
        public int $userId,
        public string $subject,
        public string $body,
    ) {}
}
```

```php
final readonly class ProcessImageMessage
{
    public function __construct(
        public int $attachmentId,
        public string $size = 'thumbnail',
    ) {}
}
```

シリアライゼーション（`JsonSerializer`）はコンストラクタの public プロパティをリフレクションで読み書きするため、プロパティ名はコンストラクタ引数名と一致させてください。

## メッセージハンドラー

`#[AsMessageHandler]` アトリビュートでハンドラーを定義します。

### クラスレベル（単一メッセージ）

クラスに `#[AsMessageHandler]` を付与し、`__invoke()` メソッドの引数型からメッセージ型が自動推論されます。

```php
use WPPack\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendEmailMessageHandler
{
    public function __construct(
        private readonly Mailer $mailer,
    ) {}

    public function __invoke(SendEmailMessage $message): void
    {
        $user = get_userdata($message->userId);
        if ($user === false) {
            return;
        }

        $email = (new Email())
            ->to($user->user_email)
            ->subject($message->subject)
            ->html($message->body);

        $this->mailer->send($email);
    }
}
```

### メソッドレベル（複数メッセージ）

一つのハンドラークラスで複数のメッセージ型を処理する場合は、メソッドレベルに `#[AsMessageHandler]` を付与します。

```php
use WPPack\Component\Messenger\Attribute\AsMessageHandler;

final class NotificationHandler
{
    #[AsMessageHandler]
    public function handleEmail(SendEmailMessage $message): void { /* ... */ }

    #[AsMessageHandler]
    public function handleSms(SendSmsMessage $message): void { /* ... */ }
}
```

### `#[AsMessageHandler]` パラメータ

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `bus` | `?string` | `null` | 対象バス名（複数バス環境用） |
| `fromTransport` | `?string` | `null` | 指定トランスポートから受信時のみ処理 |
| `handles` | `?string` | `null` | メッセージクラス名を明示（引数型推論の代替） |
| `method` | `?string` | `null` | ハンドラーメソッド名を明示 |
| `priority` | `int` | `0` | ハンドラー優先度（高い値が先に実行） |

## MessageBus

`MessageBusInterface` がメッセージのディスパッチ API です。

```php
namespace WPPack\Component\Messenger;

interface MessageBusInterface
{
    /**
     * @param array<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope;
}
```

`MessageBus` はミドルウェアチェーンを通してメッセージを処理する実装です。

```php
use WPPack\Component\Messenger\MessageBus;
use WPPack\Component\Messenger\Middleware\SendMessageMiddleware;
use WPPack\Component\Messenger\Middleware\HandleMessageMiddleware;
use WPPack\Component\Messenger\Middleware\AddBusNameStampMiddleware;

$messageBus = new MessageBus([
    new AddBusNameStampMiddleware('default'),
    new SendMessageMiddleware($transports),
    new HandleMessageMiddleware($handlerLocator),
]);

// メッセージをディスパッチ
$messageBus->dispatch(new SendEmailMessage(
    userId: 123,
    subject: 'Welcome!',
    body: 'Thanks for joining!',
));
```

`dispatch()` はメッセージオブジェクトまたは `Envelope` を受け取ります。メッセージオブジェクトの場合、内部で `Envelope::wrap()` によりラップされます。

## Envelope と Stamp

`Envelope` はメッセージと Stamp（メタデータ）を束ねる不変オブジェクトです。コンストラクタは private であり、必ず `Envelope::wrap()` を使って生成します。

### Envelope::wrap()

```php
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\Stamp\DelayStamp;

// メッセージから Envelope を生成
$envelope = Envelope::wrap(new SendEmailMessage(userId: 123, subject: 'Hi', body: '...'));

// Stamp 付きで生成
$envelope = Envelope::wrap(
    new SendEmailMessage(userId: 123, subject: 'Hi', body: '...'),
    [new DelayStamp(delayInMilliseconds: 300_000)],
);

// Envelope を渡した場合、既存の Stamp にマージ
$envelope = Envelope::wrap($envelope, [new PriorityStamp(priority: 1)]);
```

### Immutable API

すべての操作はクローンを返します。元の Envelope は変更されません。

```php
// Stamp を追加
$newEnvelope = $envelope->with(new SentStamp('sqs'));

// 特定の Stamp クラスを全削除
$newEnvelope = $envelope->withoutAll(DelayStamp::class);
```

### Stamp の取得

```php
// 最後に追加された Stamp を取得（null の場合はなし）
$delayStamp = $envelope->last(DelayStamp::class);

// 特定クラスの Stamp をすべて取得
$stamps = $envelope->all(DelayStamp::class);

// 全 Stamp を取得
$allStamps = $envelope->all();

// メッセージオブジェクトを取得
$message = $envelope->getMessage();
```

### 組み込み Stamp

| Stamp | コンストラクタ | 説明 |
|-------|-------------|------|
| `DelayStamp` | `delayInMilliseconds: int` | 指定ミリ秒だけ配信を遅延 |
| `PriorityStamp` | `priority: int` | メッセージの優先度 |
| `TransportStamp` | `transportName: string` | 送信先トランスポートを名前で指定 |
| `BusNameStamp` | `busName: string` | ディスパッチ元バス名（`AddBusNameStampMiddleware` が自動付与） |
| `MultisiteStamp` | `blogId: int` | マルチサイトのブログ ID（`AddMultisiteStampMiddleware` が自動付与） |
| `SentStamp` | `transportName: string` | 送信済みマーカー（`SendMessageMiddleware` が自動付与） |
| `ReceivedStamp` | `transportName: string` | 受信マーカー（コンシューマーが付与） |
| `HandledStamp` | `result: mixed, handlerName: string` | 処理済みマーカー（`HandleMessageMiddleware` が自動付与） |

### ディスパッチ時の Stamp 指定

`dispatch()` の第二引数で Stamp を付与できます。

```php
use WPPack\Component\Messenger\Stamp\DelayStamp;
use WPPack\Component\Messenger\Stamp\TransportStamp;

// 5分後に実行
$messageBus->dispatch(
    new SendEmailMessage(userId: 123, subject: 'Reminder', body: '...'),
    [new DelayStamp(delayInMilliseconds: 300_000)],
);

// 特定のトランスポートに送信
$messageBus->dispatch(
    new UrgentMessage(),
    [new TransportStamp(transportName: 'sqs')],
);
```

## ミドルウェア

`MiddlewareInterface` を実装してメッセージ処理パイプラインをカスタマイズできます。

```php
namespace WPPack\Component\Messenger\Middleware;

interface MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope;
}
```

### 組み込みミドルウェア

| ミドルウェア | 説明 |
|------------|------|
| `AddBusNameStampMiddleware` | `BusNameStamp` を自動付与。コンストラクタで `busName` を指定（デフォルト `'default'`） |
| `AddMultisiteStampMiddleware` | マルチサイト環境で `MultisiteStamp` を自動付与（`get_current_blog_id()` を使用） |
| `SendMessageMiddleware` | `TransportStamp` で指定されたトランスポートにメッセージを送信。`SentStamp` を付与。非同期トランスポートの場合はハンドラーに到達しない |
| `HandleMessageMiddleware` | `HandlerLocatorInterface` からハンドラーを取得して実行。`HandledStamp` を付与 |

### カスタムミドルウェアの作成

```php
use WPPack\Component\Messenger\Middleware\MiddlewareInterface;
use WPPack\Component\Messenger\Middleware\StackInterface;
use WPPack\Component\Messenger\Envelope;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->logger->info('Dispatching {class}', [
            'class' => $envelope->getMessage()::class,
        ]);

        $envelope = $stack->next()->handle($envelope, $stack);

        $this->logger->info('Dispatched {class}', [
            'class' => $envelope->getMessage()::class,
        ]);

        return $envelope;
    }
}
```

### MiddlewareStack

`MiddlewareStack` はミドルウェアの実行スタックです。`next()` で次のミドルウェアを取得し、すべて消費されるとパススルーの匿名クラスを返します。

```php
use WPPack\Component\Messenger\MessageBus;

$messageBus = new MessageBus([
    new LoggingMiddleware($logger),
    new AddBusNameStampMiddleware('command'),
    new AddMultisiteStampMiddleware(),
    new SendMessageMiddleware(['sqs' => $sqsTransport]),
    new HandleMessageMiddleware($handlerLocator),
]);
```

## トランスポート

`TransportInterface` はメッセージの送信先を抽象化します。

```php
namespace WPPack\Component\Messenger\Transport;

interface TransportInterface
{
    public function getName(): string;
    public function send(Envelope $envelope): Envelope;
}
```

### SyncTransport

同期トランスポート。メッセージをそのまま返し、`HandleMessageMiddleware` で即座に処理されます。開発環境やテスト環境で非同期処理を無効化する場合に使用します。

```php
use WPPack\Component\Messenger\Transport\SyncTransport;

$syncTransport = new SyncTransport(); // getName() → 'sync'
```

### Bridge パッケージ（トランスポート実装）

| パッケージ | トランスポート | 詳細 |
|-----------|-------------|------|
| `wppack/sqs-messenger` | Amazon SQS | [sqs-messenger.md](sqs-messenger.md) |

## ハンドラーロケーター

### HandlerLocatorInterface

メッセージクラスからハンドラーを解決するインターフェースです。

```php
namespace WPPack\Component\Messenger\Handler;

interface HandlerLocatorInterface
{
    /**
     * @return iterable<HandlerDescriptor>
     */
    public function getHandlers(object $message): iterable;
}
```

### HandlerLocator

デフォルトのハンドラーロケーター実装。コンストラクタで `メッセージクラス => callable のリスト` を渡すか、`addHandler()` で個別に登録します。

```php
use WPPack\Component\Messenger\Handler\HandlerLocator;

$handlerLocator = new HandlerLocator([
    SendEmailMessage::class => [
        [new SendEmailMessageHandler($mailer), '__invoke'],
    ],
    ProcessImageMessage::class => [
        [new ProcessImageHandler(), '__invoke'],
    ],
]);

// 個別登録
$handlerLocator->addHandler(
    SendEmailMessage::class,
    [new SendEmailMessageHandler($mailer), '__invoke'],
    'SendEmailMessageHandler',
);
```

### HandlerDescriptor

ハンドラーの callable とその名前をカプセル化します。

```php
use WPPack\Component\Messenger\Handler\HandlerDescriptor;

$descriptor = new HandlerDescriptor(
    handler: [new MyHandler(), '__invoke'],
    name: 'MyHandler::__invoke',
);

$handler = $descriptor->getHandler(); // Closure
$name = $descriptor->getName();       // string
```

## シリアライゼーション

### SerializerInterface

メッセージのエンコード（送信時）とデコード（受信時）を行うインターフェースです。

```php
namespace WPPack\Component\Messenger\Serializer;

interface SerializerInterface
{
    /**
     * @return array{headers: array<string, mixed>, body: string}
     */
    public function encode(Envelope $envelope): array;

    /**
     * @param array{headers: array<string, mixed>, body: string} $data
     */
    public function decode(array $data): Envelope;
}
```

### JsonSerializer

リフレクションベースの JSON シリアライザー。メッセージとスタンプの public プロパティを読み書きします。

```php
use WPPack\Component\Messenger\Serializer\JsonSerializer;

$serializer = new JsonSerializer();

// エンコード
$data = $serializer->encode($envelope);
// [
//     'headers' => ['type' => 'App\SendEmailMessage', 'stamps' => [...]],
//     'body' => '{"userId":123,"subject":"Hi","body":"..."}',
// ]

// デコード
$envelope = $serializer->decode($data);
```

エンコード結果の構造:

| キー | 内容 |
|------|------|
| `headers.type` | メッセージクラスの FQCN |
| `headers.stamps` | Stamp クラス名 → プロパティ配列のリスト |
| `body` | メッセージの public プロパティを JSON エンコードした文字列 |

## テスト

`TestMessageBus` はテスト用の `MessageBusInterface` 実装です。実際のトランスポートやミドルウェアを使わず、ディスパッチされたメッセージを記録します。

```php
use WPPack\Component\Messenger\Test\TestMessageBus;

$testBus = new TestMessageBus();

$testBus->dispatch(new SendEmailMessage(
    userId: 123,
    subject: 'Test',
    body: 'Test body',
));

// ディスパッチされた Envelope のリスト
$envelopes = $testBus->getDispatched();
$this->assertCount(1, $envelopes);
$this->assertInstanceOf(SendEmailMessage::class, $envelopes[0]->getMessage());

// メッセージオブジェクトのリスト（Envelope を剥がしたもの）
$messages = $testBus->getDispatchedMessages();
$this->assertInstanceOf(SendEmailMessage::class, $messages[0]);
$this->assertSame(123, $messages[0]->userId);

// リセット
$testBus->reset();
$this->assertCount(0, $testBus->getDispatched());
```

### TestMessageBus API

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `dispatch(object $message, array $stamps = [])` | `Envelope` | メッセージを記録して Envelope を返す |
| `getDispatched()` | `list<Envelope>` | ディスパッチされた Envelope のリスト |
| `getDispatchedMessages()` | `list<object>` | メッセージオブジェクトのリスト |
| `reset()` | `void` | 記録をクリア |

## 例外

| 例外 | 説明 |
|------|------|
| `TransportException` | トランスポートでの送信失敗 |
| `HandlerFailedException` | ハンドラーの実行失敗（Envelope と例外リストを保持） |
| `NoHandlerForMessageException` | メッセージに対応するハンドラーがない |
| `MessageDecodingFailedException` | デシリアライゼーション失敗 |
| `InvalidArgumentException` | 不正な引数 |

## 主要クラス一覧

| クラス | 説明 |
|-------|------|
| `Attribute\AsMessageHandler` | ハンドラーマーカーアトリビュート |
| `MessageBusInterface` | メッセージディスパッチインターフェース |
| `MessageBus` | ミドルウェアチェーンベースの MessageBus 実装 |
| `Envelope` | メッセージ + Stamp の immutable ラッパー |
| `Stamp\StampInterface` | Stamp マーカーインターフェース |
| `Stamp\DelayStamp` | 遅延配信（ミリ秒指定） |
| `Stamp\PriorityStamp` | 優先度指定 |
| `Stamp\TransportStamp` | トランスポート指定 |
| `Stamp\BusNameStamp` | バス名マーカー |
| `Stamp\MultisiteStamp` | マルチサイトブログ ID マーカー |
| `Stamp\SentStamp` | 送信済みマーカー |
| `Stamp\ReceivedStamp` | 受信マーカー |
| `Stamp\HandledStamp` | 処理済みマーカー |
| `Transport\TransportInterface` | トランスポートインターフェース |
| `Transport\SyncTransport` | 同期トランスポート（パススルー） |
| `Middleware\MiddlewareInterface` | ミドルウェアインターフェース |
| `Middleware\StackInterface` | ミドルウェアスタックインターフェース |
| `Middleware\MiddlewareStack` | ミドルウェアスタック実装 |
| `Middleware\AddBusNameStampMiddleware` | BusNameStamp 自動付与 |
| `Middleware\AddMultisiteStampMiddleware` | MultisiteStamp 自動付与 |
| `Middleware\SendMessageMiddleware` | トランスポートへの送信 |
| `Middleware\HandleMessageMiddleware` | ハンドラー実行 |
| `Handler\HandlerLocatorInterface` | ハンドラーロケーターインターフェース |
| `Handler\HandlerLocator` | デフォルトハンドラーロケーター |
| `Handler\HandlerDescriptor` | ハンドラー記述子 |
| `Serializer\SerializerInterface` | シリアライザーインターフェース |
| `Serializer\JsonSerializer` | リフレクションベース JSON シリアライザー |
| `Test\TestMessageBus` | テスト用 MessageBus |

## 依存関係

### 必須
- **psr/log ^3.0** -- PSR-3 ロガーインターフェース

### 推奨
- **wppack/sqs-messenger** -- SQS トランスポート（非同期メッセージング）
