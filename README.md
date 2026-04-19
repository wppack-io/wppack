# WPPack - Modern WordPress Component Library

[![CI](https://img.shields.io/github/actions/workflow/status/wppack-io/wppack/ci.yml?branch=master)](https://github.com/wppack-io/wppack/actions/workflows/ci.yml)
[![Codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack)](https://codecov.io/gh/wppack-io/wppack)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)

WPPack brings modern PHP development practices to WordPress. Built on PHP 8.2+ with
Symfony-inspired patterns, it provides a set of decoupled components and plugins that
replace traditional WordPress APIs with type-safe, testable, and well-structured
alternatives.

## Packages

### Infrastructure Layer

| Package | Description |
|---------|-------------|
| `wppack/handler` | Modern PHP request handler (front controller) |
| `wppack/hook` | Attribute-based hook (action/filter) registration |
| `wppack/dependency-injection` | PSR-11 service container with autowiring |
| `wppack/event-dispatcher` | PSR-14 event dispatcher |
| `wppack/filesystem` | WP_Filesystem DI wrapper |
| `wppack/kernel` | Application bootstrap |
| `wppack/option` | Type-safe wp_options wrapper |
| `wppack/transient` | Type-safe Transient API wrapper |
| `wppack/role` | Role and capability management |
| `wppack/templating` | Template engine abstraction |
| ↳ `wppack/twig-templating` | Twig bridge |
| `wppack/stopwatch` | Code execution timing and profiling |
| `wppack/logger` | PSR-3 compatible logger |
| ↳ `wppack/monolog-logger` | Monolog bridge |
| `wppack/mime` | MIME type detection and extension mapping |
| `wppack/site` | Multisite management (blog switching, context, site queries) |

### Abstraction Layer

| Package | Description |
|---------|-------------|
| `wppack/cache` | PSR-6/PSR-16 cache abstraction |
| ↳ `wppack/redis-cache` | Redis/Valkey adapter |
| ↳ `wppack/dynamodb-cache` | DynamoDB adapter |
| ↳ `wppack/memcached-cache` | Memcached adapter |
| ↳ `wppack/apcu-cache` | APCu adapter |
| ↳ `wppack/elasticache-auth` | ElastiCache IAM authentication |
| `wppack/database` | Type-safe $wpdb wrapper and migrations |
| `wppack/query` | WP_Query builder |
| `wppack/security` | Authentication and authorization framework |
| ↳ `wppack/saml-security` | SAML 2.0 SP bridge |
| ↳ `wppack/oauth-security` | OAuth 2.0 / OpenID Connect bridge |
| `wppack/sanitizer` | Input sanitization |
| `wppack/escaper` | Output escaping |
| `wppack/http-client` | HTTP client abstraction |
| `wppack/http-foundation` | Request/Response abstraction |
| `wppack/mailer` | Mail transport abstraction |
| ↳ `wppack/amazon-mailer` | Amazon SES transport |
| ↳ `wppack/azure-mailer` | Azure Communication Services transport |
| ↳ `wppack/sendgrid-mailer` | SendGrid transport |
| `wppack/messenger` | Transport-agnostic message bus |
| ↳ `wppack/sqs-messenger` | Amazon SQS transport |
| `wppack/serializer` | Object serialization (Normalizer chain) |
| `wppack/options-resolver` | Options resolver (Symfony OptionsResolver extension) |
| `wppack/debug` | Debug and profiling |
| `wppack/storage` | Object storage abstraction |
| ↳ `wppack/s3-storage` | Amazon S3 adapter |
| ↳ `wppack/azure-storage` | Azure Blob Storage adapter |
| ↳ `wppack/gcs-storage` | Google Cloud Storage adapter |

### Feature Layer

| Package | Description |
|---------|-------------|
| `wppack/admin` | Admin page and menu registration |
| `wppack/rest` | REST API endpoint registration |
| `wppack/routing` | URL routing |
| `wppack/post-type` | Custom post type and meta registration |
| `wppack/scheduler` | Trigger-based task scheduler |
| ↳ `wppack/eventbridge-scheduler` | Amazon EventBridge bridge |
| `wppack/console` | WP-CLI command framework |
| `wppack/shortcode` | Shortcode registration |
| `wppack/nonce` | CSRF token management |
| `wppack/asset` | Asset management (scripts and styles) |
| `wppack/ajax` | Admin Ajax handler |
| `wppack/scim` | SCIM 2.0 provisioning |
| `wppack/wpress` | .wpress archive format operations |

### Application Layer

| Package | Description |
|---------|-------------|
| `wppack/plugin` | Plugin lifecycle management |
| `wppack/theme` | Theme development framework |
| `wppack/widget` | Widget registration |
| `wppack/setting` | Settings API wrapper |
| `wppack/user` | User management |
| `wppack/block` | Block editor integration |
| `wppack/media` | Media management |
| `wppack/comment` | Comment management |
| `wppack/taxonomy` | Custom taxonomy registration |
| `wppack/navigation-menu` | Navigation menu management |
| `wppack/feed` | RSS/Atom feed management |
| `wppack/oembed` | oEmbed provider management |
| `wppack/site-health` | Site Health integration |
| `wppack/dashboard-widget` | Dashboard widget registration |
| `wppack/translation` | Internationalization utilities |

### Plugins

| Package | Description |
|---------|-------------|
| `wppack/debug-plugin` | Debug toolbar plugin |
| `wppack/redis-cache-plugin` | Redis cache plugin |
| `wppack/amazon-mailer-plugin` | Amazon SES mailer plugin |
| `wppack/s3-storage-plugin` | Amazon S3 media storage plugin |
| `wppack/eventbridge-scheduler-plugin` | EventBridge scheduler plugin |
| `wppack/saml-login-plugin` | SAML 2.0 SSO login plugin |
| `wppack/scim-plugin` | SCIM 2.0 provisioning plugin |

## Installation

```bash
composer require wppack/hook
```

Each component can be installed independently. Install only what you need.

## Architecture

```
Plugin Layer        wppack/*-plugin
                         |
Application Layer   wppack/plugin, wppack/theme, wppack/media, ...
                         |
Feature Layer       wppack/admin, wppack/rest, wppack/scheduler, ...
                         |
Abstraction Layer   wppack/cache, wppack/mailer, wppack/messenger, ...
                         |
Infrastructure      wppack/handler, wppack/kernel, wppack/dependency-injection
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
