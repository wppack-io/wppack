## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Scheduler/Subscriber/`

### Action アトリビュート

#### #[ScheduledEventAction]

**WordPress フック:** イベント名に基づく動的フック
**用途:** スケジュールされたイベントが発火した際の処理を定義します。

```php
use WpPack\Component\Hook\Attribute\Scheduler\Action\ScheduledEventAction;

class ScheduledJobs
{
    #[ScheduledEventAction(event: 'wppack_process_queue', priority: 10)]
    public function processJobQueue(): void
    {
        // ジョブキューの処理
    }

    #[ScheduledEventAction(event: 'wppack_sync_data', priority: 10)]
    public function syncExternalData(array $args = []): void
    {
        $sync_type = $args['type'] ?? 'full';

        match ($sync_type) {
            'full' => $this->runner->runFullSync(),
            'incremental' => $this->runner->runIncrementalSync(),
            default => throw new \InvalidArgumentException("Unknown sync type: {$sync_type}"),
        };
    }
}
```

#### #[WpCronAction]

**WordPress フック:** `wp_cron`
**用途:** WP-Cron の実行時に処理を行います。

```php
use WpPack\Component\Hook\Attribute\Scheduler\Action\WpCronAction;

class CronMonitor
{
    #[WpCronAction(priority: 10)]
    public function onCronRun(): void
    {
        // WP-Cron 実行時のモニタリング
    }
}
```

### Filter アトリビュート

#### #[CronSchedulesFilter]

**WordPress フック:** `cron_schedules`
**用途:** カスタム cron スケジュールを追加します。

```php
use WpPack\Component\Hook\Attribute\Scheduler\Filter\CronSchedulesFilter;

class CronScheduleManager
{
    #[CronSchedulesFilter(priority: 10)]
    public function registerCustomSchedules(array $schedules): array
    {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'wppack'),
        ];

        return $schedules;
    }
}
```

#### #[PreScheduleEventFilter]

**WordPress フック:** `pre_schedule_event`
**用途:** イベントのスケジューリングを変更または阻止します。

```php
use WpPack\Component\Hook\Attribute\Scheduler\Filter\PreScheduleEventFilter;

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

        return $pre;
    }
}
```

#### #[PreUnscheduleEventFilter]

**WordPress フック:** `pre_unschedule_event`
**用途:** イベントのアンスケジューリングを制御します。

```php
use WpPack\Component\Hook\Attribute\Scheduler\Filter\PreUnscheduleEventFilter;

class UnscheduleManager
{
    #[PreUnscheduleEventFilter(priority: 10)]
    public function validateUnscheduling($pre, int $timestamp, string $hook, array $args, bool $wp_error)
    {
        $protected_events = ['wppack_health_check', 'wppack_security_scan'];

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

#### #[PreDoEventFilter]

**WordPress フック:** `pre_do_event`
**用途:** イベントの実行前に処理を制御します。

```php
use WpPack\Component\Hook\Attribute\Scheduler\Filter\PreDoEventFilter;

class EventExecutionGuard
{
    #[PreDoEventFilter(priority: 10)]
    public function beforeEventExecution($pre, \stdClass $event)
    {
        // イベント実行前のガード処理
        return $pre;
    }
}
```

#### #[ScheduleEventFilter]

**WordPress フック:** `schedule_event`
**用途:** スケジュールされたイベントを変更します。

```php
use WpPack\Component\Hook\Attribute\Scheduler\Filter\ScheduleEventFilter;

class EventModifier
{
    #[ScheduleEventFilter(priority: 10)]
    public function modifyScheduledEvent($event)
    {
        // イベントを変更して返す
        return $event;
    }
}
```

#### #[GetScheduleFilter]

**WordPress フック:** `get_schedule`
**用途:** スケジュール情報の取得をフィルタリングします。

```php
use WpPack\Component\Hook\Attribute\Scheduler\Filter\GetScheduleFilter;

class ScheduleOverride
{
    #[GetScheduleFilter(priority: 10)]
    public function filterSchedule($schedule)
    {
        // スケジュール情報をフィルタリング
        return $schedule;
    }
}
```

### Hook Attribute 一覧

```php
// Action
#[ScheduledEventAction(event: '...', priority: 10)]  // スケジュールイベント処理
#[WpCronAction(priority: 10)]                          // WP-Cron 実行時

// Filter — スケジュール管理
#[CronSchedulesFilter(priority: 10)]                   // カスタム cron スケジュールの追加
#[ScheduleEventFilter(priority: 10)]                   // スケジュールイベントの変更
#[GetScheduleFilter(priority: 10)]                     // スケジュール情報の取得

// Filter — イベント制御
#[PreScheduleEventFilter(priority: 10)]                // スケジューリングの制御
#[PreUnscheduleEventFilter(priority: 10)]              // アンスケジューリングの制御
#[PreDoEventFilter(priority: 10)]                      // イベント実行の制御
```
