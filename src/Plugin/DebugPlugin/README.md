# wppack/debug-plugin

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=debug_plugin)](https://codecov.io/github/wppack-io/wppack)

WordPress plugin for debug toolbar and profiler. A thin bootstrap layer that activates the `wppack/debug` component as a WordPress plugin for local development.

## Architecture

DebugPlugin is a thin wrapper around the Debug component:

- **Data collectors** are provided by `wppack/debug` (`DebugServiceProvider`)
- **Toolbar rendering** is provided by `wppack/debug` (`ToolbarRenderer`, `ToolbarSubscriber`)
- **Profiling** is provided by `wppack/debug` (`Profiler`, `Profile`)
- **DebugPlugin** provides only: plugin bootstrap, `DebugConfig` override (`enabled: true`, `showToolbar: true`), and compiler pass registration

## Installation

```bash
composer require wppack/debug-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x
- `WP_DEBUG` must be `true`

## Configuration

The plugin automatically enables the debug toolbar when `WP_DEBUG` is `true`. No additional configuration is required.

The toolbar is displayed for administrators accessing from localhost (`127.0.0.1` / `::1`) by default. These defaults come from `DebugConfig` in the Debug component.

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
