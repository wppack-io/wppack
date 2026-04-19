# WPPack — Modern WordPress Component Library

[![CI](https://img.shields.io/github/actions/workflow/status/wppack-io/wppack/ci.yml?branch=1.x)](https://github.com/wppack-io/wppack/actions/workflows/ci.yml)
[![Codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack)](https://codecov.io/gh/wppack-io/wppack)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-21759B.svg)](https://wordpress.org)

WPPack is a Symfony-inspired component library that brings modern PHP
development practices to WordPress **without replacing it**. Adopt a single
component or a composed stack — every package is independently testable,
type-safe, PSR-* compliant, and fully compatible with the WordPress plugin
and theme ecosystem.

## Why WPPack?

### For plugin and theme authors

- **Compatible with WordPress, not a rewrite of it.** WPPack wraps WordPress
  APIs; it never replaces them. `$wpdb`, `WP_Query`, hooks, REST controllers,
  WP-CLI commands, `wp_mail()`, and the object cache all keep working the way
  third-party plugins and themes expect. Our EventDispatcher dispatches
  through `$wp_filter`, our Cache wraps `wp_cache_*`, our Mailer calls
  `wp_mail()`. You can drop WPPack into a site with a dozen existing plugins
  and nothing breaks.
- **Adopt piece by piece — no bloat.** Each component is an independent
  Composer package focused on one concern. `composer require wppack/option`
  for type-safe options, `wppack/database` for a portable `$wpdb` replacement,
  `wppack/mailer` to swap in SES / SendGrid / Azure Communication without
  touching call sites. No DI container, no mailer, no hidden dependencies
  pulled in if you didn't ask for them. Most plugins adopt 2-3 packages, not
  all 58.
- **Type-safe WordPress with modern PHP attributes.** Declarative event
  listeners (`#[AsEventListener]`), option injection (`#[Option]`), and
  routing — backed by a DI container with autowiring and service
  auto-discovery. Type-safe wrappers over `$wpdb`, `WP_Query`, the Options
  API, Transients, and object cache follow PSR-3 / PSR-6 / PSR-11 / PSR-14 /
  PSR-16 / PSR-18 contracts, so they mock cleanly and swap cleanly. IDE
  autocomplete, PHPStan guarantees, and refactoring safety you can't get
  from WordPress core alone.
- **Quality you can depend on.** **PHPStan level 6** on 114k LOC of source,
  php-cs-fixer on PER Coding Style, 149k LOC of tests (1.31:1 test/production
  ratio), and a 16-job CI matrix — PHP 8.2 / 8.3 / 8.4 / 8.5 × mysql / sqlite /
  postgresql / legacy wpdb — green on every push. 122 interfaces and
  `declare(strict_types=1)` throughout.
- **Debug toolbar and profiler out of the box.** `composer require
  wppack/debug-plugin` activates a debug toolbar with 17 data collectors
  (database / cache / DI / events / HTTP / mail / REST / Ajax / shortcode /
  routing / memory / request). An intermediate page on every redirect
  preserves post-redirect profiling data. A drop-in catches fatal errors and
  uncaught exceptions *before* the DI container boots — no more white screen.

### For WordPress site operators

- **Commercial-grade infrastructure plugins, MIT licensed.** Every first-party
  plugin is engineered for production workloads and tested in the same 16-job
  CI matrix as the core components: Redis Object Cache, S3 Media Storage,
  Amazon SES Mailer, EventBridge Scheduler, CloudWatch Monitoring — plus SSO
  login plugins for SAML 2.0, OAuth 2.0 / OIDC, and WebAuthn / Passkey, and
  SCIM 2.0 provisioning for directory sync. Deploy only the ones you need.
- **Cloud-ready and multi-cloud.** 25 first-party bridges across AWS / GCP /
  Azure cover storage (S3 / GCS / Azure Blob), mail (SES / SendGrid / Azure
  Communication), cache (Redis / Valkey / DynamoDB / Memcached / APCu +
  ElastiCache IAM), queue (SQS), scheduler (EventBridge), and monitoring
  (CloudWatch / Cloudflare). Stateless by default with gone-away reconnect,
  OCC retry, and graceful fallbacks (SQS → synchronous, EventBridge →
  WP-Cron) — Lambda / Cloud Functions / Fargate just work. Swap providers per
  domain without touching application code.
- **Multi-engine database, pick per environment.** Write MySQL dialect; run on
  MySQL, SQLite, PostgreSQL, or Aurora DSQL unchanged thanks to an AST-based
  query translator. SQLite for local / CI speed, PostgreSQL or Aurora DSQL for
  production scale — no code changes. Native prepared statements on every
  engine, reader/writer affinity with read-your-own-writes semantics,
  transparent reconnect on `server has gone away`.
- **Observability baked in.** PSR-3 logger with a Monolog bridge, PSR-14 event
  dispatcher on top of the WordPress hook backend for APM integration, and a
  per-query slow-log threshold with structured, param-redacted context.

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

**Composer-based installation is recommended for plugins as well.** A
Composer install resolves the shared component dependency graph into
`vendor/` once, instead of duplicating the same components inside every
plugin bundled under `web/wp-content/plugins/`. That keeps your web-root
footprint small and avoids version skew between plugins that share a
dependency.

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

See [CONTRIBUTING.md](CONTRIBUTING.md) for the contribution process (bug
reports, pull requests, review expectations) and
[coding-standards.md](coding-standards.md) for code style, commit
conventions, testing, and the checklists for adding new components or
plugins.

## License

MIT License. See [LICENSE](LICENSE) for details.
