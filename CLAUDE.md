# CLAUDE.md

This file provides guidance for Claude Code when working in this repository.

Coding conventions (style, commit format, testing, adding components /
plugins) live in [coding-standards.md](coding-standards.md). Contribution
process (bug reports, PR workflow, review expectations) lives in
[CONTRIBUTING.md](CONTRIBUTING.md). Neither is duplicated here. Sections
below cover project overview, Claude-specific navigation, and maintainer-
only conventions.

## Project Overview

WPPack is a monorepo of component libraries that extend WordPress with modern PHP.

## Architecture Principles

### Cloud-First

WPPack is built to run in cloud / serverless environments (Lambda, Cloud Functions,
Fargate, Aurora Serverless) as first-class citizens. Local and server-based
installations work through graceful fallbacks — never the other way round.

- Stateless by default; anything that wants to keep state asks for a cache / storage
  adapter via DI.
- Transparent reconnects (Database gone-away handling, OCC retry on DSQL),
  cold-start friendly DI (lazy service resolution).
- Examples: Messenger (SQS / Lambda → synchronous fallback), Scheduler (EventBridge
  → WP-Cron fallback), Cache (Redis / DynamoDB / APCu / Memcached / ElastiCacheAuth).

### Multi-Cloud Support (AWS / GCP / Azure)

Core interfaces (base abstractions in Data / Substrate) are cloud-agnostic. Provider-specific code lives
in Bridge packages. Development is AWS-first; GCP and Azure support expands
incrementally.

- Mailer (core) → AmazonMailer / AzureMailer / SendGridMailer
- Cache (core) → RedisCache / DynamoDbCache / MemcachedCache / ApcuCache / ElastiCacheAuth
- Storage (core) → S3Storage / AzureStorage / GcsStorage
- Database (core) → SqliteDatabase / PostgreSQLDatabase / AuroraDSQLDatabase / MySQLDataApiDatabase / PostgreSQLDataApiDatabase
- Security (core) → SamlSecurity / OAuthSecurity / PasskeySecurity
- Monitoring (core) → CloudWatchMonitoring / CloudflareMonitoring
- Bridge naming: `wppack/{provider}-{component}`
- Details: [docs/architecture/infrastructure.md](docs/architecture/infrastructure.md)

## Package Categories

### Component (Library)
WordPress components. Installed via `composer require`.
- Namespace: `WPPack\Component\{Name}\`
- Package name: `wppack/{name}`
- Directory: `src/Component/{Name}/`

### Plugin (WordPress Plugin)
Distributed as WordPress plugins. Built on top of Components.
- Namespace: `WPPack\Plugin\{Name}\`
- Package name: `wppack/{name}`
- Directory: `src/Plugin/{Name}/`

## Component & Plugin Catalogue

The authoritative catalogue of components, bridges, and plugins — along with
descriptions and layer classification — lives in the docs tree:

- [docs/components/README.md](docs/components/README.md) — every component and
  bridge, grouped by layer (Infrastructure / Abstraction / Feature / Application).
- [docs/plugins/README.md](docs/plugins/README.md) — distributable WordPress
  plugins built on the components.

Do not duplicate the package list here — update the docs instead. Directory layout
is documented under "Directory Structure" below.

## Dependency Graph

Per-package dependencies are declared in each `composer.json` and flow through
Composer's PSR-4 autoload. Components are grouped by **concern-domain category**
(Substrate / Data / Content / Identity & Security / HTTP / Presentation / Admin /
Utility — see [docs/components/README.md](docs/components/README.md)). Dependency
graph must stay acyclic; Substrate and Utility may be depended on freely, other
categories should reference each other only where semantically meaningful.

`wordpress/core-implementation` is declared by every package whose `src/` calls a
WordPress function directly. See
[coding-standards.md § Adding a Component](coding-standards.md#adding-a-component).

## Coding Conventions (see coding-standards.md)

The following are documented in [coding-standards.md](coding-standards.md)
and should be referenced from there, not re-stated here:

- [PHP version and language features](coding-standards.md#php-version-and-language-features)
- [Code style](coding-standards.md#code-style) — PER Coding Style,
  `declare(strict_types=1)`, one class per file, PSR-4
- [`final` keyword policy](coding-standards.md#final-keyword-policy)
- [`#[\SensitiveParameter]` usage](coding-standards.md#sensitiveparameter-usage)
- [`function_exists()` guards](coding-standards.md#function_exists-guards)
- [Wrapping WordPress functions](coding-standards.md#wrapping-wordpress-functions) —
  naming, existing wrappers, DI over `new`
- [Hook vs EventDispatcher](coding-standards.md#hook-vs-eventdispatcher) —
  prefer EventDispatcher for new code
- [WordPress version compatibility](coding-standards.md#wordpress-version-compatibility) —
  `version_compare(get_bloginfo('version'), ...)` pattern
- [Namespaces and directory layout](coding-standards.md#namespaces-and-directory-layout)
- [Commit messages](coding-standards.md#commit-messages) — Conventional Commits
  with atomic-commit discipline
- [Static analysis and lint](coding-standards.md#static-analysis-and-lint) —
  run php-cs-fixer and phpstan before every commit; CI enforces
- [Testing](coding-standards.md#testing) — `DATABASE_DSN` matrix, HTTP mocking
- [Adding a component](coding-standards.md#adding-a-component)
- [Adding a plugin](coding-standards.md#adding-a-plugin)

## Maintainer-Only Notes

### Named Hook Conventions

All Named Hook attributes are centralized in the Hook component:
- Details: [docs/components/hook/named-hook-conventions.md](docs/components/hook/named-hook-conventions.md)
- The Hook component owns all lifecycle hooks (`init`, `admin_init`, etc.) and domain-specific hooks
- Namespace: `WPPack\Component\Hook\Attribute\{ComponentName}\Action\` / `Filter\`
- Directory: `src/Component/Hook/src/Attribute/{ComponentName}/Action/` / `Filter/`
- Auto-discovery: no additional configuration needed thanks to `ReflectionAttribute::IS_INSTANCEOF`

### Monorepo Development Workflow
- All packages managed via the root `composer.json`
- Self-packages declared in the `replace` section
- Individual package repositories published via splitsh-lite
- CI/CD runs on GitHub Actions (16-job matrix: PHP 8.2-8.5 × wpdb/mysql/sqlite/postgresql)

### Consistency Checks for Documentation & Component Updates

- **When updating documentation**: Verify that link targets in the component list table in `docs/components/README.md` actually exist. When adding new documentation, add a link to the table and ensure path format consistency with existing links (files: `./name.md`, directories: `./name/`)
- **When updating components**: Ensure that component names, package names, and descriptions are consistent across: `docs/components/README.md`, `src/Component/{Name}/README.md` (package README), and the implementation under `src/` (namespaces, directory names, `composer.json`)

### Database Component Maintenance Notes

The `wppack/database` component is the project's most feature-rich abstraction (multi-engine driver layer, AST-based query translation, WPPackWpdb replacement for `$wpdb`). A few rules when changing it:

- **AST-first translation.** Engine-specific rewrites live in `Bridge/{Sqlite,PostgreSQL,AuroraDSQL}/src/Translator/*QueryTranslator.php`. Prefer AST-level transforms over string regex; only drop to `QueryRewriter` (token walk) when the shape isn't in phpmyadmin/sql-parser's AST. Silent pass-through is forbidden — unsupported features must raise `UnsupportedFeatureException` so callers see the gap.
- **Integration CI matrix.** The test suite runs against MySQL, SQLite, PostgreSQL (wpdb variant plus 3 driver variants × 4 PHP versions). Only the `wpdb` variant blocks merge; other variants are `continue-on-error: true` because plugin-layer tests (Query component) still have pre-existing failures unrelated to Database. When adding translator behaviour, verify with all three integration runners locally before commit:
  ```bash
  DATABASE_DSN='sqlite:///tmp/wppack_test.db' vendor/bin/phpunit --filter SqliteWpdbIntegrationTest
  DATABASE_DSN='mysql://root:password@127.0.0.1:3307/wppack_test' vendor/bin/phpunit --filter MySQLWpdbIntegrationTest
  DATABASE_DSN='pgsql://wppack:wppack@127.0.0.1:5433/wppack_test' vendor/bin/phpunit --filter PostgreSQLWpdbIntegrationTest
  ```
- **PSR-3 logger + PSR-14 events must never leak raw bound values.** Payloads flow through `paramsSummary()` (positional `#0 => 'string(7)'` type/length descriptors). The `WPPACK_DB_LOG_VALUES=1` env flag is for local dev only. Never inline raw `$params` into logger context.
- **Reader/Writer routing.** `WPPackWpdb::selectDriver()` is the single dispatch point. Changes to which query goes where (SELECT vs write, transaction vs plain) touch replication-lag-sensitive code paths — always add a spy-driver test that seeds distinct data in the two connections.
- **PreparedBank.** Markers are `/*WPP:<16-hex>*/` computed from a per-instance `random_bytes(8)` salt + sha1(sql + params). Never remove the salt (marker forge protection). When changing the marker format, update `MARKER_PATTERN` in both `PreparedBank.php` and every hardcoded test regex under `tests/`.
- **Drivers' gone-away handling.** `MySQLDriver::throwQueryError()` and `PostgreSQLDriver::throwQueryError()` drop `$this->connection = null` on specific error codes so `ensureConnected()` re-opens on the next call. Do not restore a stale handle "just to be nice" — production callers rely on the nulling.
- **PostgreSQL search_path.** `PostgreSQLDriver` (and `AuroraDSQLDriver` via inheritance) accept a `searchPath` ctor arg / `?search_path=` DSN option, emitting `SET search_path TO ...` after each connect. Translator introspection (`SHOW TABLES`, `SHOW COLUMNS`) uses `current_schema()`, not hardcoded `'public'`.
- **Translator exceptions.** `TranslationException`, `ParserFailureException`, `UnsupportedFeatureException`. Drivers emit `DriverException`, `DriverThrottledException`, `DriverTimeoutException`, `CredentialsExpiredException`, `ConnectionException`. All implement `ExceptionInterface`.
- **Mocking caveats.** `AuroraDSQLDriver` and the DataApi drivers require optional async-aws packages. Tests that don't need a live connection should use `ReflectionClass::newInstanceWithoutConstructor()` + property injection (see `tests/Bridge/AuroraDSQL/tests/OccRetryTest.php`).

### Cache Component Notes

- Adapter selection is driven by the `CACHE_DSN` environment variable / PHP
  constant (previously `WPPACK_CACHE_DSN` — renamed). The `object-cache.php`
  drop-in reads it, as do `CloudWatch` auto-discovery and
  `RedisCacheConfiguration`.

### Plugin Settings Pages (WordPress Components)

For plugin settings pages, use WordPress Components (`@wordpress/components`) with `@wordpress/scripts` for the build pipeline. Follow the modern WordPress admin UI patterns:

- [How to use DataForm to create plugin settings pages](https://developer.wordpress.org/news/2026/01/how-to-use-dataform-to-create-plugin-settings-pages/)
- [How to use WordPress React components for plugin pages](https://developer.wordpress.org/news/2024/03/how-to-use-wordpress-react-components-for-plugin-pages/)

Key patterns:
- Enqueue `wp-components`, `wp-element`, `wp-api-fetch` as dependencies
- Use custom REST API endpoints (`/wppack/v1/...`) for settings CRUD
- Sensitive fields (certificates, keys) must be masked in API responses
- Fields sourced from constants should be displayed as readonly
- **npm install must always use `--ignore-scripts`** (e.g., `npm install --ignore-scripts`). This prevents arbitrary script execution from dependencies.

### Plugin Settings Menu Position

Submenu `position` is grouped by category in increments of 100. Each plugin within a category gets a unique position value (base + 1, 2, 3...) to ensure deterministic ordering. `AdminPageRegistry` sorts WPPack submenu items by position after WordPress core items.

| position | Category | Plugins |
|----------|----------|---------|
| 101–103 | Infrastructure (Cache, Storage, Mail) | RedisCachePlugin (101), S3StoragePlugin (102), AmazonMailerPlugin (103) |
| 201–203 | Authentication (SSO, OAuth, Passkey) | SamlLoginPlugin (201), OAuthLoginPlugin (202), PasskeyLoginPlugin (203) |
| 300–301 | Provisioning | ScimPlugin (300), RoleProvisioningPlugin (301) |

MonitoringPlugin uses a top-level menu (not under Settings) with `position: 90` in the WordPress admin sidebar.

When adding a new plugin, assign the next sequential position within the existing category. If a new category is needed, use a new multiple of 100 as the base.

### Directory Structure

```
wppack/
├── src/
│   ├── Component/          # WordPress components
│   │   ├── Handler/       → wppack/handler
│   │   ├── Hook/          → wppack/hook
│   │   ├── Mailer/        → wppack/mailer
│   │   ├── Messenger/     → wppack/messenger
│   │   └── ...
│   └── Plugin/             # WordPress plugins
│       ├── EventBridgeSchedulerPlugin/  → wppack/eventbridge-scheduler-plugin
│       ├── S3StoragePlugin/  → wppack/s3-storage-plugin
│       └── AmazonMailerPlugin/  → wppack/amazon-mailer-plugin
├── tests/
│   ├── Component/
│   └── Plugin/
├── docs/
└── ...
```

### Backward Compatibility

All packages are pre-release on the `1.x` branch (in design phase), so backward
compatibility is not a concern. API changes, parameter reordering, class
renames, and deletions may be done freely.

## Status

- All packages: In design phase (branch `1.x`, unreleased)

## Updating This File

Update CLAUDE.md when:

- Architecture or design principles change
- Maintainer-only conventions (database notes, menu positions, etc.) change
- Claude-specific navigation needs adjustment

Contributor-facing rules live in [CONTRIBUTING.md](CONTRIBUTING.md) — update
that file instead. New packages should be registered in
`docs/components/README.md` or `docs/plugins/README.md`, not here.
