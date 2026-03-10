# WpPack Debug

Web debug toolbar and error handler for WordPress. Provides a Symfony-inspired profiling toolbar, data collectors, stopwatch, and a styled exception handler.

## Installation

```bash
composer require wppack/debug
```

## Quick Start

```php
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\Profiler\Stopwatch;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Profiler\Profiler;
use WpPack\Component\Debug\DataCollector\RequestDataCollector;
use WpPack\Component\Debug\DataCollector\DatabaseDataCollector;
use WpPack\Component\Debug\DataCollector\MemoryDataCollector;
use WpPack\Component\Debug\DataCollector\TimeDataCollector;
use WpPack\Component\Debug\DataCollector\CacheDataCollector;
use WpPack\Component\Debug\DataCollector\WordPressDataCollector;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;

// 1. Configuration
$config = new DebugConfig(
    enabled: true,
    showToolbar: true,
    ipWhitelist: ['127.0.0.1', '::1'],
    roleWhitelist: ['administrator'],
);

// 2. Stopwatch & Profiler
$stopwatch = new Stopwatch();
$profiler = new Profiler($stopwatch);

$result = $profiler->profile('my_operation', function () {
    // expensive work
    return 'done';
}, 'app');

// Or use the stopwatch directly
$stopwatch->start('bootstrap', 'app');
// ... work ...
$event = $stopwatch->stop('bootstrap');
// $event->duration, $event->memory

// 3. Data Collectors & Toolbar
$collectors = [
    new RequestDataCollector(),
    new DatabaseDataCollector(),
    new MemoryDataCollector(),
    new TimeDataCollector($stopwatch),
    new CacheDataCollector(),
    new WordPressDataCollector(),
];

$profile = new Profile();
$renderer = new ToolbarRenderer();

$toolbar = new ToolbarSubscriber($config, $renderer, $profile, $collectors);
$toolbar->register(); // hooks into wp_footer

// 4. Exception Handler
$errorRenderer = new ErrorRenderer();
$handler = new ExceptionHandler($errorRenderer, $config);
$handler->register(); // sets as global exception handler
```

## Data Collectors

Built-in collectors gather profiling data and display it in the toolbar:

| Collector | Badge | Description |
|-----------|-------|-------------|
| `RequestDataCollector` | Method + status code | HTTP method, URL, headers, GET/POST params, cookies, HTTP API calls |
| `DatabaseDataCollector` | Query count | SQL queries, execution time, duplicate/slow query detection |
| `MemoryDataCollector` | Peak memory | Current/peak memory, limit, lifecycle snapshots |
| `TimeDataCollector` | Total time | Request duration, WordPress lifecycle phases, stopwatch events |
| `CacheDataCollector` | Hit rate | Object cache hits/misses, transient set/delete counts |
| `WordPressDataCollector` | WP version | WordPress/PHP version, active theme/plugins, debug constants |

## Custom Data Collector

Implement `DataCollectorInterface` (or extend `AbstractDataCollector`) and tag the class with `#[AsDataCollector]`:

```php
use WpPack\Component\Debug\Attribute\AsDataCollector;
use WpPack\Component\Debug\DataCollector\AbstractDataCollector;

#[AsDataCollector(name: 'my_collector', priority: 50)]
class MyCustomCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'my_collector';
    }

    public function collect(): void
    {
        $this->data = [
            'custom_metric' => 42,
        ];
    }

    public function getBadgeValue(): string
    {
        return (string) ($this->data['custom_metric'] ?? 0);
    }
}
```

When using the DI container, the `RegisterDataCollectorsPass` compiler pass auto-discovers classes tagged with `#[AsDataCollector]` and registers them by priority.

## Error Handler

The `ExceptionHandler` renders a styled error page with:

- Exception class, message, and source location
- Syntax-highlighted code snippet around the error line
- Full stack trace with expandable code context per frame
- Previous exception chain
- Request, environment, and performance tabs

The handler respects `DebugConfig::isEnabled()` and falls back to the previous exception handler when debug mode is off.

## Adapters

Bridge adapters integrate data from existing WordPress debug plugins into the WpPack toolbar:

- **`DebugBarPanelAdapter`** -- Imports panels registered via the Debug Bar plugin (`debug_bar_panels` filter)
- **`QueryMonitorCollectorAdapter`** -- Imports collectors from Query Monitor (`qm/collectors` filter)

Both adapters are no-ops when their respective plugins are not active.

## Configuration

`DebugConfig` controls when the toolbar and error handler are active:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `enabled` | `bool` | `false` | Master switch; also requires `WP_DEBUG` to be truthy |
| `showToolbar` | `bool` | `false` | Show the debug toolbar in `wp_footer` |
| `ipWhitelist` | `string[]` | `['127.0.0.1', '::1']` | Allowed client IPs |
| `roleWhitelist` | `string[]` | `['administrator']` | Allowed user roles |

The toolbar is automatically suppressed during Ajax, cron, and REST API requests.

## DI Integration

Register the `DebugServiceProvider` with the DI container to auto-wire all services and collectors:

```php
use WpPack\Component\Debug\DependencyInjection\DebugServiceProvider;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;

$builder->registerProvider(new DebugServiceProvider());
$builder->addCompilerPass(new RegisterDataCollectorsPass());
```

## Requirements

- PHP 8.2+

## Documentation

See [docs/components/debug/](../../../docs/components/debug/) for full documentation.

## License

MIT
