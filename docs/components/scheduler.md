# Scheduler コンポーネント

**パッケージ:** `wppack/scheduler`
**名前空間:** `WpPack\Component\Scheduler\`
**レイヤー:** Feature

WordPress のスケジューリング API（`wp_schedule_event()` / `wp_schedule_single_event()`）をアトリビュートベースのモダンなインターフェースでラップするコンポーネントです。`wppack/messenger` と連携し、スケジュールされたメッセージを非同期処理できます。

## インストール

```bash
composer require wppack/scheduler
```

## このコンポーネントの機能

- **アトリビュートベースのスケジュール定義** — `#[AsSchedule]` による宣言的な定義
- **`ScheduleProviderInterface`** — `getSchedule()` メソッドでスケジュールを返す統一インターフェース
- **`RecurringMessage`** — WP-Cron の名前付きスケジュールやインターバルで繰り返し実行するメッセージの定義
- **WordPress Cron フック属性** — `cron_schedules`、`pre_schedule_event` 等の Named Hook Attributes
- **Messenger 連携** — `wppack/messenger` を使った非同期メッセージ処理

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
┌─ WordPress Cron ───────────────────────────────────┐
│  wp_schedule_event() / wp_schedule_single_event()  │
└────────────────────┬───────────────────────────────┘
                     ↓ スケジュール実行時
┌─ wppack/messenger ─────────────────────────────────┐
│  MessageBus → SQS → Lambda → Handler              │
└────────────────────────────────────────────────────┘
```

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
use WpPack\Component\Scheduler\RecurringMessage;
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

## スケジュール定義

### `#[AsSchedule]` と `ScheduleProviderInterface`

`#[AsSchedule]` アトリビュートを付与したクラスで `ScheduleProviderInterface` を実装し、`getSchedule()` メソッドでスケジュールを返します。

```php
use WpPack\Component\Scheduler\Attribute\AsSchedule;
use WpPack\Component\Scheduler\ScheduleProviderInterface;
use WpPack\Component\Scheduler\Schedule;
use WpPack\Component\Scheduler\RecurringMessage;

#[AsSchedule]
final class ReportScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::schedule('weekly', new WeeklyReportMessage()))
            ->add(RecurringMessage::schedule('daily', new MonthlyReportMessage()));
    }
}
```

### ScheduleProviderInterface

```php
namespace WpPack\Component\Scheduler;

interface ScheduleProviderInterface
{
    public function getSchedule(): Schedule;
}
```

### Schedule クラス

`Schedule` はスケジュールのコレクションです。`add()` メソッドで `RecurringMessage` を追加します。

```php
use WpPack\Component\Scheduler\Schedule;
use WpPack\Component\Scheduler\RecurringMessage;

$schedule = (new Schedule())
    ->add(RecurringMessage::schedule('daily', new CleanupMessage()))
    ->add(RecurringMessage::schedule('hourly', new HealthCheckMessage()))
    ->add(RecurringMessage::every('30 minutes', new CachePurgeMessage()));
```

## RecurringMessage

繰り返し実行するメッセージを定義します。

### WP-Cron 名前付きスケジュール

WordPress WP-Cron のビルトイン名前付きスケジュールを使用します：

```php
use WpPack\Component\Scheduler\RecurringMessage;

// ビルトインスケジュール
RecurringMessage::schedule('hourly', new HourlyMessage());       // 1時間ごと
RecurringMessage::schedule('twicedaily', new TwiceDailyMessage()); // 1日2回
RecurringMessage::schedule('daily', new DailyMessage());         // 1日1回
RecurringMessage::schedule('weekly', new WeeklyMessage());       // 1週間ごと
```

### インターバルベース

```php
RecurringMessage::every('1 hour', new HourlyMessage());
RecurringMessage::every('30 minutes', new FrequentMessage());
RecurringMessage::every('5 minutes', new VeryFrequentMessage());
RecurringMessage::every('1 day', new DailyMessage());
```

### 名前付きスケジュール

```php
RecurringMessage::schedule('daily', new CleanupMessage())
    ->name('daily-cleanup');
```

## メッセージハンドラー

スケジュールされたメッセージは `wppack/messenger` のハンドラーで処理します。メッセージクラスはプレーンな POPO として定義します。

```php
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

// メッセージクラス（プレーン POPO）
final readonly class CleanupMessage
{
    public function __construct(
        public int $retentionDays = 30,
    ) {}
}

// ハンドラー
#[AsMessageHandler]
final class CleanupMessageHandler
{
    public function __invoke(CleanupMessage $message): void
    {
        // retentionDays より古いデータを削除
    }
}
```

## Named Hook Attributes

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Scheduler/Subscriber/`

### #[CronSchedulesFilter]

**WordPress フック:** `cron_schedules`
**用途:** デフォルト（hourly, daily 等）以外のカスタム cron スケジュールを追加する場合に使用します。

```php
use WpPack\Component\Scheduler\Attribute\CronSchedulesFilter;

class CronScheduleManager
{
    #[CronSchedulesFilter(priority: 10)]
    public function registerCustomSchedules(array $schedules): array
    {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'wppack'),
        ];

        $schedules['fifteen_minutes'] = [
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'wppack'),
        ];

        return $schedules;
    }
}
```

### #[ScheduledEventAction]

**WordPress フック:** イベント名に基づく動的フック
**用途:** スケジュールされたイベントが発火した際の処理を定義します。

```php
use WpPack\Component\Scheduler\Attribute\ScheduledEventAction;

class ScheduledJobs
{
    #[ScheduledEventAction(event: 'wppack_process_queue', priority: 10)]
    public function processJobQueue(): void
    {
        $start_time = microtime(true);
        $jobs_processed = 0;

        while ($job = $this->runner->getNextJob()) {
            if ($this->runner->execute($job)) {
                $jobs_processed++;
            }

            if ((microtime(true) - $start_time) > 20) {
                break;
            }
        }
    }

    #[ScheduledEventAction(event: 'wppack_sync_data', priority: 10)]
    public function syncExternalData(array $args = []): void
    {
        $sync_type = $args['type'] ?? 'full';
        $source = $args['source'] ?? 'api';

        match ($sync_type) {
            'full' => $this->runner->runFullSync($source),
            'incremental' => $this->runner->runIncrementalSync($source),
            'changes' => $this->runner->syncChangesOnly($source),
            default => throw new \InvalidArgumentException("Unknown sync type: {$sync_type}"),
        };
    }
}
```

### #[PreScheduleEventFilter]

**WordPress フック:** `pre_schedule_event`
**用途:** イベントのスケジューリングを変更または阻止します。

```php
use WpPack\Component\Scheduler\Attribute\PreScheduleEventFilter;

class ScheduleValidator
{
    #[PreScheduleEventFilter(priority: 10)]
    public function validateScheduling($pre, \stdClass $event, bool $wp_error)
    {
        if ($this->isDuplicateJob($event)) {
            return $wp_error ? new \WP_Error(
                'duplicate_event',
                __('This event is already scheduled', 'wppack')
            ) : false;
        }

        if ($this->exceedsRateLimit($event)) {
            return $wp_error ? new \WP_Error(
                'rate_limit',
                __('Too many scheduled events', 'wppack')
            ) : false;
        }

        return $pre;
    }
}
```

### #[PreUnscheduleEventFilter]

**WordPress フック:** `pre_unschedule_event`
**用途:** イベントのアンスケジューリングを制御します。

```php
use WpPack\Component\Scheduler\Attribute\PreUnscheduleEventFilter;

class UnscheduleManager
{
    #[PreUnscheduleEventFilter(priority: 10)]
    public function validateUnscheduling($pre, int $timestamp, string $hook, array $args, bool $wp_error)
    {
        $protected_events = [
            'wppack_health_check',
            'wppack_security_scan',
            'wppack_backup_database',
        ];

        if (in_array($hook, $protected_events) && !current_user_can('manage_options')) {
            return $wp_error ? new \WP_Error(
                'protected_event',
                __('Cannot unschedule protected system event', 'wppack')
            ) : false;
        }

        return $pre;
    }
}
```

### Hook Attribute 一覧

```php
// スケジュール管理
#[CronSchedulesFilter(priority: 10)]      // カスタム cron スケジュールの追加
#[ScheduledEventAction(priority: 10)]      // スケジュールされたイベントの処理

// イベント制御
#[PreScheduleEventFilter(priority: 10)]    // イベントスケジューリングの制御
#[PreUnscheduleEventFilter(priority: 10)]  // イベントアンスケジューリングの制御

// イベントフィルター
#[ScheduleEventFilter(priority: 10)]       // スケジュールされたイベントの変更
```

## テスト

```php
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Schedule;
use WpPack\Component\Scheduler\RecurringMessage;

class ReportScheduleProviderTest extends TestCase
{
    public function testScheduleContainsWeeklyReport(): void
    {
        $provider = new ReportScheduleProvider();
        $schedule = $provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        $this->assertNotEmpty($messages);
    }
}
```

## 主要クラス一覧

| クラス | 説明 |
|-------|------|
| `Attribute\AsSchedule` | スケジュールプロバイダーマーカー |
| `ScheduleProviderInterface` | スケジュール定義インターフェース（`getSchedule(): Schedule`） |
| `Schedule` | スケジュールコレクション |
| `RecurringMessage` | 繰り返し実行メッセージ |
| `Trigger\ScheduleTrigger` | WP-Cron 名前付きスケジュールトリガー |
| `Trigger\IntervalTrigger` | インターバルトリガー |

## このコンポーネントを使う場面

**最適な用途:**
- 定期的なメンテナンスやクリーンアップ処理
- データ同期
- レポート生成
- メールキャンペーン
- バックアップ処理
- インポート / エクスポート処理

**別の方法を検討すべき場面:**
- 単発のタスク
- リアルタイム処理
- ユーザートリガーのアクション

## 依存関係

### 必須
- なし — Scheduler コンポーネントは単独で動作可能です。

### 推奨
- **Messenger コンポーネント** — メッセージベースのスケジューリングに使用
- **Hook コンポーネント** — WordPress フック統合に使用
