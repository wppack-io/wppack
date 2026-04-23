# WPPack Scheduler

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=scheduler)](https://codecov.io/github/wppack-io/wppack)

Symfony-like task scheduler with AWS EventBridge sync for WordPress.

## Installation

```bash
composer require wppack/scheduler
```

## Usage

### Define Schedules

```php
use WPPack\Component\Scheduler\Attribute\AsSchedule;
use WPPack\Component\Scheduler\Schedule;
use WPPack\Component\Scheduler\RecurringMessage;
use WPPack\Component\Scheduler\ScheduleProviderInterface;

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

### RecurringMessage

```php
// Cron expression
RecurringMessage::cron('0 9 * * 1', new WeeklyReportMessage());

// Interval
RecurringMessage::every('30 minutes', new FrequentMessage());

// Presets
RecurringMessage::cron('@daily', new DailyMessage());
RecurringMessage::cron('@hourly', new HourlyMessage());
```

## Backend Selection

The Scheduler component ships three `SchedulerInterface` implementations. Pick the one that matches your runtime — **EventBridge is opt-in, not required**.

| Backend | Requires | CronExpression | Interval | DateTime | Notes |
|---------|----------|:-:|:-:|:-:|-------|
| `WpCronScheduler` | WordPress core | ❌ | ✅ | ✅ | Uses `wp_schedule_event()` / `wp_schedule_single_event()`. No cron-expression support — WP-Cron itself has none. |
| `ActionSchedulerScheduler` | `woocommerce/action-scheduler` plugin | ✅ | ✅ | ✅ | Uses `as_schedule_cron_action()` / `as_schedule_recurring_action()` / `as_schedule_single_action()`. Recommended for full cron support with no cloud dependency. |
| `EventBridgeScheduler` (bridge) | AWS EventBridge + SQS + `wppack/eventbridge-scheduler` | ✅ | ✅ | ✅ | Precise, durable, serverless-friendly. Schedules fire even when no PHP request arrives. |

All three use the `$scheduleId` as the wp-action/AS-hook name, and encode the payload object via `base64(serialize(...))` into a single positional arg. Your handler decodes with `unserialize(base64_decode(...))` and dispatches to `wppack/messenger`.

The EventBridge bridge also provides **opt-in takeover interceptors** (`WpCronInterceptor`, `ActionSchedulerInterceptor`) that redirect existing `wp_schedule_event()` / `as_schedule_*()` calls to EventBridge. These do not auto-register; install and call `$interceptor->register()` only if you want EventBridge to become the sole executor. Without the interceptors wired up, wp-cron and Action Scheduler keep running locally even when the EventBridge bridge is installed.

## Documentation

See [docs/components/scheduler.md](../../docs/components/scheduler.md) for full documentation.

## License

MIT
