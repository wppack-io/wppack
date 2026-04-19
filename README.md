# WPPack — Modern WordPress Component Library

[![CI](https://img.shields.io/github/actions/workflow/status/wppack-io/wppack/ci.yml?branch=1.x)](https://github.com/wppack-io/wppack/actions/workflows/ci.yml)
[![Codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack)](https://codecov.io/gh/wppack-io/wppack)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-21759B.svg)](https://wordpress.org)

WPPack is a Symfony-inspired component library that brings modern PHP development
practices to WordPress. Type-safe, testable, cloud-native, and composable — use the
whole stack or just the components you need.

## Why WPPack?

- **Cloud-first.** Built for Lambda, Cloud Functions, Fargate, and Aurora
  Serverless. Stateless by default, with transparent reconnects (Database
  gone-away handling, Aurora DSQL OCC retry), graceful fallbacks (SQS
  messenger → synchronous, EventBridge scheduler → WP-Cron), and lazy DI
  for fast cold starts.
- **Component-level composability.** 58 components and 25 bridges, each an
  independent Composer package with its own tests and docs. Plugin and theme
  authors can adopt WPPack one package at a time (`composer require wppack/option`)
  or compose a full stack. No monolithic framework buy-in.
- **Modern WordPress wrapper via PHP Attributes and DI.** Declarative event
  listeners (`#[AsEventListener]`), option injection (`#[Option]`), and routing —
  all backed by a Symfony-style DI container with autowiring and service
  auto-discovery. Type-safe wrappers over `$wpdb`, `WP_Query`, the Options API,
  Transients, and object cache. PSR-3 / PSR-6 / PSR-11 / PSR-14 / PSR-16 / PSR-18
  compliant.
- **Unified security framework.** OAuth 2.0 / OIDC, SAML 2.0, and WebAuthn /
  Passkey are layered on a single `wppack/security` framework — `AbstractAuthenticator`,
  `TokenStorage`, `AccessDecisionManager`, `UserProvider`. Adding or swapping a
  provider requires no user-code change. SCIM 2.0 provisioning is included.
- **Quality backed by verification.** `declare(strict_types=1)` throughout, 100+
  interfaces (interface-first design), **PHPStan level 6** on the full 114k-LOC
  codebase, php-cs-fixer on PER Coding Style, and 149k LOC of tests
  (1.31:1 test/production ratio). A 16-job CI matrix runs on every push —
  PHP 8.2 / 8.3 / 8.4 / 8.5 × mysql / sqlite / postgresql / legacy wpdb —
  all green.
- **Multi-cloud, first-party bridges.** Aurora DSQL (IAM auth + OCC retry),
  Aurora RDS Data API, ElastiCache IAM, EventBridge Scheduler,
  S3 / Azure Blob / GCS storage, SES / SendGrid / Azure Communication mail,
  SQS messenger, CloudWatch / Cloudflare monitoring. 25 bridges across 8
  domains — the only WordPress component library shipping native AWS / GCP /
  Azure integration out of the box.

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
