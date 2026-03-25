# WpPack Debug

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=debug)](https://codecov.io/github/wppack-io/wppack)

> [!WARNING]
> **This component is intended for development environments only.** It is automatically disabled when `wp_get_environment_type()` returns `'production'`. Sensitive data (passwords, tokens, API keys) in POST parameters, cookies, headers, and SQL queries is automatically masked, but configure `ipWhitelist` and `roleWhitelist` to restrict access.

Web debug toolbar and error handler for WordPress. Provides a Symfony-inspired profiling toolbar, data collectors, panel renderers, stopwatch, and a styled exception handler.

## Installation

```bash
composer require wppack/debug
```

## Quick Start

```php
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Stopwatch\Stopwatch;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Profiler\Profiler;
use WpPack\Component\Debug\DataCollector\RequestDataCollector;
use WpPack\Component\Debug\DataCollector\DatabaseDataCollector;
use WpPack\Component\Debug\DataCollector\MemoryDataCollector;
use WpPack\Component\Debug\DataCollector\StopwatchDataCollector;
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
    new StopwatchDataCollector($stopwatch),
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

| Collector | Indicator | Description |
|-----------|-----------|-------------|
| `RequestDataCollector` | Method + status code | HTTP method, URL, headers, GET/POST params, cookies |
| `StopwatchDataCollector` | Total time | Request duration, WordPress lifecycle phases, stopwatch events |
| `MemoryDataCollector` | Peak memory | Current/peak memory, limit, lifecycle snapshots |
| `DatabaseDataCollector` | Query count | SQL queries, execution time, duplicate/slow query detection |
| `CacheDataCollector` | Hit rate | Object cache hits/misses, transient set/delete counts |
| `HttpClientDataCollector` | Request count | Outgoing HTTP requests with timing, status, response size |
| `RouterDataCollector` | Template name | Matched rewrite rule, template, query vars, conditional tags (FSE/classic) |
| `PluginDataCollector` | Plugin count | Active plugins, MU plugins, drop-ins |
| `ThemeDataCollector` | Theme name | Active theme, parent theme, block/classic detection |
| `EventDataCollector` | Hook firings | WordPress hooks monitoring, top hooks, orphan hooks |
| `AjaxDataCollector` | AJAX count | WordPress AJAX request tracking |
| `RestDataCollector` | Endpoint count | REST API endpoint information |
| `AssetDataCollector` | Asset count | Registered/enqueued scripts and styles |
| `AdminDataCollector` | Admin page | Admin screen information |
| `LoggerDataCollector` | Log count | Log messages, PHP errors, WordPress deprecation/doing_it_wrong notices |
| `DumpDataCollector` | Dump count | Captured dump() calls with file/line info |
| `MailDataCollector` | Email count | Emails sent via wp_mail(), success/failure tracking |
| `SecurityDataCollector` | Username | Current user, roles, capabilities, authentication, nonce tracking |
| `WidgetDataCollector` | Widget count | Registered widgets and active sidebars |
| `ContainerDataCollector` | Service count | DI container service information |
| `ShortcodeDataCollector` | Shortcode count | Registered shortcodes |
| `FeedDataCollector` | Feed count | RSS/Atom feed information |
| `EnvironmentDataCollector` | Environment type | PHP/server environment details |
| `SchedulerDataCollector` | Task count | Scheduled tasks and cron events |
| `TranslationDataCollector` | Missing count | Text domains, translation lookups, missing translation detection |
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

    public function getIndicatorValue(): string
    {
        return (string) ($this->data['custom_metric'] ?? 0);
    }
}
```

When using the DI container, the `RegisterDataCollectorsPass` compiler pass auto-discovers classes tagged with `#[AsDataCollector]` and registers them by priority.

## Panel Renderers

Each toolbar panel (indicator + sidebar content) is rendered by a `RendererInterface` implementation. Extend `AbstractPanelRenderer` for built-in UI helpers (tables, performance cards, timeline bars, formatters):

```php
use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;

#[AsPanelRenderer(name: 'my_collector')]
class MyPanelRenderer extends AbstractPanelRenderer
{
    public function getName(): string
    {
        return 'my_collector';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, 'my_collector');

        return '<div class="wpd-panel-content">'
            . $this->renderKeyValueSection('Metrics', [
                'Custom Metric' => (string) ($data['custom_metric'] ?? 0),
            ])
            . '</div>';
    }
}
```

The `RegisterPanelRenderersPass` compiler pass auto-discovers classes tagged with `#[AsPanelRenderer]`.

## Error Handlers

The Debug component provides four error handlers:

- **`ExceptionHandler`** — Intercepts uncaught exceptions via `set_exception_handler()` and renders a styled error page with exception details, syntax-highlighted code snippets, full stack trace, previous exception chain, and request/environment/performance tabs. Falls back to the previous exception handler when debug mode is off.
- **`WpDieHandler`** — Intercepts `wp_die()` via `wp_die_handler` / `wp_die_ajax_handler` / `wp_die_json_handler` filters and renders context-appropriate error pages (HTML, Ajax, JSON). Extracts the original call site from the backtrace for accurate file/line display.
- **`RedirectHandler`** — Intercepts redirects via `wp_redirect` filter and renders an intermediate page with profiling data. Uses a shutdown function to display the page after WordPress cancels the redirect. In full-boot mode, includes the debug toolbar.
- **`FatalErrorHandler`** — Implements `WP_Fatal_Error_Handler` to catch fatal PHP errors (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR) at shutdown.

All handlers use a unified architecture with nullable DI dependencies, allowing them to operate in two modes: lightweight (early boot via drop-in, without DI) and full (after `DebugPlugin::boot()`, with DI-injected dependencies including toolbar rendering).

## Adapters

Bridge adapter integrates data from existing WordPress debug plugins into the WpPack toolbar:

- **`DebugBarPanelAdapter`** -- Imports panels registered via the Debug Bar plugin (`debug_bar_panels` filter)

The adapter is a no-op when the Debug Bar plugin is not active.

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

Register the `DebugServiceProvider` with the DI container to auto-wire all services, collectors, and panel renderers:

```php
use WpPack\Component\Debug\DependencyInjection\DebugServiceProvider;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WpPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;

$builder->registerProvider(new DebugServiceProvider());
$builder->addCompilerPass(new RegisterDataCollectorsPass());
$builder->addCompilerPass(new RegisterPanelRenderersPass());
```

## Requirements

- PHP 8.2+
- `wppack/stopwatch` — Timer and lifecycle profiling

## Documentation

See [docs/components/debug/](../../../docs/components/debug/) for full documentation.

## Third-party Notices

This component includes SVG icon data from [Lucide Icons](https://lucide.dev), licensed under the [ISC License](https://github.com/lucide-icons/lucide/blob/main/LICENSE).

## License

MIT
