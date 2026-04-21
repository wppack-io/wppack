# CLAUDE.md

This file provides guidance for Claude Code when working in this repository.

Coding conventions (style, commit format, testing, adding components /
plugins) live in [coding-standards.md](coding-standards.md). Contribution
process (bug reports, PR workflow, review expectations) lives in
[CONTRIBUTING.md](CONTRIBUTING.md). Neither is duplicated here. Sections
below cover project overview, Claude-specific navigation, and maintainer-
only conventions.

## Non-negotiables (failing these loses trust; do not skip)

1. **Tests must pass before every commit.** `vendor/bin/phpunit` for the
   touched component(s) at minimum; full suite before push when the change
   crosses component boundaries.
2. **PHPStan must be clean (level 7).** `vendor/bin/phpstan analyse` shows
   `[OK] No errors`. Don't lower the level. Don't add baseline entries
   without explanation in the commit message — they're tech debt receipts.
3. **Never push to `master`.** All work goes through `1.x` (current
   pre-release branch). PRs target `1.x`.
4. **Never skip hooks or signing.** No `--no-verify`, no
   `--no-gpg-sign`. If a hook fails, fix the cause, don't bypass.
5. **Secrets never in git.** `.env`, IAM credentials, OAuth secrets,
   API tokens — fetch via env / Secrets Manager / OptionManager. If a
   change would commit one, refuse.
6. **Destructive ops require explicit user confirmation.** `git push
   --force`, `git reset --hard`, `rm -rf`, `DROP TABLE`, dropping a
   PHPStan ignore that masks a real bug — call it out and wait. Local-only
   commits can be force-pushed without permission only if not yet on
   origin; once pushed, treat as published.
7. **Don't auto-push after every commit.** CI matrix (PHP × DB × WP) is
   heavy. Batch commits locally; push only on explicit instruction.

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
descriptions and concern-domain category — lives in the docs tree:

- [docs/components/README.md](docs/components/README.md) — every component and
  bridge, grouped by the 8 concern-domain categories (Substrate / Data /
  Content / Identity & Security / HTTP / Presentation / Admin / Utility).
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
- CI/CD runs on GitHub Actions:
  - **push / PR (auto, 19 jobs)**: WP 6.9 × PHP {8.2-8.5} × DB
    {wpdb,sqlite,mysql,postgresql} + smoke combo (PHP 8.2 × wpdb)
    for WP {6.7, 6.8, 7.0}.
  - **workflow_dispatch (manual, 56 jobs)**: full PHP × DB × WP matrix
    minus PHP 8.5 × WP 6.7/6.8 (compat exclusions). Run from Actions UI
    or `gh workflow run ci.yml`.

### Recurring patterns Claude should know up-front

Lessons from past sessions; each was a non-obvious time-sink the first
time. Apply pre-emptively when touching the listed surface:

- **PHP version stub differences (`long2ip`, etc.)**: PHP 8.2 stubs
  declare `long2ip(): string|false`; PHP 8.4 stubs declare `string`. CI
  runs PHP 8.2 minimum, local often runs newer. For functions whose
  return type narrowed across versions, use `(string) func(...)` to
  satisfy both stub generations without dead-code warnings.
- **WP API stub looseness — `array<int|string, T>` vs `list<T>`**:
  `WP_Error::get_error_codes()`, `get_users()`, `get_terms()`,
  `wp_get_object_terms()`, `WP_User->roles`, etc. all return
  `array<int|string, T>` per phpstan-wordpress stubs. When passing to a
  `list<T>` parameter or contracted return, wrap with `array_values()`
  (and `array_filter(...instanceof WP_*)` if the union also admits
  non-instance values).
- **`is_wp_error()` doesn't always narrow under PHPStan**: prefer
  `instanceof \WP_Error` for the early-return guard. Native instanceof
  narrows reliably; the `is_wp_error()` extension support is patchy.
- **`wp_remote_request()` field access**: use `wp_remote_retrieve_*`
  helpers (`_response_code`, `_response_message`, `_headers`, `_body`)
  instead of `$result['response']['code']`. The helpers encapsulate the
  loose stub union.
- **mysqli `affected_rows` is `int<-1, max>|string`**: cast with `(int)`
  before forwarding to `Result` constructor or returning as `int`.
  String only triggers on >2^32 row counts but PHPStan flags every site.
- **`fopen()` / `ftell()` / `fread()` / `filesize()` / `unpack()` return
  `... | false`**: assign to local var, check, then assign to property
  or pass to function. The check on the property post-assignment doesn't
  narrow under PHPStan.
- **`method_exists($x, ...)` accepts `class-string|object`**: subsequent
  `$x->method()` fails type-check unless preceded by `is_object($x)`.
  Group all `method_exists` chains under one `is_object` guard at top
  of the loop / branch.

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

## Toolchain Quick Reference

| Task | Command |
|------|---------|
| Install deps | `composer install` |
| Run tests (all) | `vendor/bin/phpunit` |
| Run tests (single component) | `vendor/bin/phpunit src/Component/{Name}/tests/` |
| Run tests (single file) | `vendor/bin/phpunit path/to/Test.php` |
| Coverage (HTML) | `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage` |
| PHPStan | `vendor/bin/phpstan analyse --no-progress` |
| Regenerate baseline | `vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon` |
| Code style fix | `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php <path>` |
| Code style check | `vendor/bin/php-cs-fixer fix --dry-run --diff --ansi` |
| Database integration (each engine) | `DATABASE_DSN='sqlite:///tmp/wppack_test.db' vendor/bin/phpunit --filter SqliteWpdbIntegrationTest` (mysql / pgsql variants in Database notes) |
| Switch PHP version | `phpenv local 8.2` (8.3 / 8.4 / 8.5) |
| CI status | `gh run list --workflow=ci.yml --limit=3` |
| Run failed CI job (manual matrix) | `gh workflow run ci.yml` (legacy WP) |

PHP runtime: phpenv (no `eval "$(phpenv init -)"` needed for `composer`).

## Working Loop and Session Hygiene

### How to approach a task

1. **Read the surrounding code first.** Skim related component README and
   `coding-standards.md` for the contract you're touching. Don't rewrite
   before understanding.
2. **Find the canonical pattern.** Mirror an existing test or service
   that solves an analogous problem — `DatabaseManager` is the reference
   for component-shaped repositories, `Cache/Bridge/Redis/*` for
   bridge-pattern adapters, `Security/Authentication/Token/*` for value
   objects.
3. **State the plan when the change is non-trivial.** One or two
   sentences for changes > 20 lines or crossing component boundaries.
   Trivial edits: just ship.
4. **Edit → test → PHPStan → commit.** `vendor/bin/phpunit` (touched
   components) + `vendor/bin/phpstan analyse` must both be green before
   each commit. CS-fixer last (if needed).
5. **Never claim a task complete with red tests or PHPStan errors.**
   "Partial / blocked, here's why" beats a false "done".
6. **Discovered a bug along the way?** Record it in the commit message
   or a follow-up task. Don't expand scope silently.
7. **Don't push after every commit** — CI matrix is heavy; batch locally
   and push on explicit request.

### Natural stopping points — stop and prompt a fresh session

Long autonomous loops accumulate context, drift, and risk. When you hit
any of the following, **stop and ask the user whether to continue or
start a new session** — don't push through silently:

- A coherent unit of work just landed (one or more commits) and the next
  task starts a different concern, file family, or component.
- The remaining work needs design discussion (refactor split, API change,
  cross-cutting renames) rather than mechanical edits.
- Tests or PHPStan revealed a category of issues (e.g., a stub-quirk
  pattern, a recurring null-narrowing problem) that deserves planning,
  not iteration.
- The conversation context is nearing capacity, repeated patterns are
  triggering the same auto-reminders, or you've been ping-ponging on the
  same file for several rounds.
- A risky / hard-to-reverse step is next (force push, mass rename,
  destructive migration).

When you stop, summarise concisely: **what landed**, **what's left**,
**why a new session is the right next step**. Offer a clear next-session
brief so the user can paste it as the opening prompt.

This is mandatory at natural boundaries even when the user said
"続けて" earlier — that authorisation is for the current coherent slice,
not an open-ended commitment.

### Self-evaluation + improvement of this file

CLAUDE.md is **not static**. At the end of any session where you notice
one of the following, propose an edit to this file in the same PR (or a
follow-up commit, the user's preference):

- A rule here turned out to be wrong, outdated, or under-specified.
- A decision was made that belongs here but isn't captured (a new
  dependency policy, a CI quirk, a non-obvious convention).
- A convention drifted (PHP version, package version pin, test runner
  flag).
- A recurring mistake was made that a rule could have prevented.
- A section is consistently ignored — it's either wrong or needs
  sharper wording.
- A pattern from the codebase emerged that future Claude should know
  upfront (e.g., "always wrap `array_map(WP_*)` with `array_values()` —
  the WP stub returns array<int|string>").

**Self-evaluation checklist at end of session** (silent unless you
change something):

- [ ] Did I hit any surprise the rules should have warned me about?
- [ ] Did I violate any rule without catching it until late? Why?
- [ ] Are the listed PHP / WordPress / tool versions in this file still
      accurate?
- [ ] Any new package, command, or workflow worth documenting?
- [ ] If this file grew, is it still scannable in one pass? Prune dead
      sections.

When updating: **edit, don't append**. Replace stale guidance outright.
Keep this document under ~250 lines — longer means nobody reads it.

**Meta-rule**: this file supersedes training-data defaults for this
repository. If you catch yourself applying a rule that isn't written
here, ask whether it should be added.

## Updating This File

Update CLAUDE.md when:

- Architecture or design principles change
- Maintainer-only conventions (database notes, menu positions, etc.) change
- Claude-specific navigation needs adjustment
- The Self-evaluation checklist above surfaces a missing / stale rule

Contributor-facing rules live in [CONTRIBUTING.md](CONTRIBUTING.md) — update
that file instead. New packages should be registered in
`docs/components/README.md` or `docs/plugins/README.md`, not here.
