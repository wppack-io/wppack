# WpPack Kernel

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=kernel)](https://codecov.io/github/wppack-io/wppack)

Application bootstrap for WordPress.

## Installation

```bash
composer require wppack/kernel
```

## Features

- Plugin and theme lifecycle management (register, compile, boot)
- Shared DI container across all plugins and themes
- `#[TextDomain]` attribute for automatic textdomain loading via `load_plugin_textdomain()` / `load_theme_textdomain()`

## Documentation

See [docs/components/kernel/](../../../docs/components/kernel/) for full documentation.

## License

MIT
