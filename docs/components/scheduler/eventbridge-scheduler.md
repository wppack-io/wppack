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

### SchedulerInterface 経由（直接利用）

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

### WP-Cron インターセプション（Full Intercept + Local State）

既存の WP-Cron API をそのまま使いながら、バックエンドを EventBridge に差し替えます。Cavalcade（humanmade/Cavalcade）と同じアプローチです。

```
wp_schedule_event('hourly', 'my_hook', $args)
  ↓ pre_schedule_event フィルター（WpCronInterceptor がインターセプト）
  ↓ wp_options.cron に書き込み（DB first — 必ず永続化）
  ↓ EventBridge に rate(1 hours) スケジュール作成（best-effort — 失敗時はログ出力して継続）
  ↓ return true（WP は正常にスケジュールされたと認識）

EventBridge が指定時刻に発火
  ↓ SQS に WpCronMessage ペイロード送信
  ↓ Lambda SqsEventHandler（既存、変更不要）
  ↓ MessageBus が WpCronMessage をディスパッチ
  ↓ WpCronMessageHandler:
     - do_action_ref_array($hook, $args) ← 既存コールバックが動く
     - wp_options.cron の次回実行時刻を更新
```

**設計方針:**
- **Write**: `pre_*` フィルターでインターセプト → DB first（`wp_options.cron` に必ず永続化）+ EventBridge best-effort（失敗時はログ出力して継続）
- **Read**: `wp_options.cron` から直接読み取り（高速、管理ツール互換）
- **Execution**: `pre_get_ready_cron_jobs` で空配列返却 → ローカル実行を無効化、EventBridge が sole executor
- **Recovery**: EventBridge 作成失敗時は `sync()` で DB 状態をもとに一括リカバリ可能

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

### createScheduleRaw()

Trigger を経由せず直接 EventBridge スケジュールを作成します。`WpCronInterceptor` が内部的に使用します。

```php
$scheduler->createScheduleRaw(
    scheduleId: 'wpcron_my_hook_abcdef12',
    expression: 'rate(1 hours)',
    payload: '{"headers":{"type":"..."},"body":"..."}',
    autoDelete: false,
);
```

## WP-Cron インターセプション

### WpCronInterceptor

`pre_*` フィルターで WP-Cron API をインターセプトし、EventBridge にスケジュールを作成します。

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\Interceptor\WpCronInterceptor;

$interceptor = new WpCronInterceptor(
    scheduler: $eventBridgeScheduler,
    scheduleFactory: new EventBridgeScheduleFactory(),
    payloadFactory: new SqsPayloadFactory(),
    logger: $logger,  // ?LoggerInterface — EventBridge 失敗時のログ出力
);

$interceptor->register();   // フィルター登録
$interceptor->unregister(); // フィルター解除
```

#### インターセプトするフィルター

| WordPress フィルター | メソッド | 動作 |
|---------------------|---------|------|
| `pre_schedule_event` | `onPreScheduleEvent()` | DB first: ローカル cron 更新 → best-effort: EventBridge スケジュール作成 → `true` |
| `pre_reschedule_event` | `onPreRescheduleEvent()` | ローカル cron のみ更新（rate() 自動繰り返し）→ `true` |
| `pre_unschedule_event` | `onPreUnscheduleEvent()` | DB first: ローカル cron 削除 → best-effort: EventBridge スケジュール削除 → `true` |
| `pre_clear_scheduled_hook` | `onPreClearScheduledHook()` | hook+args の全スケジュール削除（EventBridge は best-effort）→ 削除件数 |
| `pre_unschedule_hook` | `onPreUnscheduleHook()` | hook の全スケジュール削除（EventBridge は best-effort）→ 削除件数 |
| `pre_get_ready_cron_jobs` | `onPreGetReadyCronJobs()` | `[]` 返却（ローカル実行無効化） |

`register()` 内で `DISABLE_WP_CRON` を定義し、`spawn_cron()` を完全に無効化します。

### WpCronMessageHandler

Lambda 上で WP-Cron メッセージを処理するハンドラー。`#[AsMessageHandler]` アトリビュート付き。

```php
// 自動的に MessageBus 経由で呼び出される
// 1. do_action_ref_array($hook, $args) — 既存のコールバックが動作
// 2. 繰り返し: wp_options.cron の次回実行時刻を更新
// 3. 単発: wp_options.cron から削除
```

### WpCronMessage

WP-Cron イベントを表現する POPO メッセージ。`JsonSerializer` でシリアライズ/デシリアライズ可能。

```php
use WpPack\Component\Scheduler\Message\WpCronMessage;

$message = new WpCronMessage(
    hook: 'my_cron_hook',
    args: ['arg1', 'arg2'],
    schedule: 'hourly',  // 繰り返し: schedule 名、単発: false
    timestamp: 1700000000,
);
```

### ScheduleIdGenerator

WP-Cron の `(hook, args, timestamp)` から EventBridge スケジュール名（最大 64 文字）を決定的に生成します。

| 種類 | フォーマット | 例 |
|------|------------|-----|
| 繰り返し | `wpcron_{hook}_{md5(args)[0:8]}` | `wpcron_my_hook_a1b2c3d4` |
| 単発 | `wpcron_{hook}_{md5(args)[0:8]}_{timestamp}` | `wpcron_my_hook_a1b2c3d4_1700000000` |
| 64 文字超過 | `wpcron_{md5(full_id)[0:32]}` | `wpcron_abcdef1234567890...` |
| Action Scheduler | `as_{md5(hook+args)[0:16]}_{actionId}` | `as_abcdef1234567890_42` |

### WpCronCollector

初期マイグレーション用。`_get_cron_array()` から全 WP-Cron イベントを収集します。

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\Collector\WpCronCollector;

$collector = new WpCronCollector();
$events = $collector->collect();
// → list<array{hook, args, schedule, interval, timestamp}>
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
| 60 秒未満 | `rate(1 minute)` — EventBridge 最小粒度 |
| 60 〜 3599 秒 | `rate(N minutes)` |
| 3600 秒以上 | `rate(N hours)` |

### cron 式の変換

WordPress の cron 式（5 フィールド）を EventBridge の cron 式（6 フィールド、`?` 対応）に変換します。

```
WordPress:    */15 * * * *
EventBridge:  cron(*/15 * * * ? *)
```

### WP-Cron interval からの直接変換

`fromWpCronInterval()` で WP-Cron の interval（秒数）から直接 rate 式に変換できます。

```php
$factory = new EventBridgeScheduleFactory();
$factory->fromWpCronInterval(3600);    // → ['expression' => 'rate(1 hour)', 'type' => 'rate']
$factory->fromTimestamp(1700000000);   // → ['expression' => 'at(...)', 'type' => 'at']
```

## マルチサイト対応

### MultisiteScheduleGroupResolver

マルチサイト環境では、ブログごとにスケジュールグループを分離します。

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\MultisiteScheduleGroupResolver;

$resolver = new MultisiteScheduleGroupResolver();

$groupName = $resolver->resolve();     // 現在のブログ
$groupName = $resolver->resolve(3);    // 特定のブログ
```

### グループ名テーブル

| ブログ ID | グループ名 |
|----------|-----------|
| 1（メイン） | `wppack` |
| 2 | `wppack_2` |
| 3 | `wppack_3` |

## SqsPayloadFactory

EventBridge から SQS に渡すペイロードを構築します。`JsonSerializer` と同じフォーマットでメッセージをエンコードし、既存の `SqsEventHandler` がそのまま処理可能です。

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;

$factory = new SqsPayloadFactory();

// 任意のメッセージオブジェクト + スタンプ
$payload = $factory->create($message, $stamps);

// WP-Cron イベントから直接生成
$payload = $factory->createForWpCronEvent(
    hook: 'my_hook',
    args: ['arg1'],
    schedule: 'hourly',
    timestamp: 1700000000,
);
```

### ペイロード構造

EventBridge が SQS に送信するペイロードは、`SqsEventHandler` が処理できる形式（`JsonSerializer` のエンコード結果）です。

```json
{
    "headers": {
        "type": "WpPack\\Component\\Scheduler\\Message\\WpCronMessage",
        "stamps": {}
    },
    "body": "{\"hook\":\"my_hook\",\"args\":[\"arg1\"],\"schedule\":\"hourly\",\"timestamp\":1700000000}"
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

### SchedulerInterface 経由

```php
use AsyncAws\Scheduler\SchedulerClient;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduler;
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;
use WpPack\Component\Scheduler\Message\RecurringMessage;

$scheduler = new EventBridgeScheduler(
    schedulerClient: new SchedulerClient(['region' => 'ap-northeast-1']),
    scheduleFactory: new EventBridgeScheduleFactory(),
    payloadFactory: new SqsPayloadFactory(),
    groupName: WPPACK_SCHEDULER_GROUP,
    targetArn: WPPACK_SCHEDULER_TARGET_ARN,
    roleArn: WPPACK_SCHEDULER_ROLE_ARN,
);

$scheduler->schedule(
    'daily-cleanup',
    RecurringMessage::schedule('daily', new CleanupMessage()),
);
```

### WP-Cron インターセプション

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\Interceptor\WpCronInterceptor;

// SchedulerPlugin が自動的に register() を呼ぶ
$interceptor = new WpCronInterceptor(
    scheduler: $eventBridgeScheduler,
    scheduleFactory: new EventBridgeScheduleFactory(),
    payloadFactory: new SqsPayloadFactory(),
    logger: $logger,  // ?LoggerInterface
);
$interceptor->register();

// 以後、既存の WP-Cron API がそのまま使える
wp_schedule_event(time(), 'hourly', 'my_hook', ['arg1']);
// → EventBridge に rate(1 hours) スケジュールが作成される
```

## Action Scheduler インターセプション

### アーキテクチャ: Post-Store Hook + Queue Runner Disable

WP-Cron とは異なり、Action Scheduler（AS）には `pre_schedule_event` のような統一的な pre-filter がありません。代わりに、`action_scheduler_stored_action`（post-store）フックで AS がアクションを DB に保存した後に EventBridge スケジュールを作成します。AS のローカルストアはそのまま残し、管理画面の互換性を維持します。

```
as_schedule_recurring_action($timestamp, 3600, 'my_hook', $args)
  ↓ AS がアクションを DB に保存（ステータス: pending）
  ↓ action_scheduler_stored_action フック発火
  ↓ ActionSchedulerInterceptor が EventBridge に rate(1 hours) スケジュール作成（best-effort）
  ↓ AS queue runner は無効化済み（ローカル実行なし）

EventBridge が指定時刻に発火
  ↓ SQS に ActionSchedulerMessage ペイロード送信
  ↓ Lambda SqsEventHandler → MessageBus → ActionSchedulerMessageHandler
  ↓ do_action_ref_array($hook, $args)
  ↓ AS ストアのステータスを complete に更新
```

### ActionSchedulerInterceptor

Post-store/cancel フックで AS ライフサイクルをインターセプトし、EventBridge にスケジュールを作成します。

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\Interceptor\ActionSchedulerInterceptor;

$interceptor = new ActionSchedulerInterceptor(
    scheduler: $eventBridgeScheduler,
    scheduleFactory: new EventBridgeScheduleFactory(),
    payloadFactory: new SqsPayloadFactory(),
    logger: $logger,  // ?LoggerInterface — EventBridge 失敗時のログ出力
);

$interceptor->register();   // フック登録
$interceptor->unregister(); // フック解除
```

#### インターセプトするフック

| AS フック | メソッド | 動作 |
|----------|---------|------|
| `action_scheduler_stored_action` | `onStoredAction()` | AS ストアからアクション取得 → EventBridge スケジュール作成（best-effort） |
| `action_scheduler_canceled_action` | `onCanceledAction()` | EventBridge スケジュール削除（best-effort） |
| `action_scheduler_queue_runner_concurrent_batches` | `onConcurrentBatches()` | `0` 返却（queue runner 無効化） |

#### AS スケジュール型 → EventBridge 式変換

| AS スケジュール型 | EventBridge 式 | autoDelete |
|-----------------|---------------|------------|
| `ActionScheduler_NullSchedule`（async） | `at(now)` | `true` |
| `ActionScheduler_SimpleSchedule`（single） | `at(scheduled_date)` | `true` |
| `ActionScheduler_IntervalSchedule`（recurring） | `rate(interval)` | `false` |
| `ActionScheduler_CronSchedule`（cron） | `cron(expression)` | `false` |

### ActionSchedulerMessageHandler

Lambda 上で Action Scheduler メッセージを処理するハンドラー。`#[AsMessageHandler]` アトリビュート付き。

```php
// 自動的に MessageBus 経由で呼び出される
// 1. do_action_ref_array($hook, $args) — 既存のコールバックが動作
// 2. AS ストアのステータスを complete に更新
//    (EventBridge の rate()/cron() が繰り返しを担当、AS は自動リスケジュールしない)
```

### ActionSchedulerCollector

初期マイグレーション用。AS ストアから pending アクションを収集します。

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\Collector\ActionSchedulerCollector;

$collector = new ActionSchedulerCollector();
$actions = $collector->collect();
// → list<array{hook, args, group, actionId, scheduleType, interval, cronExpression, scheduledDate}>
```

### ActionSchedulerMessage

Action Scheduler アクションを表現する POPO メッセージ。

```php
use WpPack\Component\Scheduler\Message\ActionSchedulerMessage;

$message = new ActionSchedulerMessage(
    hook: 'my_as_hook',
    args: ['arg1', 'arg2'],
    group: 'my-group',
    actionId: 42,
);
```

## エラーハンドリングとリカバリ

### DB 永続化保証

両 Interceptor は **DB first** の原則に従います:

- **WpCronInterceptor**: `wp_options.cron` への書き込みを最優先で実行し、EventBridge スケジュール操作は best-effort で行います
- **ActionSchedulerInterceptor**: AS が DB にアクションを保存した後の post-store フックで動作するため、DB 永続化は AS 側で保証済みです。EventBridge スケジュール操作は best-effort です

EventBridge 操作（作成・削除）が失敗しても、DB 側の状態は正常に保たれます。これにより、管理画面でのスケジュール表示やローカル状態の整合性が常に維持されます。

### EventBridge 失敗時の動作

EventBridge API の呼び出しが失敗した場合:

1. `try/catch` で例外をキャッチ
2. `LoggerInterface` 経由でエラーログを出力（Logger が注入されている場合）
3. 処理を継続（DB 操作は成功しているため、WordPress 側の動作に影響なし）

```php
// EventBridge 失敗時のログ出力例
// [ERROR] Failed to create EventBridge schedule for WP-Cron event "my_hook": AccessDeniedException
```

### sync() によるリカバリ

EventBridge スケジュールの作成に失敗した場合、`sync()` メソッドで DB 状態をもとに一括リカバリできます:

```php
// WP-Cron イベントの一括同期
$synced = $wpCronInterceptor->sync();

// Action Scheduler アクションの一括同期
$synced = $asInterceptor->sync();
```

`sync()` は per-event で `try/catch` を行い、個別のイベントが失敗しても残りの同期を継続します。プラグインの有効化時や WP-CLI コマンド（`wp wppack scheduler sync`）で実行することを想定しています。

### LoggerInterface の注入

両 Interceptor のコンストラクタに `?LoggerInterface $logger = null` を渡すことで、EventBridge 操作失敗時のエラーログを取得できます。Logger が `null` の場合、エラーは静かに無視されます。

```php
use Psr\Log\LoggerInterface;

// Logger あり — エラーをログに記録
$interceptor = new WpCronInterceptor(
    scheduler: $scheduler,
    scheduleFactory: $scheduleFactory,
    payloadFactory: $payloadFactory,
    logger: $logger,
);

// Logger なし — エラーは静かに無視（DB 永続化は保証）
$interceptor = new WpCronInterceptor(
    scheduler: $scheduler,
    scheduleFactory: $scheduleFactory,
    payloadFactory: $payloadFactory,
);
```

## クラス一覧

| クラス | 説明 |
|-------|------|
| `EventBridgeScheduler` | `SchedulerInterface` の EventBridge 実装 |
| `EventBridgeScheduleFactory` | Trigger → EventBridge 式変換 |
| `ScheduleIdGenerator` | WP-Cron / AS → スケジュール ID の決定的マッピング |
| `MultisiteScheduleGroupResolver` | マルチサイト対応グループ名解決 |
| `SqsPayloadFactory` | SQS ペイロード構築 |
| `WpCronInterceptor` | WP-Cron API の EventBridge バックエンド差し替え |
| `WpCronMessageHandler` | Lambda での WP-Cron メッセージ処理 |
| `WpCronCollector` | 既存 WP-Cron イベントの収集（マイグレーション用） |
| `ActionSchedulerInterceptor` | Action Scheduler の EventBridge バックエンド差し替え |
| `ActionSchedulerMessageHandler` | Lambda での AS メッセージ処理 |
| `ActionSchedulerCollector` | 既存 AS アクションの収集（マイグレーション用） |
| `WpCronMessage` | WP-Cron イベントを表現するメッセージ |
| `ActionSchedulerMessage` | Action Scheduler アクションを表現するメッセージ |
| `EventBridgeException` | EventBridge 操作エラー |

## 依存関係

### 必須
- **wppack/scheduler** -- Scheduler 基盤（`SchedulerInterface`, Trigger, Message）
- **wppack/messenger** -- メッセージングバス（`AsMessageHandler`, `JsonSerializer`）
- **async-aws/scheduler ^1.0** -- EventBridge Scheduler API クライアント
