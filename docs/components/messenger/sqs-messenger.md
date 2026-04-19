# SqsMessenger コンポーネント

**パッケージ:** `wppack/sqs-messenger`
**名前空間:** `WPPack\Component\Messenger\Bridge\Sqs\`
**レイヤー:** Abstraction

Messenger コンポーネントの Amazon SQS トランスポート実装。SQS キューへのメッセージ送信と、Lambda 環境での SQS イベント受信・処理を提供します。

## インストール

```bash
composer require wppack/sqs-messenger
```

## SqsTransport

`TransportInterface` の SQS 実装。メッセージを `JsonSerializer` でエンコードし、SQS キューに送信します。

```php
use AsyncAws\Sqs\SqsClient;
use WPPack\Component\Messenger\Bridge\Sqs\Transport\SqsTransport;
use WPPack\Component\Messenger\Serializer\JsonSerializer;

$transport = new SqsTransport(
    sqsClient: new SqsClient([
        'region' => 'ap-northeast-1',
    ]),
    serializer: new JsonSerializer(),
    queueUrl: 'https://sqs.ap-northeast-1.amazonaws.com/123456789012/wppack',
    name: 'sqs', // デフォルト: 'sqs'
);
```

### コンストラクタ

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `sqsClient` | `SqsClient` | — | async-aws SQS クライアント |
| `serializer` | `SerializerInterface` | — | メッセージシリアライザー |
| `queueUrl` | `string` | — | SQS キュー URL |
| `name` | `string` | `'sqs'` | トランスポート名 |

### 送信フロー

1. `SerializerInterface::encode()` で Envelope をエンコード
2. エンコード結果を JSON 化して `MessageBody` にセット
3. `DelayStamp` があれば `DelaySeconds` に変換
4. `SqsClient::sendMessage()` で送信
5. `SentStamp` を付与した Envelope を返す

### DelayStamp 対応

`DelayStamp` のミリ秒値を秒に変換（切り上げ）して SQS の `DelaySeconds` に設定します。SQS の上限は 900 秒（15 分）で、超過分はクランプされます。

```php
use WPPack\Component\Messenger\Stamp\DelayStamp;

// 5分後に実行
$messageBus->dispatch(
    new SendEmailMessage(userId: 123, subject: 'Reminder', body: '...'),
    [new DelayStamp(delayInMilliseconds: 300_000)],
);
```

| ミリ秒 | 変換後（秒） | 備考 |
|--------|------------|------|
| 5000 | 5 | そのまま変換 |
| 1500 | 2 | 切り上げ |
| 1_000_000 | 900 | 上限でクランプ |

## Lambda ハンドラー (SqsEventHandler)

Lambda 環境で SQS メッセージを受信し、WordPress をブートストラップしてハンドラーを実行します。

```php
use WPPack\Component\Messenger\Bridge\Sqs\Handler\SqsEventHandler;
use WPPack\Component\Messenger\Serializer\JsonSerializer;

// Lambda エントリポイント
return new SqsEventHandler(
    wordpressPath: '/var/task/wordpress',
    messageBus: $messageBus,
    serializer: new JsonSerializer(),
    logger: $logger, // オプション
);
```

### コンストラクタ

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `wordpressPath` | `string` | — | WordPress ルートディレクトリパス |
| `messageBus` | `MessageBusInterface` | — | メッセージバス |
| `serializer` | `SerializerInterface` | — | メッセージシリアライザー |
| `logger` | `?LoggerInterface` | `null` | PSR-3 ロガー |

### 処理フロー

1. **WordPress ブートストラップ**（初回のみ）— `wp-load.php` を読み込み
2. SQS イベントの `Records` を順次処理
3. 各レコードの `body` を JSON デコードし、`SerializerInterface::decode()` で Envelope に復元
4. `MultisiteStamp` があれば `switch_to_blog()` でブログを切り替え
5. `MessageBusInterface::dispatch()` で `ReceivedStamp('sqs')` 付きでディスパッチ
6. 処理完了後、マルチサイトの場合は `restore_current_blog()` で復帰

### batchItemFailures

SQS の partial batch failure を活用します。成功したメッセージはキューから削除され、失敗したメッセージのみ再試行されます。

```php
// 戻り値の形式
[
    'batchItemFailures' => [
        ['itemIdentifier' => 'failed-message-id-1'],
        ['itemIdentifier' => 'failed-message-id-2'],
    ],
]
```

### マルチサイト対応

`AddMultisiteStampMiddleware` が送信時に `MultisiteStamp` を自動付与します。`SqsEventHandler` は受信時にこの Stamp を読み取り、`switch_to_blog()` でコンテキストを切り替えます。

```
送信側: dispatch() → AddMultisiteStampMiddleware → MultisiteStamp(blogId: 3)
         ↓ SQS
受信側: SqsEventHandler → switch_to_blog(3) → dispatch() → handler → restore_current_blog()
```

## 環境変数・設定

### SQS 設定

```bash
SQS_QUEUE_URL=https://sqs.ap-northeast-1.amazonaws.com/123456789012/wppack
```

### AWS 認証情報

```bash
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=ap-northeast-1
```

## 認証方法

### IAM ロール（推奨）

Lambda 実行ロールや EC2 Instance Profile を使用する場合、環境変数の設定は不要です。async-aws が自動的に認証情報を検出します。

1. 環境変数（`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`）
2. AWS 認証ファイル（`~/.aws/credentials`）
3. ECS タスクロール
4. EC2 Instance Profile / Lambda 実行ロール

### アクセスキー

```php
$sqsClient = new SqsClient([
    'accessKeyId' => 'YOUR_ACCESS_KEY',
    'accessKeySecret' => 'YOUR_SECRET_KEY',
    'region' => 'ap-northeast-1',
]);
```

### STS 一時認証

```php
$sqsClient = new SqsClient([
    'accessKeyId' => 'YOUR_ACCESS_KEY',
    'accessKeySecret' => 'YOUR_SECRET_KEY',
    'sessionToken' => 'YOUR_SESSION_TOKEN',
    'region' => 'ap-northeast-1',
]);
```

## AWS IAM ポリシー

SQS メッセージング に必要な最小権限:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "sqs:SendMessage"
            ],
            "Resource": "arn:aws:sqs:ap-northeast-1:123456789012:wppack"
        }
    ]
}
```

Lambda 側（受信・削除）:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "sqs:ReceiveMessage",
                "sqs:DeleteMessage",
                "sqs:GetQueueAttributes"
            ],
            "Resource": "arn:aws:sqs:ap-northeast-1:123456789012:wppack"
        }
    ]
}
```

## アーキテクチャ

```
┌─ WordPress ────────────────────────────────────────┐
│  $messageBus->dispatch(new SendEmailMessage(...))  │
│  ↓ SendMessageMiddleware                           │
│  SqsTransport::send()                              │
└────────────────────┬───────────────────────────────┘
                     ↓ SQS SendMessage
┌─ Amazon SQS ───────────────────────────────────────┐
│  メッセージキュー                                    │
└────────────────────┬───────────────────────────────┘
                     ↓ Lambda トリガー
┌─ Lambda (Bref) ────────────────────────────────────┐
│  SqsEventHandler                                    │
│  ├── WordPress ブートストラップ（初回のみ）           │
│  ├── メッセージデシリアライズ                         │
│  ├── マルチサイト切り替え                             │
│  └── MessageBus::dispatch() → Handler 実行          │
│  ↓                                                  │
│  batchItemFailures レスポンス                        │
└────────────────────────────────────────────────────┘
```

## クイックスタート

```php
use AsyncAws\Sqs\SqsClient;
use WPPack\Component\Messenger\Bridge\Sqs\Transport\SqsTransport;
use WPPack\Component\Messenger\MessageBus;
use WPPack\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use WPPack\Component\Messenger\Middleware\AddMultisiteStampMiddleware;
use WPPack\Component\Messenger\Middleware\HandleMessageMiddleware;
use WPPack\Component\Messenger\Middleware\SendMessageMiddleware;
use WPPack\Component\Messenger\Serializer\JsonSerializer;

// トランスポート設定
$sqsTransport = new SqsTransport(
    sqsClient: new SqsClient(['region' => 'ap-northeast-1']),
    serializer: new JsonSerializer(),
    queueUrl: $_ENV['SQS_QUEUE_URL'],
);

// MessageBus 構築
$messageBus = new MessageBus([
    new AddBusNameStampMiddleware(),
    new AddMultisiteStampMiddleware(),
    new SendMessageMiddleware(['sqs' => $sqsTransport]),
    new HandleMessageMiddleware($handlerLocator),
]);

// メッセージをディスパッチ（SQS に送信）
$messageBus->dispatch(
    new SendEmailMessage(userId: 123, subject: 'Welcome!', body: 'Hello!'),
    [new \WPPack\Component\Messenger\Stamp\TransportStamp('sqs')],
);
```

## クラス一覧

| クラス | 説明 |
|-------|------|
| `Transport\SqsTransport` | SQS トランスポート |
| `Handler\SqsEventHandler` | Lambda SQS イベントハンドラー |

## 依存関係

### 必須
- **wppack/messenger** -- メッセージバス基盤
- **async-aws/sqs ^2.0** -- SQS API クライアント
