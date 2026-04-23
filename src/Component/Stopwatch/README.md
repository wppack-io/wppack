# WPPack Stopwatch

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=stopwatch)](https://codecov.io/github/wppack-io/wppack)

Stopwatch component for measuring code execution time. Provides a simple API to start/stop named timers and collect timing data.

## Installation

```bash
composer require wppack/stopwatch
```

## Usage

```php
use WPPack\Component\Stopwatch\Stopwatch;

$stopwatch = new Stopwatch();

// Start a timer
$stopwatch->start('my_operation', 'app');

// ... expensive work ...

// Stop and get the event
$event = $stopwatch->stop('my_operation');

echo $event->duration; // milliseconds
echo $event->memory;   // bytes (memory_get_usage at stop time)
echo $event->category; // 'app'
```

## Multiple Timers

Multiple timers can run concurrently:

```php
$stopwatch->start('db_query', 'database');
$stopwatch->start('template', 'rendering');

$stopwatch->stop('db_query');
$stopwatch->stop('template');

// Retrieve all completed events
$events = $stopwatch->getEvents();
```

## API

### `Stopwatch`

| Method | Description |
|--------|-------------|
| `start(string $name, string $category = 'default'): void` | Start a named timer |
| `stop(string $name): StopwatchEvent` | Stop a timer and return the event |
| `isStarted(string $name): bool` | Check if a timer is running |
| `getEvent(string $name): StopwatchEvent` | Get a completed event |
| `getEvents(): array<string, StopwatchEvent>` | Get all completed events |
| `reset(): void` | Clear all timers and events |

### `StopwatchEvent` (readonly)

| Property | Type | Description |
|----------|------|-------------|
| `name` | `string` | Timer name |
| `category` | `string` | Category label |
| `duration` | `float` | Duration in milliseconds |
| `memory` | `int` | Memory usage in bytes at stop time |
| `startTime` | `float` | Start time in milliseconds (hrtime-based) |
| `endTime` | `float` | End time in milliseconds (hrtime-based) |

## Requirements

- PHP 8.2+

## Further Reading

See [docs/components/stopwatch/](../../../docs/components/stopwatch/) for the full reference and Debug toolbar integration.

## License

MIT
