# WpPack - Modern WordPress Component Library

[![CI](https://img.shields.io/github/actions/workflow/status/wppack-io/wppack/ci.yml?branch=master)](https://github.com/wppack-io/wppack/actions/workflows/ci.yml)
[![Codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack)](https://codecov.io/gh/wppack-io/wppack)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)

WpPack brings modern PHP development practices to WordPress. Built on PHP 8.2+ with
Symfony-inspired patterns, it provides a set of decoupled components and plugins that
replace traditional WordPress APIs with type-safe, testable, and well-structured
alternatives.

## Packages

### Components

| Package | Description |
|---------|-------------|
| `wppack/hook` | Type-safe hook (action/filter) registration via attributes |
| `wppack/dependency-injection` | PSR-11 compatible dependency injection container |
| `wppack/config` | Configuration management |
| `wppack/event-dispatcher` | PSR-14 compatible event dispatcher |
| `wppack/filesystem` | Filesystem abstraction |
| `wppack/kernel` | Application kernel and bootstrap |
| `wppack/mailer` | Mail abstraction |
| `wppack/messenger` | Async message bus (SQS/Lambda) |
| `wppack/scheduler` | Cron and recurring task scheduling (EventBridge) |
| `wppack/media` | Media and attachment management |
| `wppack/admin` | Admin page and menu registration |
| `wppack/rest` | REST API endpoint registration |
| `wppack/post-type` | Custom post type registration |
| `wppack/query` | Type-safe query builder |
| `wppack/command` | WP-CLI command registration |
| `wppack/cache` | PSR-6/PSR-16 cache abstraction |
| `wppack/database` | Database abstraction and migrations |
| `wppack/security` | Authentication and authorization |
| `wppack/sanitizer` | Input sanitization |
| `wppack/validator` | Input validation |
| `wppack/http-client` | HTTP client abstraction |
| `wppack/http-foundation` | Request/Response abstraction |
| `wppack/plugin` | Plugin lifecycle management |
| `wppack/theme` | Theme lifecycle management |
| `wppack/widget` | Widget registration |
| `wppack/setting` | Settings API abstraction |
| `wppack/user` | User management |
| `wppack/block` | Gutenberg block registration |
| `wppack/comment` | Comment management |
| `wppack/taxonomy` | Custom taxonomy registration |
| `wppack/role` | Role and capability management |
| `wppack/templating` | Template engine abstraction |
| `wppack/logger` | PSR-3 compatible logger |
| `wppack/option` | Options API abstraction |
| `wppack/transient` | Transients API abstraction |
| `wppack/nonce` | Nonce management |
| `wppack/shortcode` | Shortcode registration |
| `wppack/routing` | URL routing |
| `wppack/ajax` | AJAX handler registration |
| `wppack/debug` | Debug toolbar and utilities |
| `wppack/navigation-menu` | Navigation menu management |
| `wppack/feed` | RSS/Atom feed management |
| `wppack/oembed` | oEmbed provider management |
| `wppack/site-health` | Site Health integration |
| `wppack/dashboard-widget` | Dashboard widget registration |
| `wppack/translation` | Internationalization utilities |

### Plugins

| Package | Description |
|---------|-------------|
| `wppack/scheduler-plugin` | WordPress plugin for scheduler integration |
| `wppack/s3-storage-plugin` | Amazon S3 media storage plugin |
| `wppack/amazon-mailer-plugin` | Amazon SES mailer plugin |

## Installation

```bash
composer require wppack/hook
```

Each component can be installed independently. Install only what you need.

## Architecture

```
Plugin Layer        wppack/scheduler-plugin, wppack/s3-storage-plugin, ...
                         |
Feature Layer       wppack/messenger, wppack/scheduler, wppack/mailer, ...
                         |
Abstraction Layer   wppack/hook, wppack/event-dispatcher, wppack/cache, ...
                         |
Infrastructure      wppack/kernel, wppack/dependency-injection, wppack/config
                         |
                    WordPress Core
```

## Documentation

See the [docs/](docs/) directory for detailed documentation on each package.

## Requirements

- PHP 8.2 or higher
- WordPress 6.0 or higher

## Development

```bash
# Install dependencies
composer install

# Run static analysis
vendor/bin/phpstan analyse

# Check coding standards
vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix coding standards
vendor/bin/php-cs-fixer fix

# Run tests
vendor/bin/phpunit
```

## License

MIT License. See [LICENSE](LICENSE) for details.
