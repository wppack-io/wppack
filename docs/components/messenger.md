# Messenger コンポーネント

**パッケージ:** `wppack/messenger`
**名前空間:** `WpPack\Component\Messenger\`
**レイヤー:** Abstraction

Symfony Messenger ライクな非同期メッセージング基盤。SQS + Lambda (Bref) でメッセージを非同期処理します。

## インストール

```bash
composer require wppack/messenger
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
│  Middleware Chain                                    │
│  ├── LoggingMiddleware                              │
│  ├── StampMiddleware                                │
│  └── SendMessageMiddleware → SqsTransport           │
└────────────────────┬───────────────────────────────┘
                     ↓
┌─ Amazon SQS ───────────────────────────────────────┐
│  メッセージキュー                                    │
└────────────────────┬───────────────────────────────┘
                     ↓ Lambda トリガー
┌─ Lambda (Bref) ────────────────────────────────────┐
│  SqsEventHandler                                    │
│  ├── WordPress ブートストラップ                      │
│  ├── メッセージデシリアライズ                         │
│  └── #[AsMessageHandler] を実行                     │
└────────────────────────────────────────────────────┘
```

## メッセージ定義

メッセージクラスはプレーンな POPO（Plain Old PHP Object）/ DTO として定義します。特別なアトリビュートやインターフェースは不要です。

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

## メッセージハンドラー

`#[AsMessageHandler]` アトリビュートでハンドラーを定義します。

### 単一メッセージの処理

クラスレベルに `#[AsMessageHandler]` を付与し、`__invoke()` メソッドの引数型からメッセージ型が自動推論されます。

```php
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

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

### 複数メッセージの処理

一つのハンドラークラスで複数のメッセージ型を処理する場合は、メソッドレベルに `#[AsMessageHandler]` を付与します。クラスレベルのアトリビュートは不要です。

```php
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

final class NotificationHandler
{
    #[AsMessageHandler]
    public function handleEmail(SendEmailMessage $message): void { /* ... */ }

    #[AsMessageHandler]
    public function handleSms(SendSmsMessage $message): void { /* ... */ }
}
```

## MessageBus

メッセージのディスパッチを行います。

```php
use WpPack\Component\Messenger\MessageBus;

// メッセージをディスパッチ（SQS に送信）
$messageBus->dispatch(new SendEmailMessage(
    userId: 123,
    subject: 'Welcome!',
    body: 'Thanks for joining!',
));
```

## Envelope と Stamp

メッセージにメタデータを付与できます。

```php
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Stamp\DelayStamp;
use WpPack\Component\Messenger\Stamp\PriorityStamp;

// 5分後に実行
$messageBus->dispatch(new Envelope(
    new SendEmailMessage(userId: 123, subject: 'Reminder', body: '...'),
    stamps: [new DelayStamp(seconds: 300)],
));

// 優先度付き
$messageBus->dispatch(new Envelope(
    new UrgentMessage(),
    stamps: [new PriorityStamp(priority: 1)],
));
```

### 組み込み Stamp

| Stamp | 説明 |
|-------|------|
| `DelayStamp` | 指定秒数だけ配信を遅延 |
| `PriorityStamp` | メッセージの優先度 |
| `TransportStamp` | 送信先トランスポートの指定 |
| `SentStamp` | 送信済みマーカー（自動付与） |
| `ReceivedStamp` | 受信マーカー（自動付与） |
| `HandledStamp` | 処理済みマーカー（自動付与） |

## SqsTransport

Amazon SQS へのメッセージ送受信を行うトランスポート。

```php
use WpPack\Component\Messenger\Transport\SqsTransport;

$transport = new SqsTransport(
    sqsClient: $sqsClient,
    queueUrl: 'https://sqs.ap-northeast-1.amazonaws.com/123456789/wppack',
);
```

### 環境変数

```bash
SQS_QUEUE_URL=https://sqs.ap-northeast-1.amazonaws.com/123456789/wppack
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=ap-northeast-1
```

## Middleware

メッセージ処理パイプラインをカスタマイズできます。

```php
use WpPack\Component\Messenger\Middleware\MiddlewareInterface;
use WpPack\Component\Messenger\Middleware\StackInterface;
use WpPack\Component\Messenger\Envelope;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->logger->info('Dispatching {class}', [
            'class' => get_class($envelope->getMessage()),
        ]);

        $envelope = $stack->next()->handle($envelope, $stack);

        $this->logger->info('Dispatched {class}', [
            'class' => get_class($envelope->getMessage()),
        ]);

        return $envelope;
    }
}
```

```php
$messageBus = new MessageBus([
    new LoggingMiddleware($logger),
    new SendMessageMiddleware($transport),
]);
```

## Lambda ハンドラー (SqsEventHandler)

Bref Lambda 環境で SQS メッセージを受信し、WordPress をブートストラップしてハンドラーを実行します。

```php
use WpPack\Component\Messenger\Handler\SqsEventHandler;

// Lambda エントリポイント
return new SqsEventHandler(
    wordpressPath: '/var/task/wordpress',
    handlerLocator: $handlerLocator,
);
```

### WordPress ブートストラップ

Lambda 実行時に WordPress 環境を初期化します:

1. `wp-load.php` を読み込み
2. WordPress のプラグイン・テーマを初期化
3. DI コンテナからハンドラーを取得
4. メッセージをデシリアライズしてハンドラーに渡す

## テスト

`TestMessageBus` を使ってテスト時のメッセージ送信を検証できます。

```php
use WpPack\Component\Messenger\Test\TestMessageBus;

$testBus = new TestMessageBus();

$testBus->dispatch(new SendEmailMessage(
    userId: 123,
    subject: 'Test',
    body: 'Test body',
));

// アサーション
$testBus->assertDispatched(SendEmailMessage::class);
$testBus->assertDispatchedCount(1);
$testBus->assertDispatched(SendEmailMessage::class, function (SendEmailMessage $message): bool {
    return $message->userId === 123;
});
$testBus->assertNotDispatched(ProcessImageMessage::class);
```

## 主要クラス一覧

| クラス | 説明 |
|-------|------|
| `Attribute\AsMessageHandler` | ハンドラーマーカー |
| `MessageBus` | メッセージディスパッチャー |
| `MessageBusInterface` | MessageBus インターフェース |
| `Envelope` | メッセージ + Stamp のラッパー |
| `Stamp\DelayStamp` | 遅延配信 |
| `Stamp\PriorityStamp` | 優先度指定 |
| `Stamp\TransportStamp` | トランスポート指定 |
| `Transport\SqsTransport` | SQS トランスポート |
| `Transport\TransportInterface` | トランスポートインターフェース |
| `Middleware\MiddlewareInterface` | ミドルウェアインターフェース |
| `Handler\SqsEventHandler` | Lambda SQS ハンドラー |
| `Handler\HandlerLocatorInterface` | ハンドラーロケーター |
| `Serializer\MessageSerializer` | メッセージシリアライザー |
| `Test\TestMessageBus` | テスト用 MessageBus |
