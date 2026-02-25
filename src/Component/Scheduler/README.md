# WpPack Scheduler

Symfony-like task scheduler with AWS EventBridge sync for WordPress.

## Installation

```bash
composer require wppack/scheduler
```

## Usage

### Define Schedules

```php
use WpPack\Component\Scheduler\Attribute\AsSchedule;
use WpPack\Component\Scheduler\Schedule;
use WpPack\Component\Scheduler\RecurringMessage;
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

## Architecture

Schedules are stored in Action Scheduler (primary data source) and synced to AWS EventBridge Scheduler for precise time-based triggering. EventBridge sends messages to SQS at the scheduled time, which are then consumed by Lambda workers via `wppack/messenger`.

## Documentation

See [docs/components/scheduler.md](../../docs/components/scheduler.md) for full documentation.

## License

MIT
