# wppack/scheduler-plugin

WordPress plugin for EventBridge-based scheduling. Provides Symfony Scheduler-like declarative schedule definitions with real-time synchronization to AWS EventBridge Scheduler.

## Installation

```bash
composer require wppack/scheduler-plugin
```

## Requirements

- PHP 8.1+
- WordPress 6.x
- Action Scheduler 3.x
- AWS account with EventBridge Scheduler and SQS

## Usage

### Define a Schedule

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

### Define a Handler

```php
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CleanupMessageHandler
{
    public function __invoke(CleanupMessage $message): void
    {
        // Your cleanup logic
    }
}
```

### WP-CLI

```bash
wp wppack scheduler sync          # Sync schedules to EventBridge
wp wppack scheduler status        # Show schedule status
```

## Configuration

Set environment variables:

```bash
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=ap-northeast-1
EVENTBRIDGE_SCHEDULER_GROUP=wppack-scheduler
EVENTBRIDGE_SCHEDULER_ROLE_ARN=arn:aws:iam::123456789:role/EventBridgeSchedulerRole
SQS_QUEUE_URL=https://sqs.ap-northeast-1.amazonaws.com/123456789/wppack-scheduler
```

## Documentation

See [full documentation](../../docs/plugins/scheduler-plugin.md) for details.

## License

MIT
