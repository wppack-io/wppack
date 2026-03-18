# Scheduler コンポーネント

**パッケージ:** `wppack/scheduler`
**名前空間:** `WpPack\Component\Scheduler\`
**レイヤー:** Feature

Trigger ベースのタスクスケジューラー。WordPress の `wp_schedule_event()` / `wp_schedule_single_event()` をアトリビュートベースのモダンなインターフェースでラップし、5 種類の Trigger による柔軟なスケジュール定義と、複数バックエンド（WP-Cron / Action Scheduler / EventBridge）の切り替えをサポートします。

## インストール

```bash
composer require wppack/scheduler
```

## このコンポーネントの機能

- **Trigger システム** — `TriggerInterface` による統一的なスケジュール定義（cron 式、インターバル、WP-Cron 名前付きスケジュール、日時指定、ジッター付き）
- **RecurringMessage / OneTimeMessage** — 繰り返し実行と単発実行の宣言的なメッセージ定義
- **`#[AsSchedule]` + `ScheduleProviderInterface`** — アトリビュートベースのスケジュール定義
- **SchedulerInterface** — バックエンド切り替え可能な CRUD API（WP-Cron / Action Scheduler / EventBridge / Null）
- **Messenger 連携** — `wppack/messenger` と組み合わせた非同期メッセージ処理
- **Named Hook アトリビュート** — cron_schedules、pre_schedule_event 等の WordPress フック

## 基本コンセプト

### Before（従来の WordPress）

```php
// WP-Cron
register_activation_hook(__FILE__, 'myplugin_activation');
function myplugin_activation() {
    if (!wp_next_scheduled('myplugin_cleanup_hook')) {
        wp_schedule_event(time(), 'daily', 'myplugin_cleanup_hook');
    }
}

add_action('myplugin_cleanup_hook', 'myplugin_cleanup_old_posts');

function myplugin_cleanup_old_posts() {
    $posts = get_posts([
        'post_status' => 'draft',
        'date_query' => [['before' => '30 days ago']],
        'posts_per_page' => -1,
    ]);

    foreach ($posts as $post) {
        wp_delete_post($post->ID, true);
    }
}

register_deactivation_hook(__FILE__, 'myplugin_deactivation');
function myplugin_deactivation() {
    $timestamp = wp_next_scheduled('myplugin_cleanup_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'myplugin_cleanup_hook');
    }
}
```

### After（WpPack）

```php
use WpPack\Component\Scheduler\Attribute\AsSchedule;
use WpPack\Component\Scheduler\ScheduleProviderInterface;
use WpPack\Component\Scheduler\Schedule;
use WpPack\Component\Scheduler\Message\RecurringMessage;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

// メッセージクラス（プレーン POPO）
final readonly class CleanupOldPostsMessage
{
    public function __construct(
        public int $retentionDays = 30,
    ) {}
}

// スケジュール定義
#[AsSchedule]
final class CleanupScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::schedule('daily', new CleanupOldPostsMessage()));
    }
}

// メッセージハンドラー
#[AsMessageHandler]
final class CleanupOldPostsHandler
{
    public function __invoke(CleanupOldPostsMessage $message): void
    {
        $posts = get_posts([
            'post_status' => 'draft',
            'date_query' => [['before' => "{$message->retentionDays} days ago"]],
            'posts_per_page' => -1,
        ]);

        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
    }
}
```

## アーキテクチャ

```
┌─ Schedule 定義 ────────────────────────────────────┐
│                                                    │
│  #[AsSchedule]                                     │
│  class MyScheduleProvider                          │
│      implements ScheduleProviderInterface           │
│      getSchedule(): Schedule                       │
│                                                    │
└────────────────────┬───────────────────────────────┘
                     ↓ スケジュール収集
┌─ SchedulerInterface ──────────────────────────────┐
│  schedule($id, $message)                           │
│  ├── WpCronScheduler（WP-Cron）                    │
│  ├── ActionSchedulerScheduler（Action Scheduler）  │
│  ├── EventBridgeScheduler（AWS EventBridge）       │
│  └── NullScheduler（テスト用）                      │
└────────────────────┬───────────────────────────────┘
                     ↓ スケジュール実行時
┌─ wppack/messenger ─────────────────────────────────┐
│  MessageBus::dispatch() → Handler                  │
└────────────────────────────────────────────────────┘
```

## Trigger システム

`TriggerInterface` はスケジュールの「いつ実行するか」を定義する統一インターフェースです。

```php
namespace WpPack\Component\Scheduler\Trigger;

interface TriggerInterface extends \Stringable
{
    public function getNextRunDate(\DateTimeImmutable $now, ?\DateTimeImmutable $lastRun = null): ?\DateTimeImmutable;
    public function getIntervalInSeconds(): ?int;
    public function __toString(): string;
}
```

### 5 つの Trigger 実装

#### CronExpressionTrigger

cron 式（`* * * * *` 形式）によるスケジュール。`dragonmantank/cron-expression` を使用します。

```php
use WpPack\Component\Scheduler\Trigger\CronExpressionTrigger;

$trigger = new CronExpressionTrigger('0 3 * * *');     // 毎日 03:00
$trigger = new CronExpressionTrigger('*/15 * * * *');   // 15 分ごと
$trigger = new CronExpressionTrigger('0 9 * * 1');      // 毎週月曜 09:00
```

`getIntervalInSeconds()` は `null` を返します（cron 式は固定インターバルではないため）。

#### IntervalTrigger

固定間隔での繰り返し実行。

```php
use WpPack\Component\Scheduler\Trigger\IntervalTrigger;

$trigger = new IntervalTrigger(
    intervalInSeconds: 300,     // 5 分ごと
    from: new \DateTimeImmutable('2024-01-01 00:00:00'), // 開始日時（省略可）
);
```

`from` を指定すると、その日時以降に初回実行されます。省略時は即座に実行可能になります。

#### WpCronScheduleTrigger

WordPress WP-Cron のビルトイン名前付きスケジュールを使用します。

```php
use WpPack\Component\Scheduler\Trigger\WpCronScheduleTrigger;

$trigger = new WpCronScheduleTrigger('daily');
```

| スケジュール名 | インターバル |
|---------------|------------|
| `hourly` | 3600 秒（1 時間） |
| `twicedaily` | 43200 秒（12 時間） |
| `daily` | 86400 秒（1 日） |
| `weekly` | 604800 秒（1 週間） |

未知のスケジュール名を指定すると `InvalidArgumentException` がスローされます。

#### DateTimeTrigger

指定日時に一度だけ実行する Trigger。`OneTimeMessage` で使用されます。

```php
use WpPack\Component\Scheduler\Trigger\DateTimeTrigger;

$trigger = new DateTimeTrigger(
    new \DateTimeImmutable('2024-12-31 23:59:59'),
);

$trigger->getDateTime(); // DateTimeImmutable
```

- 過去の日時を指定した場合、`getNextRunDate()` は `null` を返します
- 一度実行された後（`$lastRun` が非 null）も `null` を返します
- `getIntervalInSeconds()` は `null` を返します

#### JitterTrigger

内部の Trigger にランダムな遅延（ジッター）を追加するデコレーター。複数のスケジュールが同時に実行されるのを防ぎます。

```php
use WpPack\Component\Scheduler\Trigger\JitterTrigger;
use WpPack\Component\Scheduler\Trigger\IntervalTrigger;

$trigger = new JitterTrigger(
    inner: new IntervalTrigger(3600),
    maxJitterSeconds: 120,  // デフォルト: 60
);
```

0 から `maxJitterSeconds` 秒のランダムな遅延が次回実行日時に加算されます。

### Trigger 比較表

| Trigger | 用途 | `getIntervalInSeconds()` | 例 |
|---------|------|-------------------------|-----|
| `CronExpressionTrigger` | cron 式スケジュール | `null` | `0 3 * * *` |
| `IntervalTrigger` | 固定間隔 | 指定値 | 300 秒ごと |
| `WpCronScheduleTrigger` | WP-Cron 名前付き | 対応する秒数 | `daily` |
| `DateTimeTrigger` | 一度だけ実行 | `null` | 2024-12-31 |
| `JitterTrigger` | ランダム遅延付き | 内部 Trigger の値 | ±60 秒 |

## RecurringMessage

繰り返し実行するメッセージを定義します。4 つの static factory メソッドで生成します。

### `schedule()` — WP-Cron 名前付きスケジュール

```php
use WpPack\Component\Scheduler\Message\RecurringMessage;

RecurringMessage::schedule('hourly', new HourlyMessage());
RecurringMessage::schedule('twicedaily', new TwiceDailyMessage());
RecurringMessage::schedule('daily', new DailyMessage());
RecurringMessage::schedule('weekly', new WeeklyMessage());
```

### `every()` — インターバルベース

人間が読みやすい形式でインターバルを指定します。

```php
RecurringMessage::every('30 seconds', new FrequentMessage());
RecurringMessage::every('5 minutes', new FiveMinuteMessage());
RecurringMessage::every('1 hour', new HourlyMessage());
RecurringMessage::every('2 hours', new BiHourlyMessage());
RecurringMessage::every('1 day', new DailyMessage());
RecurringMessage::every('1 week', new WeeklyMessage());
```

対応フォーマット:

| 単位 | 単数形 / 複数形 |
|------|---------------|
| 秒 | `second` / `seconds` |
| 分 | `minute` / `minutes` |
| 時間 | `hour` / `hours` |
| 日 | `day` / `days` |
| 週 | `week` / `weeks` |

### `cron()` — cron 式

```php
RecurringMessage::cron('0 3 * * *', new NightlyCleanupMessage());
RecurringMessage::cron('*/15 * * * *', new FrequentCheckMessage());
RecurringMessage::cron('0 9 * * 1', new MondayReportMessage());
```

### `trigger()` — カスタム Trigger

任意の `TriggerInterface` 実装を渡せます。

```php
use WpPack\Component\Scheduler\Trigger\JitterTrigger;
use WpPack\Component\Scheduler\Trigger\IntervalTrigger;

RecurringMessage::trigger(
    new JitterTrigger(new IntervalTrigger(3600), maxJitterSeconds: 120),
    new HourlyMessage(),
);
```

### `name()` — 名前の設定

`name()` メソッドでスケジュールに名前を付けられます（fluent）。

```php
RecurringMessage::schedule('daily', new CleanupMessage())
    ->name('daily-cleanup');
```

### RecurringMessage API

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `schedule(string, object)` | `self` | WP-Cron 名前付きスケジュール |
| `every(string, object)` | `self` | インターバルベース |
| `cron(string, object)` | `self` | cron 式 |
| `trigger(TriggerInterface, object)` | `self` | カスタム Trigger |
| `name(string)` | `self` | 名前設定（fluent） |
| `getTrigger()` | `TriggerInterface` | Trigger を取得 |
| `getMessage()` | `object` | メッセージを取得 |
| `getName()` | `?string` | 名前を取得（未設定は `null`） |

## OneTimeMessage

指定日時に一度だけ実行するメッセージを定義します。内部で `DateTimeTrigger` を使用します。

### `at()` — 日時指定

```php
use WpPack\Component\Scheduler\Message\OneTimeMessage;

OneTimeMessage::at(
    new \DateTimeImmutable('2024-12-31 23:59:59'),
    new NewYearMessage(),
);
```

### `delay()` — 遅延指定（文字列）

現在時刻からの相対的な遅延を指定します。

```php
OneTimeMessage::delay('30 minutes', new ReminderMessage());
OneTimeMessage::delay('2 hours', new FollowUpMessage());
OneTimeMessage::delay('1 day', new DailyDigestMessage());
```

### `delaySeconds()` — 遅延指定（秒数）

```php
OneTimeMessage::delaySeconds(1800, new ReminderMessage());
```

### `name()` — 名前の設定

```php
OneTimeMessage::at($dateTime, new ReminderMessage())
    ->name('welcome-reminder');
```

### OneTimeMessage API

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `at(DateTimeImmutable, object)` | `self` | 日時指定 |
| `delay(string, object)` | `self` | 遅延指定（文字列） |
| `delaySeconds(int, object)` | `self` | 遅延指定（秒数） |
| `name(string)` | `self` | 名前設定（fluent） |
| `getTrigger()` | `TriggerInterface` | Trigger を取得 |
| `getMessage()` | `object` | メッセージを取得 |
| `getName()` | `?string` | 名前を取得 |

## Schedule と ScheduleProviderInterface

### ScheduleProviderInterface

```php
namespace WpPack\Component\Scheduler;

interface ScheduleProviderInterface
{
    public function getSchedule(): Schedule;
}
```

### `#[AsSchedule]` アトリビュート

`ScheduleProviderInterface` を実装したクラスに `#[AsSchedule]` を付与します。

```php
use WpPack\Component\Scheduler\Attribute\AsSchedule;

#[AsSchedule]                          // name: 'default'
#[AsSchedule(name: 'maintenance')]     // 名前付き
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `name` | `string` | `'default'` | スケジュールプロバイダー名 |

### Schedule クラス

`Schedule` はスケジュールのコレクションです。`add()` で `RecurringMessage` / `OneTimeMessage` を追加します（fluent）。

```php
use WpPack\Component\Scheduler\Schedule;
use WpPack\Component\Scheduler\Message\RecurringMessage;
use WpPack\Component\Scheduler\Message\OneTimeMessage;

$schedule = (new Schedule())
    ->add(RecurringMessage::schedule('daily', new CleanupMessage()))
    ->add(RecurringMessage::every('30 minutes', new CachePurgeMessage()))
    ->add(RecurringMessage::cron('0 3 * * *', new NightlyBackupMessage()))
    ->add(OneTimeMessage::delay('1 hour', new WelcomeMessage()));
```

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `add(ScheduledMessage)` | `self` | メッセージを追加（fluent） |
| `getMessages()` | `list<ScheduledMessage>` | 全メッセージを取得 |

### 複数プロバイダーの例

```php
#[AsSchedule(name: 'maintenance')]
final class MaintenanceScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::schedule('daily', new CleanupMessage()))
            ->add(RecurringMessage::schedule('weekly', new OptimizeTablesMessage()));
    }
}

#[AsSchedule(name: 'reporting')]
final class ReportingScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::cron('0 9 * * 1', new WeeklyReportMessage()))
            ->add(RecurringMessage::every('1 hour', new MetricsCollectionMessage()));
    }
}
```

## SchedulerInterface（バックエンド）

`SchedulerInterface` はスケジュールの登録・解除・確認を行う CRUD API です。バックエンドごとに実装が異なります。

```php
namespace WpPack\Component\Scheduler\Scheduler;

interface SchedulerInterface
{
    public function schedule(string $scheduleId, ScheduledMessage $message): void;
    public function unschedule(string $scheduleId): void;
    public function has(string $scheduleId): bool;
    public function getNextRunDate(string $scheduleId): ?\DateTimeImmutable;
}
```

### バックエンド比較表

| バックエンド | パッケージ | 信頼性 | 適用環境 |
|------------|-----------|--------|---------|
| WpCronScheduler | `wppack/scheduler` | 標準 | 標準 WordPress |
| ActionSchedulerScheduler | `wppack/scheduler` | 高 | WooCommerce 環境 |
| EventBridgeScheduler | `wppack/eventbridge-scheduler` | 最高 | サーバーレス（AWS） |
| NullScheduler | `wppack/scheduler` | — | テスト用 |

### NullScheduler

テスト用のインメモリ実装。実際のスケジューリングは行わず、登録されたメッセージを保持します。

```php
use WpPack\Component\Scheduler\Scheduler\NullScheduler;

$scheduler = new NullScheduler();
$scheduler->schedule('daily-cleanup', RecurringMessage::schedule('daily', new CleanupMessage()));

$scheduler->has('daily-cleanup');    // true
$scheduler->unschedule('daily-cleanup');
$scheduler->has('daily-cleanup');    // false

// テスト用: 登録済みメッセージを取得
$schedules = $scheduler->getSchedules(); // array<string, ScheduledMessage>
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/scheduler.md) を参照してください。

## Messenger 連携

Scheduler で定義されたメッセージは `wppack/messenger` の `MessageBus` を通じてディスパッチされます。メッセージクラスはプレーンな POPO として定義し、`#[AsMessageHandler]` でハンドラーを紐付けます。

```
Schedule → SchedulerInterface::schedule()
                 ↓ スケジュール実行時
         MessageBus::dispatch($message)
                 ↓
         #[AsMessageHandler] → Handler
```

```php
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CleanupMessage
{
    public function __construct(
        public int $retentionDays = 30,
    ) {}
}

#[AsMessageHandler]
final class CleanupMessageHandler
{
    public function __invoke(CleanupMessage $message): void
    {
        // retentionDays より古いデータを削除
    }
}
```

## テスト

`NullScheduler` と `TestMessageBus` を組み合わせてテストします。

```php
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Schedule;
use WpPack\Component\Scheduler\Message\RecurringMessage;
use WpPack\Component\Scheduler\Scheduler\NullScheduler;
use WpPack\Component\Messenger\Test\TestMessageBus;

class CleanupScheduleProviderTest extends TestCase
{
    public function testScheduleContainsDailyCleanup(): void
    {
        $provider = new CleanupScheduleProvider();
        $schedule = $provider->getSchedule();

        $messages = $schedule->getMessages();
        $this->assertNotEmpty($messages);
        $this->assertInstanceOf(CleanupOldPostsMessage::class, $messages[0]->getMessage());
    }

    public function testScheduleRegistration(): void
    {
        $scheduler = new NullScheduler();

        $scheduler->schedule('daily-cleanup', RecurringMessage::schedule('daily', new CleanupMessage()));

        $this->assertTrue($scheduler->has('daily-cleanup'));
        $this->assertNotNull($scheduler->getNextRunDate('daily-cleanup'));
    }

    public function testMessageDispatching(): void
    {
        $testBus = new TestMessageBus();

        $testBus->dispatch(new CleanupMessage(retentionDays: 7));

        $messages = $testBus->getDispatchedMessages();
        $this->assertCount(1, $messages);
        $this->assertSame(7, $messages[0]->retentionDays);
    }
}
```

## 主要クラス一覧

| クラス | 説明 |
|-------|------|
| `Attribute\AsSchedule` | スケジュールプロバイダーマーカー |
| `ScheduleProviderInterface` | スケジュール定義インターフェース |
| `Schedule` | スケジュールコレクション |
| `Message\ScheduledMessage` | スケジュールメッセージインターフェース |
| `Message\RecurringMessage` | 繰り返し実行メッセージ |
| `Message\OneTimeMessage` | 単発実行メッセージ |
| `Trigger\TriggerInterface` | Trigger インターフェース |
| `Trigger\CronExpressionTrigger` | cron 式 Trigger |
| `Trigger\IntervalTrigger` | 固定間隔 Trigger |
| `Trigger\WpCronScheduleTrigger` | WP-Cron 名前付き Trigger |
| `Trigger\DateTimeTrigger` | 日時指定 Trigger |
| `Trigger\JitterTrigger` | ジッター付き Trigger デコレーター |
| `Scheduler\SchedulerInterface` | バックエンド CRUD インターフェース |
| `Scheduler\NullScheduler` | テスト用インメモリ実装 |
| `Exception\SchedulerException` | スケジューラー例外 |
| `Exception\InvalidArgumentException` | 不正な引数例外 |
| `Exception\LogicException` | ロジック例外 |

## 依存関係

### 必須
- **wppack/messenger** -- メッセージバス基盤
- **dragonmantank/cron-expression ^3.0** -- cron 式パーサー

### 推奨
- **wppack/eventbridge-scheduler** -- EventBridge バックエンド（高信頼性スケジューリング）
