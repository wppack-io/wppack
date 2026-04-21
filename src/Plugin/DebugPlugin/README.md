# wppack/debug-plugin

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=debug_plugin)](https://codecov.io/github/wppack-io/wppack)

WordPress plugin for debug toolbar and profiler. A thin bootstrap layer that activates the `wppack/debug` component as a WordPress plugin for local development.

## Architecture

DebugPlugin is a thin wrapper around the Debug component:

- **Data collectors** are provided by `wppack/debug` (`DebugServiceProvider`)
- **Toolbar rendering** is provided by `wppack/debug` (`ToolbarRenderer`, `ToolbarSubscriber`)
- **Profiling** is provided by `wppack/debug` (`Profiler`, `Profile`)
- **Redirect handling** is provided by `wppack/debug` (`RedirectHandler`) — intercepts redirects and shows an intermediate page with profiling data
- **Fatal error handling** is provided by `wppack/debug` (`FatalErrorHandler`) via the `fatal-error-handler.php` drop-in
- **Early exception handling** is provided by `wppack/debug` (`ExceptionHandler` in lightweight mode) via the same drop-in — catches uncaught exceptions before the DI container is available
- **Early redirect handling** is provided by `wppack/debug` (`RedirectHandler` in lightweight mode) via the same drop-in — intercepts redirects before the DI container is available
- **DebugPlugin** provides only: plugin bootstrap, `DebugConfig` override (`enabled: true`, `showToolbar: true`), compiler pass registration, and drop-in management

## Installation

```bash
composer require wppack/debug-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.7 or higher
- `WP_DEBUG` must be `true`

## Configuration

The plugin automatically enables the debug toolbar when `WP_DEBUG` is `true`. No additional configuration is required.

The toolbar is displayed for administrators accessing from localhost (`127.0.0.1` / `::1`) by default. These defaults come from `DebugConfig` in the Debug component.

### Fatal Error Handler Drop-in

On activation, the plugin copies `fatal-error-handler.php` to `wp-content/`. This drop-in registers three services at early boot:

1. **ExceptionHandler** — registered via `set_exception_handler()` at drop-in load time (without DI dependencies). Catches uncaught exceptions thrown during plugin loading, DI container compilation, and `Kernel::boot()`. Once `DebugPlugin::boot()` runs, the full `ExceptionHandler` (with DI dependencies) overwrites it, keeping the early instance as the previous handler.
2. **RedirectHandler** — intercepts redirects via `wp_redirect` filter at drop-in load time (lightweight mode without toolbar). Once `DebugPlugin::boot()` runs, the full `RedirectHandler` (with toolbar rendering) replaces it.
3. **FatalErrorHandler** — returned as the `WP_Fatal_Error_Handler` implementation. Catches fatal PHP errors (E_ERROR, E_PARSE, etc.) at shutdown.

Both use the Debug component's `ErrorRenderer` for detailed error pages.

On deactivation, the drop-in is removed (only if it was installed by WPPack).

### `WPPACK_DEBUG_ENABLED` Kill Switch

In serverless or read-only environments where the drop-in file cannot be deleted, use the `WPPACK_DEBUG_ENABLED` constant to disable it:

```php
// wp-config.php
define('WPPACK_DEBUG_ENABLED', false);
```

| State | Behavior |
|-------|----------|
| Not defined + `WP_DEBUG=true` | Enabled (custom handler) |
| Not defined + `WP_DEBUG=false` | Disabled (WordPress default) |
| `= false` | Force disabled (kill switch) |
| `= true` + `WP_DEBUG=true` | Enabled |
| `= true` + `WP_DEBUG=false` | Disabled (`WP_DEBUG` takes precedence) |

### Safety Guards

The plugin will **not** load when:

- `WP_DEBUG` is `false` or not defined
- `wp_get_environment_type()` returns `'production'`
- The request is from a non-whitelisted IP
- The current user does not have an allowed role

## Documentation

See [full documentation](../../docs/plugins/debug-plugin.md) for details.

## License

MIT
