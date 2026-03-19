# EventBridge Scheduler

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=eventbridge_scheduler)](https://codecov.io/github/wppack-io/wppack)

Amazon EventBridge Scheduler backend for [WpPack Scheduler](../../README.md).

## Installation

```bash
composer require wppack/eventbridge-scheduler
```

## Usage

### Register the Scheduler

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduler;

$scheduler = new EventBridgeScheduler(
    eventBridgeClient: $eventBridgeClient,
    sqsPayloadFactory: $sqsPayloadFactory,
    scheduleGroupResolver: $scheduleGroupResolver,
    scheduleIdGenerator: $scheduleIdGenerator,
);
```

### How It Works

Schedules defined via `wppack/scheduler` are synced to AWS EventBridge Scheduler. At the scheduled time, EventBridge sends a message to SQS, which is consumed by Lambda workers via `wppack/messenger`.

```
Schedule Provider → EventBridge Scheduler → SQS → Lambda (wppack/messenger)
```

### Multisite Support

The bridge resolves schedule groups per site using `ScheduleGroupResolverInterface`, keeping schedules isolated across a multisite network.

## Requirements

- `wppack/scheduler` ^1.0
- `wppack/messenger` ^1.0
- `async-aws/scheduler` ^1.0

## Documentation

See [docs/components/scheduler.md](../../../../docs/components/scheduler.md) for full documentation.

## License

MIT
