# WPPack — Modern WordPress Component Library

[![CI](https://img.shields.io/github/actions/workflow/status/wppack-io/wppack/ci.yml?branch=1.x)](https://github.com/wppack-io/wppack/actions/workflows/ci.yml)
[![Codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack)](https://codecov.io/gh/wppack-io/wppack)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-21759B.svg)](https://wordpress.org)

WPPack is a Symfony-inspired component library that brings modern PHP development
practices to WordPress. Adopt one component or the whole stack — every package is
independently testable, type-safe, and cloud-ready.

## Why WPPack?

> **Scope: infrastructure, not features.** WPPack intentionally stays at the
> foundation layer — database, cache, mail, storage, auth, observability,
> scheduling. Upper-layer features (forms, e-commerce, page builders) live
> in your own plugins or third-party products, built on top of WPPack.

### For plugin and theme authors

- **Adopt piece by piece — no framework lock-in.** Drop a single component into
  an existing codebase without rewriting it. `composer require wppack/option`
  for type-safe options, `wppack/database` for a portable `$wpdb` replacement,
  `wppack/mailer` to swap in SES / SendGrid / Azure Communication without
  touching call sites. 58 components and 25 bridges, each an independent
  Composer package with its own tests and docs.
- **Type-safe WordPress with modern PHP attributes.** Declarative event listeners
  (`#[AsEventListener]`), option injection (`#[Option]`), and routing — all
  backed by a Symfony-style DI container with autowiring and service
  auto-discovery. Type-safe wrappers over `$wpdb`, `WP_Query`, the Options API,
  Transients, and object cache. IDE autocomplete, PHPStan guarantees, and
  refactoring safety you can't get from WordPress core.
- **Quality you can depend on.** **PHPStan level 6** on 114k LOC of source.
  php-cs-fixer on PER Coding Style. 149k LOC of tests (1.31:1 test/production
  ratio). A 16-job CI matrix — PHP 8.2 / 8.3 / 8.4 / 8.5 × mysql / sqlite /
  postgresql / legacy wpdb — runs on every push, all green. 122 interfaces
  and `declare(strict_types=1)` throughout.
- **Symfony-grade debug toolbar out of the box.** `composer require
  wppack/debug-plugin` turns on a Symfony-profiler-style toolbar with 17
  data collectors — database queries, object cache operations, DI container
  services, dispatched events, HTTP client calls, sent mail, REST / Ajax
  / shortcode / routing activity, memory and request profiles. An
  intermediate page on every redirect keeps post-redirect profiling data
  accessible. Fatal errors and uncaught exceptions are caught by a
  drop-in before the DI container even boots, so you get a readable
  error page instead of a white screen.

- **Infrastructure plugins at commercial-offering quality.** WPPack's
  first-party plugins — Redis Object Cache, S3 Media Storage, Amazon SES
  Mailer, EventBridge Scheduler, CloudWatch Monitoring — are built at a
  quality level comparable to paid commercial WordPress plugins, but MIT
  licensed. Engineered for production workloads, tested in the same 16-job CI
  matrix as the core components.
- **Robust authentication, ready to deploy.** SAML 2.0, OAuth 2.0 / OIDC, and
  WebAuthn / Passkey each ship as a dedicated login plugin layered on a
  unified security framework. Deploy only the provider you need, or combine
  them freely. SCIM 2.0 provisioning included for directory sync.
- **Cloud-ready without glue code.** Aurora DSQL (IAM auth + OCC retry), Aurora
  RDS Data API, ElastiCache IAM, EventBridge Scheduler, S3 / Azure Blob / GCS
  storage, SES / SendGrid / Azure Communication mail, SQS messenger, CloudWatch
  / Cloudflare monitoring — 25 first-party bridges across 8 domains. Stateless
  by default; cold starts, gone-away reconnect, OCC retry, and graceful
  fallbacks (SQS → synchronous, EventBridge → WP-Cron) are built in so Lambda
  / Cloud Functions / Fargate just work.
- **Multi-engine database, pick per environment.** Write MySQL dialect; run on
  MySQL, SQLite, PostgreSQL, or Aurora DSQL unchanged thanks to an AST-based
  query translator. Use SQLite for local / CI speed, PostgreSQL or Aurora DSQL
  for production scale — no code changes. Native prepared statements on every
  engine.
- **Multi-cloud, no vendor lock-in.** 25 bridges across AWS / GCP / Azure let
  you choose your cloud provider per domain: S3 or GCS or Azure Blob for
  storage; SES or Azure Communication or SendGrid for mail; Redis or
  DynamoDB or Memcached or APCu for cache. Migrate between providers by
  swapping a bridge, not the application.
- **Observability out of the box.** PSR-3 logger (Monolog bridge ready), PSR-14
  event dispatcher on top of `$wp_filter`, per-query slow-log threshold,
  CloudWatch / Cloudflare monitoring bridges for metrics, and a debug toolbar
  with database / cache / event / mailer collectors.

## Feature Highlights

- **Database.** AST-based MySQL → SQLite / PostgreSQL / Aurora DSQL query
  translator, native prepared statements on every engine, reader/writer affinity
  with read-your-own-writes, transparent reconnect on `server has gone away`,
  PSR-3 logging with param redaction, and PSR-14 events for APM integration.
- **Security.** Pluggable AuthN / AuthZ framework with first-party bridges for
  OAuth 2.0 / OIDC, SAML 2.0, and WebAuthn / Passkey. SCIM 2.0 provisioning.
- **Cache.** PSR-6 and PSR-16 façade over Redis / Valkey, Memcached, APCu, and
  DynamoDB, with an object-cache drop-in that auto-discovers the adapter from
  the `CACHE_DSN` environment variable.
- **Observability.** PSR-3 logger with a Monolog bridge, PSR-14 event dispatcher
  built on the WordPress hook backend, per-query slow-log threshold, CloudWatch /
  Cloudflare monitoring bridges, and a debug toolbar with database / cache /
  event / mailer collectors.

## Installation

Each component is an independent Composer package. Install only what you need.

```bash
# A single utility inside an existing plugin
composer require wppack/option

# A composed stack
composer require wppack/kernel wppack/dependency-injection wppack/event-dispatcher

# A ready-to-use WordPress plugin
composer require wppack/saml-login-plugin
```

See [`docs/components/`](docs/components/) for the full catalogue of components
and their package names, and [`docs/plugins/`](docs/plugins/) for distributable
plugins.

## Documentation

- [Architecture overview](docs/architecture/) — infrastructure, multi-cloud,
  and serverless design notes.
- [Component catalogue](docs/components/) — every package, its purpose, and its
  dependencies.
- [Plugins](docs/plugins/) — ready-to-install WordPress plugins built on top of
  the components.

## Requirements

- **PHP 8.2 or higher**
- **WordPress 6.3 or higher** — the first release officially supporting PHP 8.2.
  - WordPress 6.5+ recommended for full PHP 8.3 compatibility.
  - WordPress 6.8+ recommended for PHP 8.4 compatibility (tested in CI).

## Development

```bash
# Install dependencies
composer install

# Start backing services (MySQL, PostgreSQL, Redis, etc.)
docker compose up -d --wait

# Static analysis (PHPStan level 6)
vendor/bin/phpstan analyse

# Coding style (PER Coding Style)
vendor/bin/php-cs-fixer fix --dry-run --diff

# Tests (default: runs against MySQL via wpdb)
vendor/bin/phpunit

# Tests against a specific engine
DATABASE_DSN='sqlite:///tmp/wppack-test.db' vendor/bin/phpunit
DATABASE_DSN='pgsql://wppack:wppack@127.0.0.1:5433/wppack_test' vendor/bin/phpunit
```

## Contributing

The repository is in active design phase on the `1.x` branch. Backward
compatibility is not preserved across commits; API changes, parameter
reordering, and renames may happen at any time until a stable release is
tagged.

## License

MIT License. See [LICENSE](LICENSE) for details.
