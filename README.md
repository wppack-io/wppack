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

- **Cloud-first.** Designed to run on Lambda, Cloud Functions, Fargate, and Aurora
  Serverless. Stateless by default, with gone-away reconnects, OCC retry, cold-start
  friendly DI, and no "works on my VPS" assumptions.
- **Composable by design.** Plugin and theme authors can adopt WPPack one package at
  a time — `composer require wppack/option` if that is all you need. There is no
  monolithic framework buy-in; every component is a decoupled Composer package.
- **Modern WordPress wrapper.** Attribute-driven hook and route registration
  (`#[Hook]`, `#[Route]`), a DI container with autowiring, and type-safe wrappers
  around `$wpdb`, `WP_Query`, the Options API, Transients, and object cache. PSR-3 /
  PSR-6 / PSR-11 / PSR-14 / PSR-16 compliant throughout.
- **Unified security framework.** OAuth 2.0 / OIDC, SAML 2.0, and WebAuthn / Passkey
  are not reinvented per provider — they are bridges on top of a single
  `wppack/security` framework (`AbstractAuthenticator`, `TokenStorage`,
  `AccessDecisionManager`, `UserProvider`). Adding or swapping a provider does not
  touch user code.
- **Quality-first.** PHPStan at `level: max`, php-cs-fixer on PER Coding Style, and a
  test matrix of 9,000+ tests across 4 PHP versions (8.2 / 8.3 / 8.4 / 8.5) × 4
  database engines (mysql / sqlite / postgresql / legacy wpdb) runs on every
  push — 16 jobs, all green.
- **First-class cloud integrations.** Aurora DSQL (IAM token + OCC retry), Aurora
  RDS Data API, ElastiCache IAM auth, EventBridge Scheduler, S3 / Azure Blob / GCS
  storage, SES / SendGrid / Azure Communication mail, SQS messenger, and CloudWatch
  / Cloudflare monitoring all ship as first-party bridges.

## Quick Example

Type-safe `$wpdb` replacement with native prepared statements and multi-engine
support:

```php
use WPPack\Component\Database\DatabaseManager;

$db = new DatabaseManager();

// DBAL-style fetch API — values are always bound, never spliced into SQL
$user = $db->fetchAssociative(
    'SELECT * FROM {$db->users} WHERE user_email = %s',
    [$email],
);

$db->transactional(function (DatabaseManager $db) use ($postId) {
    $db->update('posts', ['post_status' => 'publish'], ['ID' => $postId]);
    $db->insert('postmeta', ['post_id' => $postId, 'meta_key' => 'published_at']);
});
```

Attribute-driven hooks with dependency injection:

```php
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Option\OptionManager;

final class AnalyticsListener
{
    public function __construct(private OptionManager $options) {}

    #[Action('save_post')]
    public function onPostSaved(int $postId): void
    {
        $this->options->set('last_saved_post_id', $postId);
    }
}
```

## Feature Highlights

- **Database.** AST-based MySQL → SQLite / PostgreSQL / Aurora DSQL query translator,
  native prepared statements on every engine, reader/writer affinity with
  read-your-own-writes, transparent reconnect on `server has gone away`, PSR-3
  logging with param redaction, and PSR-14 events for APM integration.
- **Security.** Pluggable AuthN/AuthZ framework with first-party bridges for OAuth 2.0
  / OIDC, SAML 2.0, and WebAuthn / Passkey. Provisioning via SCIM 2.0 included.
- **Cache.** PSR-6 and PSR-16 façade over Redis / Valkey, Memcached, APCu, and
  DynamoDB, with an object-cache drop-in that auto-discovers the adapter from
  `CACHE_DSN`.
- **Observability.** PSR-3 logger with a Monolog bridge, PSR-14 event dispatcher
  built on the WordPress hook backend, per-query slow-log threshold, CloudWatch /
  Cloudflare monitoring bridges, and a debug toolbar with database / cache / event
  / mailer / hook collectors.

## Installation

Each component is an independent Composer package. Install only what you need.

```bash
# A single utility inside an existing plugin
composer require wppack/option

# Composed stack for a serious plugin or site
composer require wppack/hook wppack/dependency-injection wppack/kernel

# A ready-to-use WordPress plugin
composer require wppack/saml-login-plugin
```

See [`docs/components/`](docs/components/) for the full catalogue of components and
their composer package names, and [`docs/plugins/`](docs/plugins/) for distributable
plugins.

## Documentation

- [Architecture overview](docs/architecture/) — infrastructure, multi-cloud, and
  serverless design notes.
- [Component catalogue](docs/components/) — every package, its purpose, and its
  dependencies.
- [Plugins](docs/plugins/) — ready-to-install WordPress plugins built on top of the
  components.

## Requirements

- **PHP 8.2 or higher**
- **WordPress 6.3 or higher** — the first release that officially supports PHP 8.2.
  - WordPress 6.5+ recommended for full PHP 8.3 compatibility.
  - WordPress 6.8+ recommended for PHP 8.4 compatibility (tested in CI).

## Development

```bash
# Install dependencies
composer install

# Start backing services (MySQL, PostgreSQL, Redis, etc.)
docker compose up -d --wait

# Static analysis
vendor/bin/phpstan analyse

# Coding style
vendor/bin/php-cs-fixer fix --dry-run --diff

# Tests (default: runs against MySQL via wpdb)
vendor/bin/phpunit

# Tests against a specific engine
DATABASE_DSN='sqlite:///tmp/wppack-test.db' vendor/bin/phpunit
DATABASE_DSN='pgsql://wppack:wppack@127.0.0.1:5433/wppack_test' vendor/bin/phpunit
```

## Contributing

Issues and pull requests are welcome. Please read [CLAUDE.md](CLAUDE.md) for the
coding standards, commit message format, and architectural principles enforced on
this project.

## License

MIT License. See [LICENSE](LICENSE) for details.
