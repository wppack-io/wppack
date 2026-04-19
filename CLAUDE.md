# CLAUDE.md

This file provides guidance for Claude Code when working in this repository.

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
  → WP-Cron fallback), Cache (Redis / DynamoDB / APCu / Memcached).

### Multi-Cloud Support (AWS / GCP / Azure)

Core interfaces (Abstraction Layer) are cloud-agnostic. Provider-specific code lives
in Bridge packages. Development is AWS-first; GCP and Azure support expands
incrementally.

- Mailer (core) → AmazonMailer / AzureMailer / SendGridMailer
- Cache (core) → RedisCache / DynamoDbCache / MemcachedCache / ApcuCache
- Storage (core) → S3Storage / AzureStorage / GcsStorage
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
Composer's PSR-4 autoload. Architectural layering (Infrastructure → Abstraction →
Feature → Application) is documented in
[docs/architecture/](docs/architecture/) — refer there when deciding whether a new
dependency is acceptable (a lower layer must never depend on a higher one).

`wordpress/core-implementation` is declared by every package whose src/ calls a
WordPress function directly. See "Checklist for Adding Components" below for the
current rule.

## Development Guidelines

### Language
- Documentation: English (`src/Component/**/README.md`, `src/Plugin/**/README.md`)
- Top-level docs (`docs/`, root `README.md`): English
- Japanese docs under `docs/` are acceptable where the audience is JP-speaking
  operators / maintainers (per-case)
- Code: English (variable names, class names, comments)

### PHP Requirements
- PHP 8.2 or higher
- PSR-4 autoloading

### WordPress Requirements
- WordPress 6.3 or higher (first release with official PHP 8.2 support)

### Coding Standards

**Follow modern PHP best practices. Do NOT use WordPress Coding Standards.**

- Comply with PER Coding Style (successor to PSR-12)
- Strict type declarations (`declare(strict_types=1)`)
- Use readonly properties
- Follow Symfony patterns
- Use constructor property promotion
- Use match expressions
- Use named arguments where appropriate

### `final` Keyword Policy

**Default is no `final`.** Only add `final` when there is a compelling reason (e.g., immutable value objects). Prefer testability and extensibility.

| Class type | `final` | Reason |
|------------|---------|--------|
| Value objects / DTOs | `final readonly` | Immutable, no extension needed |
| Configuration classes | `final readonly` | Immutable, no extension needed |
| All others | No | Preserve testability and extensibility |

### `#[\SensitiveParameter]` Usage Policy

Apply `#[\SensitiveParameter]` to parameters that receive secret information such as passwords, API keys, and access keys. This replaces parameter values with `SensitiveParameterValue` in exception stack traces, preventing leakage to logs and screens.

```php
public function __construct(
    #[\SensitiveParameter]
    private readonly ?string $password,
) {}
```

- **Include:** Passwords, API keys, access keys, encryption keys, authentication tokens (JWT, etc.)
- **Exclude:** Public information (`$clientId`, `$issuer`, JWKS public keys), CSRF nonces, object types (only type names appear in stack traces), entire DSN strings (scheme/host information would be lost — protect the password portion via a `$password` parameter instead), entire `$options` arrays (makes debugging difficult — protect individual secret parameters instead)

### Commit Messages

Use a format based on [Conventional Commits](https://www.conventionalcommits.org/).

```
<type>(<scope>): <summary>

<body>
```

#### Summary Line (first line)

- **72 characters or fewer** (fits in `git log --oneline` without truncation)
- **Type prefix**: Clarifies the kind of change
- **Scope (optional)**: Target component or package name
- **Imperative mood**: "Add", "Fix", "Refactor" etc. (not "Added" or "Fixes")

| type | Purpose |
|------|------|
| `feat` | New feature |
| `fix` | Bug fix |
| `refactor` | Refactoring (no behavior change) |
| `docs` | Documentation-only changes |
| `test` | Test additions or modifications only |
| `chore` | Build, CI, dependency maintenance, etc. |

#### Body (subsequent lines)

- Separate from the summary line with **one blank line**
- Structure changes using **bullet points** (`-`)
- Include **why** the change was needed
- Add technical details as necessary

#### Example

```
feat(Admin,DashboardWidget,Setting): add render() shortcut

- Add render() method to AbstractAdminPage, AbstractDashboardWidget,
  AbstractSettingsPage that delegates to TemplateRendererInterface
- Registry classes accept optional TemplateRendererInterface in constructor
  and inject via setter during register()
- Setting uses $templateRenderer to avoid collision with $renderer
  (SettingsRenderer)
```

#### Commit Granularity

**1 commit = 1 logical unit of change (atomic commit)** as a general rule.

- **Include in the same commit**: Feature implementation and its tests, feature implementation and directly related documentation updates
- **Separate into different commits**: Independent bug fixes, new features and unrelated refactoring
- **Rule of thumb**: "If I `git revert` only this commit, does it revert a meaningful, self-contained unit?"

### `function_exists()` Usage Policy

There is no need to assume environments where WordPress is not loaded. `function_exists()` guards for WordPress core functions are unnecessary.

- **Not needed:** WordPress core functions such as `get_post`, `wp_insert_user`, `get_term_meta` (always exist when WordPress is loaded)
- **Needed:** Multisite-only functions (`get_sites`, `switch_to_blog`, etc. — do not exist on single-site installs)
- **Needed:** PHP extension functions (`apcu_enabled`, `bzcompress`, `finfo_open`, etc.)
- **Needed:** `wp-admin`-only functions that require `require_once` (`dbDelta`, `wp_delete_user`, etc.)

### Wrapping WordPress Functions

WordPress global functions (`wp_logout()`, `get_current_user_id()`, `wp_set_auth_cookie()`, etc.) should **not** be called directly from application code. Instead, wrap them in a dedicated class and inject that class via DI. This enables type-safe usage, testability (mockable), and a single point of change.

#### Naming Conventions

Follow Symfony's naming patterns — use a plain noun or compound noun **without** a suffix for thin façades/wrappers. Reserve the `*Manager` suffix for classes that orchestrate multiple sub-components.

| Pattern | When to use | Examples |
|---------|-------------|---------|
| No suffix (plain noun) | Thin wrapper / façade over a small set of related functions | `AuthenticationSession`, `Security`, `TokenStorage`, `PasswordHasher` |
| `*Manager` | Orchestrates multiple sub-components or manages CRUD lifecycle of a resource | `AuthenticationManager`, `AccessDecisionManager`, `SamlSessionManager` |

#### Example

```php
// Good — wrapped in a class, injected via DI
final class AuthenticationSession
{
    public function login(int $userId, bool $remember = false, bool $secure = false): void
    {
        wp_clear_auth_cookie();
        wp_set_auth_cookie($userId, $remember, $secure);
    }

    public function logout(): void { wp_logout(); }
    public function getCurrentUserId(): int { return get_current_user_id(); }
}

// Bad — calling WordPress functions directly in application code
$this->establishAuthSession($token);
// ...
private function establishAuthSession(TokenInterface $token): void
{
    wp_clear_auth_cookie();
    wp_set_auth_cookie($token->getUser()->ID, false, is_ssl());
}
```

#### Scope

- **Wrap:** WordPress functions used across multiple classes or that affect external state (auth cookies, sessions, user switching, rewrite rules, etc.)
- **No need to wrap:** Functions called only inside a single, self-contained utility (e.g., `home_url()` in a URL builder) or inside hooks registered before the DI container boots
- **Use existing components first:** Before calling a WordPress function directly, check if a WPPack component already wraps it. Use the component API instead of the raw WordPress function.
- **Prefer DI over `new`:** Always inject dependencies via the constructor rather than using `new ClassName()` as default parameter values. This ensures dependencies are properly managed by the DI container and makes the dependency graph explicit.

| WordPress function | WPPack component method |
|---|---|
| `wp_logout()`, `wp_set_auth_cookie()`, `get_current_user_id()` | `AuthenticationSession::logout()`, `login()`, `getCurrentUserId()` |
| `flush_rewrite_rules()`, `add_rewrite_rule()` | `RouteRegistry::flush()`, `addRoute()` |
| `wp_insert_post()`, `get_post()` | `PostType` component |
| `switch_to_blog()`, `restore_current_blog()` | `BlogContext` component |

### Hook vs EventDispatcher

**Prefer EventDispatcher** for new implementations. EventDispatcher uses WordPress's `$wp_filter` as its backend, so WordPress hooks (actions/filters) can also be handled in a type-safe manner via `WordPressEvent` / Extended Event classes.

| Case | Recommendation |
|--------|------|
| Hooks before DI container boot (`plugins_loaded`, etc.) | Use WordPress functions directly (`add_action()` / `add_filter()`) |
| WordPress hooks in general (`init` and later) | **EventDispatcher** (`WordPressEvent` / `#[AsEventListener]`) |
| Application-specific domain events | **EventDispatcher** (custom events + `#[AsEventListener]`) |
| Loosely coupled notifications between components | **EventDispatcher** |

The Hook component is retained for compatibility with existing code, but new implementations should use EventDispatcher.

### Named Hook Conventions

All Named Hook attributes are centralized in the Hook component:
- Details: [docs/components/hook/named-hook-conventions.md](docs/components/hook/named-hook-conventions.md)
- The Hook component owns all lifecycle hooks (`init`, `admin_init`, etc.) and domain-specific hooks
- Namespace: `WPPack\Component\Hook\Attribute\{ComponentName}\Action\` / `Filter\`
- Directory: `src/Component/Hook/src/Attribute/{ComponentName}/Action/` / `Filter/`
- Auto-discovery: No additional configuration needed thanks to `ReflectionAttribute::IS_INSTANCEOF`

### WordPress Version Compatibility

When WordPress hooks or functions are renamed or deprecated between versions, use `version_compare(get_bloginfo('version'), ...)` to detect the version and support both old and new. Prefer the newer hook, with a fallback for older versions.

```php
// Example: setted_transient renamed to set_transient in WP 6.8
$useNewHooks = version_compare(get_bloginfo('version'), '6.8', '>=');
$setHook = $useNewHooks ? 'set_transient' : 'setted_transient';
add_action($setHook, [$this, 'onTransientSet'], 10, 3);
```

- Do not register both old and new hooks simultaneously to avoid double-firing (use conditional branching to select one)
- Do not continue using deprecated hooks/functions (causes deprecation warnings)
- Clearly document the supported version range in comments (e.g., `// WP 6.8+: ... / WP < 6.8: ...`)

### Namespaces

```
WPPack\Component\{Name}\  - Components
WPPack\Plugin\{Name}\     - Plugins
```

### Static Analysis & CI

```bash
vendor/bin/phpstan analyse                      # Static analysis
vendor/bin/php-cs-fixer fix --dry-run --diff    # Code style check
vendor/bin/phpunit                              # Run tests
```

**Important:** Before every `git commit`, always run `vendor/bin/php-cs-fixer fix` and `vendor/bin/phpstan analyse` to ensure code style compliance and no static analysis errors. CI will fail if these checks are not passed.

### Testing

#### Test Configuration

Tests run in a WordPress integration test environment using wp-phpunit + MySQL. `tests/bootstrap.php` always fully loads WordPress, so MySQL must be started via Docker before running tests.

#### Running Tests Locally

```bash
docker compose up -d --wait    # Start MySQL (required)
vendor/bin/phpunit             # Run all tests
docker compose down            # Stop MySQL
```

Test DB credentials are `root` / `password` on port 3307 (tmpfs, reset on
container restart). See `tests/wp-config.php` for defaults.

#### Mocking WordPress Functions in Tests

For tests that depend on WordPress functions, mock HTTP calls using the `pre_http_request` filter. Do not use the pattern of extending `HttpClient` with anonymous classes (incompatible with clone-based immutability).

```php
// Register filter in setUp()
add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);

// Remove filter in tearDown()
remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
```

#### Test File Location

Tests for each component are located in `src/Component/{Name}/tests/`.

### Monorepo Development Workflow
- All packages managed via the root `composer.json`
- Self-packages declared in the `replace` section
- Individual package repositories published via splitsh-lite
- CI/CD runs on GitHub Actions

### Checklist for Adding Components

When adding a new component or Bridge package:

1. **Component directory** — Create `src/Component/{Name}/` with:
   - `src/` — Component source code
   - `tests/` — Tests
   - `composer.json` — Package definition
   - `README.md` — Package README (English)
   - `LICENSE` — MIT license file
   - `.gitignore` — `vendor/`, `composer.lock`, `phpunit.xml`
   - `phpunit.xml.dist` — PHPUnit configuration
   - `.github/PULL_REQUEST_TEMPLATE.md` — Subtree split PR template
   - `.github/workflows/close-pull-request.yml` — Auto-close PRs on read-only repo
2. **`composer.json` `require`** — If the package's src/ calls any WordPress
   function, constant, or class directly, declare
   `"wordpress/core-implementation": "^6.3"`. Pure-PHP packages (value objects,
   AST translators, SDK adapters) should not. When in doubt, grep src/ for
   `wp_`, `get_`, `add_action`, `add_filter`, `WP_`, `ABSPATH`, `$wpdb`.
3. **Root `composer.json`** — Add to `autoload.psr-4`, `autoload-dev.psr-4`, and `replace`
4. **`codecov.yml`** — Add `component_id` / `name` / `paths` to `individual_components`
5. **`docs/components/README.md`** — Register the new package in the catalogue
   table for its layer, linking to a component-level doc if you added one
6. **`docs/`** — Create or update documentation for the component

### Checklist for Adding Plugins

When adding a new WordPress plugin package:

1. **Plugin directory** — Create `src/Plugin/{Name}/` with:
   - `wppack-{slug}.php` — Bootstrap file (`Kernel::registerPlugin`)
   - `src/` — Plugin source code
   - `tests/` — Tests
   - `composer.json` — Package definition
   - `README.md` — Package README
   - `.github/` — PR template and workflows (copy from existing plugin)
   - `.gitignore` — Git ignore rules
   - `LICENSE` — MIT license file
2. **Root `composer.json`** — Add to `autoload.psr-4`, `autoload-dev.psr-4`, and `replace`
3. **`codecov.yml`** — Add coverage configuration
4. **Symlink (required)** — Create a symlink in `web/wp-content/plugins/` and commit it to Git:
   ```bash
   cd web/wp-content/plugins && ln -s ../../../src/Plugin/{Name} wppack-{slug}
   ```
   Required for WordPress to discover the plugin. Always use relative paths.
5. **`docs/plugins/README.md`** — Register the plugin in the catalogue table
6. **`docs/plugins/`** — Create plugin documentation

### Consistency Checks for Documentation & Component Updates

- **When updating documentation**: Verify that link targets in the component list table in `docs/components/README.md` actually exist. When adding new documentation, add a link to the table and ensure path format consistency with existing links (files: `./name.md`, directories: `./name/`)
- **When updating components**: Ensure that component names, package names, and descriptions are consistent across: `docs/components/README.md`, `src/Component/{Name}/README.md` (package README), and the implementation under `src/` (namespaces, directory names, `composer.json`)

### Database Component Maintenance Notes

The `wppack/database` component is the project's most feature-rich abstraction (multi-engine driver layer, AST-based query translation, WPPackWpdb replacement for `$wpdb`). A few rules when changing it:

- **AST-first translation.** Engine-specific rewrites live in `Bridge/{Sqlite,Pgsql,AuroraDsql}/src/Translator/*QueryTranslator.php`. Prefer AST-level transforms over string regex; only drop to `QueryRewriter` (token walk) when the shape isn't in phpmyadmin/sql-parser's AST. Silent pass-through is forbidden — unsupported features must raise `UnsupportedFeatureException` so callers see the gap.
- **Integration CI matrix.** The test suite runs against MySQL, SQLite, PostgreSQL (wpdb variant plus 3 driver variants × 4 PHP versions). Only the `wpdb` variant blocks merge; other variants are `continue-on-error: true` because plugin-layer tests (Query component) still have pre-existing failures unrelated to Database. When adding translator behaviour, verify with all three integration runners locally before commit:
  ```bash
  DATABASE_DSN='sqlite:///tmp/wppack_test.db' vendor/bin/phpunit --filter SqliteWpdbIntegrationTest
  DATABASE_DSN='mysql://root:password@127.0.0.1:3307/wppack_test' vendor/bin/phpunit --filter MysqlWpdbIntegrationTest
  DATABASE_DSN='pgsql://wppack:wppack@127.0.0.1:5433/wppack_test' vendor/bin/phpunit --filter PgsqlWpdbIntegrationTest
  ```
- **PSR-3 logger + PSR-14 events must never leak raw bound values.** Payloads flow through `paramsSummary()` (positional `#0 => 'string(7)'` type/length descriptors). The `WPPACK_DB_LOG_VALUES=1` env flag is for local dev only. Never inline raw `$params` into logger context.
- **Reader/Writer routing.** `WPPackWpdb::selectDriver()` is the single dispatch point. Changes to which query goes where (SELECT vs write, transaction vs plain) touch replication-lag-sensitive code paths — always add a spy-driver test that seeds distinct data in the two connections.
- **PreparedBank.** Markers are `/*WPP:<16-hex>*/` computed from a per-instance `random_bytes(8)` salt + sha1(sql + params). Never remove the salt (marker forge protection). When changing the marker format, update `MARKER_PATTERN` in both `PreparedBank.php` and every hardcoded test regex under `tests/`.
- **Drivers' gone-away handling.** `MysqlDriver::throwQueryError()` and `PgsqlDriver::throwQueryError()` drop `$this->connection = null` on specific error codes so `ensureConnected()` re-opens on the next call. Do not restore a stale handle "just to be nice" — production callers rely on the nulling.
- **PostgreSQL search_path.** `PgsqlDriver` (and `AuroraDsqlDriver` via inheritance) accept a `searchPath` ctor arg / `?search_path=` DSN option, emitting `SET search_path TO ...` after each connect. Translator introspection (`SHOW TABLES`, `SHOW COLUMNS`) uses `current_schema()`, not hardcoded `'public'`.
- **Translator exceptions.** `TranslationException`, `ParserFailureException`, `UnsupportedFeatureException`. Drivers emit `DriverException`, `DriverThrottledException`, `DriverTimeoutException`, `CredentialsExpiredException`, `ConnectionException`. All implement `ExceptionInterface`.
- **Mocking caveats.** `AuroraDsqlDriver` and the DataApi drivers require optional async-aws packages. Tests that don't need a live connection should use `ReflectionClass::newInstanceWithoutConstructor()` + property injection (see `tests/Bridge/AuroraDsql/tests/OccRetryTest.php`).

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

Update this CLAUDE.md as needed when the project changes:

- When architecture or design principles change
- When coding standards are updated
- When important development rules or commands are added
- When the project status changes

New packages should be registered in `docs/components/README.md` or
`docs/plugins/README.md`, not here.
