# SchedulerPlugin

Symfony Scheduler ライクなスケジュール定義と AWS EventBridge Scheduler 同期を提供する WordPress プラグイン。

## 概要

SchedulerPlugin は PHP アトリビュートによる宣言的なスケジュール定義を WordPress に導入します。定義されたスケジュールは [Action Scheduler](https://actionscheduler.org/)（Automattic 社製のサードパーティライブラリ）を一次データソースとし、EventBridge Scheduler にリアルタイム同期されます。指定時刻に SQS 経由でメッセージが配信され、Lambda (Bref) 上でハンドラが実行されます。

> [!NOTE]
> Action Scheduler は WordPress コアには含まれていません。WooCommerce に同梱されているほか、単独でも利用可能です。SchedulerPlugin は Action Scheduler をバンドルしています。

## アーキテクチャ

```
┌─ スケジュール定義 ────────────────────────────┐
│                                              │
│  #[AsSchedule]                               │
│  class MyScheduleProvider                    │
│      RecurringMessage::cron('@daily', $msg)  │
│                                              │
│  #[AsMessageHandler]                         │
│  class MyMessageHandler                      │
│      public function __invoke($msg): void    │
│                                              │
└──────────────────────────────────────────────┘
            ↓ SchedulerPlugin が収集・同期
┌─ 一次データ ────────────────────────────────┐
│ Action Scheduler DB（一次データソース）       │
└──────────────────────────────────────────────┘
            ↓ リアルタイム同期
┌─ スケジュール管理 ─────────────────────────┐
│ AWS EventBridge Scheduler                   │
│ （分単位の正確な時刻管理）                    │
└──────────────────────────────────────────────┘
            ↓ 指定時刻にトリガー
┌─ メッセージキュー ─────────────────────────┐
│ Amazon SQS                                   │
└──────────────────────────────────────────────┘
            ↓ WpPack\Component\Messenger
┌─ 実行 ──────────────────────────────────────┐
│ Lambda (Bref WordPress)                      │
│ #[AsMessageHandler] を実行                   │
└──────────────────────────────────────────────┘
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/scheduler | スケジュール定義（RecurringMessage, Schedule） |
| wppack/messenger | メッセージバス・ハンドラ基盤 |
| async-aws/scheduler | EventBridge Scheduler API |
| async-aws/sqs | SQS メッセージ送受信 |
| bref/bref | Lambda ランタイム |

## 名前空間

```
WpPack\Plugin\SchedulerPlugin\
```

## 主要クラス

### Plugin

プラグインのエントリポイント。WordPress のアクティベーション・ディアクティベーション処理、各コンポーネントの初期化を行う。

```php
namespace WpPack\Plugin\SchedulerPlugin;

final class Plugin
{
    public function boot(): void;
    public function activate(): void;
    public function deactivate(): void;
}
```

### ScheduleDiscovery

`#[AsSchedule]` アトリビュートが付与されたクラスを自動検出し、スケジュールプロバイダーとして登録する。

```php
namespace WpPack\Plugin\SchedulerPlugin;

final class ScheduleDiscovery
{
    /** @return list<ScheduleProviderInterface> */
    public function discover(): array;
}
```

### HandlerDiscovery

`#[AsMessageHandler]` アトリビュートが付与されたクラスを自動検出し、メッセージハンドラとして登録する。

```php
namespace WpPack\Plugin\SchedulerPlugin;

final class HandlerDiscovery
{
    /** @return array<class-string, callable> */
    public function discover(): array;
}
```

### WpCronInterceptor

WordPress の WP-Cron イベントをインターセプトし、Action Scheduler に変換する。

```php
namespace WpPack\Plugin\SchedulerPlugin;

final class WpCronInterceptor
{
    public function intercept(): void;
    public function isEnabled(): bool;
}
```

### ActionSchedulerInterceptor

Action Scheduler の自動実行を無効化し、EventBridge 経由の実行に切り替える。

```php
namespace WpPack\Plugin\SchedulerPlugin;

final class ActionSchedulerInterceptor
{
    public function disableAutoRunner(): void;
}
```

### EventBridgeSynchronizer

Action Scheduler のスケジュールデータを EventBridge Scheduler にリアルタイム同期する。

```php
namespace WpPack\Plugin\SchedulerPlugin;

final class EventBridgeSynchronizer
{
    public function sync(): SyncResult;
    public function syncSchedule(string $scheduleId): void;
    public function removeSchedule(string $scheduleId): void;
}
```

## WP-CLI コマンド

### SyncCommand

Action Scheduler のスケジュールを EventBridge に手動同期する。

```bash
# 全スケジュールを同期
wp wppack scheduler sync

# 同期状態をドライランで確認
wp wppack scheduler sync --dry-run
```

### StatusCommand

スケジュールの状態を表示する。

```bash
# 全スケジュールの状態を表示
wp wppack scheduler status

# EventBridge との同期状態を含めて表示
wp wppack scheduler status --show-eventbridge
```

## 管理画面 UI

`WpPack > Scheduler` メニューで以下の情報を表示:

- 登録済みスケジュール一覧
- 各スケジュールの次回実行時刻
- EventBridge との同期状態
- 直近の実行履歴

## Lambda ハンドラ (Bref)

SQS メッセージを受信し、対応するメッセージハンドラを実行する Lambda 関数。Bref の WordPress ブートストラップを使用して WordPress 環境を初期化する。

```yaml
# serverless.yml
functions:
  scheduler-worker:
    handler: vendor/wppack/scheduler-plugin/handler.php
    runtime: php-81
    events:
      - sqs:
          arn: !GetAtt SchedulerQueue.Arn
```

## マルチサイト対応

- サイトごとに独立したスケジュールグループを作成
- EventBridge スケジュール名にサイト ID を含める
- `switch_to_blog()` による正しいサイトコンテキストでのハンドラ実行

## 環境変数

```bash
# AWS 認証情報
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=ap-northeast-1

# EventBridge Scheduler 設定
EVENTBRIDGE_SCHEDULER_GROUP=wppack-scheduler
EVENTBRIDGE_SCHEDULER_ROLE_ARN=arn:aws:iam::123456789:role/EventBridgeSchedulerRole

# SQS 設定
SQS_QUEUE_URL=https://sqs.ap-northeast-1.amazonaws.com/123456789/wppack-scheduler

# WP-Cron を Action Scheduler に変換（デフォルト: true）
WPPACK_CONVERT_WP_CRON=true

# EventBridge 同期を有効化（デフォルト: true、開発環境では false）
WPPACK_USE_EVENTBRIDGE=true
```

## 使用例

### スケジュール定義

```php
use WpPack\Component\Scheduler\Attribute\AsSchedule;
use WpPack\Component\Scheduler\RecurringMessage;
use WpPack\Component\Scheduler\Schedule;
use WpPack\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule]
final class MaintenanceScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::cron('@daily', new CleanupMessage()))
            ->add(RecurringMessage::every('1 hour', new SyncMessage()));
    }
}
```

### メッセージハンドラ

```php
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CleanupMessageHandler
{
    public function __invoke(CleanupMessage $message): void
    {
        // クリーンアップ処理
    }
}
```
