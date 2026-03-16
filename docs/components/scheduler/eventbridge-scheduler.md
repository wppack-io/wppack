# EventBridgeScheduler コンポーネント

**パッケージ:** `wppack/eventbridge-scheduler`
**名前空間:** `WpPack\Component\Scheduler\Bridge\EventBridge\`
**レイヤー:** Feature

Scheduler コンポーネントの Amazon EventBridge Scheduler バックエンド実装。WP-Cron に依存しない高信頼性のスケジューリングを提供し、EventBridge → SQS → Lambda の連携で正確なタイミングでのメッセージ処理を実現します。

## インストール

```bash
composer require wppack/eventbridge-scheduler
```

## EventBridge Scheduler とは

[Amazon EventBridge Scheduler](https://docs.aws.amazon.com/scheduler/latest/UserGuide/what-is-scheduler.html) は、AWS のフルマネージドなスケジューリングサービスです。

WP-Cron との比較:

| | WP-Cron | EventBridge Scheduler |
|---|---------|----------------------|
| トリガー方式 | ページアクセス時に発火 | AWS がサーバーサイドで発火 |
| 精度 | アクセスがなければ遅延 | 秒単位の精度 |
| 信頼性 | アクセス依存 | AWS SLA 保証 |
| サーバーレス | 非対応 | ネイティブ対応 |
| スケーラビリティ | 単一プロセス | 自動スケーリング |
| コスト | 無料（サーバー負荷） | スケジュールあたりの課金 |

## アーキテクチャ

```
┌─ WordPress ────────────────────────────────────────┐
│  EventBridgeScheduler::schedule($id, $message)     │
│  ↓                                                  │
│  EventBridgeScheduleFactory: Trigger → Expression   │
│  SqsPayloadFactory: Message → SQS ペイロード         │
│  ↓                                                  │
│  SchedulerClient::createSchedule()                  │
└────────────────────┬───────────────────────────────┘
                     ↓
┌─ EventBridge Scheduler ───────────────────────────┐
│  rate() / cron() / at() スケジュール               │
└────────────────────┬───────────────────────────────┘
                     ↓ スケジュール実行時
┌─ Amazon SQS ───────────────────────────────────────┐
│  メッセージキュー                                    │
└────────────────────┬───────────────────────────────┘
                     ↓ Lambda トリガー
┌─ Lambda (Bref) ────────────────────────────────────┐
│  SqsEventHandler → MessageBus → Handler            │
└────────────────────────────────────────────────────┘
```

## EventBridgeScheduler

`SchedulerInterface` の EventBridge 実装。`schedule()` / `unschedule()` / `has()` で EventBridge スケジュールを CRUD 管理します。

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduler;

$scheduler = new EventBridgeScheduler(
    schedulerClient: $schedulerClient,        // async-aws SchedulerClient
    scheduleFactory: $scheduleFactory,        // EventBridgeScheduleFactory
    payloadFactory: $payloadFactory,          // SqsPayloadFactory
    groupName: 'wppack-default',              // スケジュールグループ名
    targetArn: 'arn:aws:sqs:...:wppack',     // SQS ターゲット ARN
    roleArn: 'arn:aws:iam::...:role/...',    // IAM ロール ARN
);
```

### schedule()

`ScheduledMessage` の Trigger を EventBridge 式に変換し、SQS ターゲットへのスケジュールを作成します。

```php
use WpPack\Component\Scheduler\Message\RecurringMessage;

$scheduler->schedule(
    'daily-cleanup',
    RecurringMessage::schedule('daily', new CleanupMessage()),
);
```

### unschedule()

```php
$scheduler->unschedule('daily-cleanup');
```

### has()

```php
if ($scheduler->has('daily-cleanup')) {
    // スケジュールが存在する
}
```

## EventBridgeScheduleFactory

`TriggerInterface` を EventBridge Scheduler の式に変換します。

### Trigger → EventBridge 変換表

| Trigger | EventBridge 式 | 例 |
|---------|---------------|-----|
| `IntervalTrigger` | `rate(N minutes)` / `rate(N hours)` | `rate(5 minutes)` |
| `WpCronScheduleTrigger` | `rate(N hours)` | `rate(24 hours)` |
| `CronExpressionTrigger` | `cron(expr)` | `cron(0 3 * * ? *)` |
| `DateTimeTrigger` | `at(datetime)` | `at(2024-12-31T23:59:59)` |
| `JitterTrigger` | 内部 Trigger に委譲 | — |

### rate 式の単位選択

| インターバル | 式 |
|------------|-----|
| 60 秒未満 | `rate(N minutes)` — 最小 1 分 |
| 60 〜 3599 秒 | `rate(N minutes)` |
| 3600 秒以上 | `rate(N hours)` |

### cron 式の変換

WordPress の cron 式（5 フィールド）を EventBridge の cron 式（6 フィールド、`?` 対応）に変換します。

```
WordPress:    */15 * * * *
EventBridge:  cron(*/15 * * * ? *)
```

## マルチサイト対応

### MultisiteScheduleGroupResolver

マルチサイト環境では、ブログごとにスケジュールグループを分離します。

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\MultisiteScheduleGroupResolver;

$resolver = new MultisiteScheduleGroupResolver(
    prefix: 'wppack', // デフォルト: 'wppack'
);

$groupName = $resolver->resolve();           // 現在のブログ
$groupName = $resolver->resolveForSite(3);   // 特定のブログ
```

### グループ名テーブル

| ブログ ID | グループ名 |
|----------|-----------|
| 1（メイン） | `wppack-site-1` |
| 2 | `wppack-site-2` |
| 3 | `wppack-site-3` |

## SqsPayloadFactory

EventBridge から SQS に渡すペイロードを構築します。`JsonSerializer` と同じフォーマットでメッセージをエンコードします。

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;
use WpPack\Component\Messenger\Serializer\JsonSerializer;

$payloadFactory = new SqsPayloadFactory(
    serializer: new JsonSerializer(),
);

$payload = $payloadFactory->create($scheduledMessage);
```

### ペイロード構造

EventBridge が SQS に送信するペイロードは、`SqsEventHandler` が処理できる形式（`JsonSerializer` のエンコード結果）です。

```json
{
    "headers": {
        "type": "App\\CleanupMessage",
        "stamps": {}
    },
    "body": "{\"retentionDays\":30}"
}
```

## 設定

### 環境変数

```bash
# SQS キュー URL（SqsTransport 用）
SQS_QUEUE_URL=https://sqs.ap-northeast-1.amazonaws.com/123456789012/wppack

# AWS リージョン
AWS_REGION=ap-northeast-1
```

### wp-config.php 定数

```php
// EventBridge スケジュールグループ名
define('WPPACK_SCHEDULER_GROUP', 'wppack-production');

// SQS ターゲット ARN
define('WPPACK_SCHEDULER_TARGET_ARN', 'arn:aws:sqs:ap-northeast-1:123456789012:wppack');

// IAM ロール ARN（EventBridge が SQS に送信するため）
define('WPPACK_SCHEDULER_ROLE_ARN', 'arn:aws:iam::123456789012:role/EventBridgeSchedulerRole');
```

## AWS IAM ポリシー

### WordPress アプリケーション用

EventBridge スケジュールの作成・更新・削除:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "scheduler:CreateSchedule",
                "scheduler:UpdateSchedule",
                "scheduler:DeleteSchedule",
                "scheduler:GetSchedule"
            ],
            "Resource": "arn:aws:scheduler:ap-northeast-1:123456789012:schedule/wppack-*/*"
        },
        {
            "Effect": "Allow",
            "Action": "iam:PassRole",
            "Resource": "arn:aws:iam::123456789012:role/EventBridgeSchedulerRole",
            "Condition": {
                "StringEquals": {
                    "iam:PassedToService": "scheduler.amazonaws.com"
                }
            }
        }
    ]
}
```

### EventBridge 実行ロール用

EventBridge が SQS にメッセージを送信するロール:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "sqs:SendMessage",
            "Resource": "arn:aws:sqs:ap-northeast-1:123456789012:wppack"
        }
    ]
}
```

## クイックスタート

```php
use AsyncAws\Scheduler\SchedulerClient;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduler;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;
use WpPack\Component\Scheduler\Message\RecurringMessage;
use WpPack\Component\Messenger\Serializer\JsonSerializer;

// EventBridge Scheduler のセットアップ
$schedulerClient = new SchedulerClient(['region' => 'ap-northeast-1']);
$serializer = new JsonSerializer();

$scheduler = new EventBridgeScheduler(
    schedulerClient: $schedulerClient,
    scheduleFactory: new EventBridgeScheduleFactory(),
    payloadFactory: new SqsPayloadFactory($serializer),
    groupName: WPPACK_SCHEDULER_GROUP,
    targetArn: WPPACK_SCHEDULER_TARGET_ARN,
    roleArn: WPPACK_SCHEDULER_ROLE_ARN,
);

// スケジュール登録
$scheduler->schedule(
    'daily-cleanup',
    RecurringMessage::schedule('daily', new CleanupMessage()),
);

$scheduler->schedule(
    'hourly-sync',
    RecurringMessage::every('1 hour', new SyncMessage()),
);

// スケジュール確認
$scheduler->has('daily-cleanup'); // true

// スケジュール解除
$scheduler->unschedule('hourly-sync');
```

## クラス一覧

| クラス | 説明 |
|-------|------|
| `EventBridgeScheduler` | `SchedulerInterface` の EventBridge 実装 |
| `EventBridgeScheduleFactory` | Trigger → EventBridge 式変換 |
| `MultisiteScheduleGroupResolver` | マルチサイト対応グループ名解決 |
| `SqsPayloadFactory` | SQS ペイロード構築 |

## 依存関係

### 必須
- **wppack/scheduler** -- Scheduler 基盤（`SchedulerInterface`, Trigger, Message）
- **async-aws/scheduler ^1.0** -- EventBridge Scheduler API クライアント
