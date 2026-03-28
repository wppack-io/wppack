# CLAUDE.md

This file provides guidance for Claude Code when working in this repository.

## Project Overview

WpPack is a monorepo of component libraries that extend WordPress with modern PHP.

## Architecture Principles

### Multi-Cloud Support (AWS / GCP / Azure)

Core interfaces (Abstraction Layer) are cloud-agnostic. Provider-specific implementations are separated into Bridge packages. Development is AWS-first, with GCP and Azure support expanding incrementally.

- Example: Mailer (core) → AmazonMailer / AzureMailer / SendGridMailer
- Example: Cache (core) → RedisCache / DynamoDbCache / MemcachedCache / ApcuCache
- Bridge naming: `wppack/{provider}-{component}`

### Serverless Environment Support

Serverless environments such as Lambda and Cloud Functions are first-class citizens. Graceful fallbacks are provided for local and server-based environments.

- Example: Messenger (SQS/Lambda → synchronous fallback), Scheduler (EventBridge → WP-Cron fallback)
- Details: [docs/architecture/infrastructure.md](docs/architecture/infrastructure.md)

## Package Categories

### Component (Library)
WordPress components. Installed via `composer require`.
- Namespace: `WpPack\Component\{Name}\`
- Package name: `wppack/{name}`
- Directory: `src/Component/{Name}/`

### Plugin (WordPress Plugin)
Distributed as WordPress plugins. Built on top of Components.
- Namespace: `WpPack\Plugin\{Name}\`
- Package name: `wppack/{name}`
- Directory: `src/Plugin/{Name}/`

## Component List

### Infrastructure Layer
| Component | Package Name | Description |
|-----------|-------------|------|
| Handler | wppack/handler | Modern PHP request handler (front controller) |
| Hook | wppack/hook | Attribute-based WordPress hook (action/filter) management |
| DependencyInjection | wppack/dependency-injection | PSR-11 compliant service container, autowiring, configuration management |
| EventDispatcher | wppack/event-dispatcher | PSR-14 compliant event system |
| Filesystem | wppack/filesystem | WP_Filesystem DI wrapper, file operation abstraction |
| Kernel | wppack/kernel | Application bootstrap |
| Option | wppack/option | Type-safe wrapper for wp_options |
| Transient | wppack/transient | Type-safe wrapper for the Transient API |
| Role | wppack/role | Role and capability management |
| Templating | wppack/templating | Template engine abstraction |
| TwigTemplating | wppack/twig-templating | Twig bridge |
| Stopwatch | wppack/stopwatch | Code execution time measurement |
| Logger | wppack/logger | PSR-3 compliant logger |
| MonologLogger | wppack/monolog-logger | Monolog bridge |
| Mime | wppack/mime | MIME type detection and extension mapping |
| Site | wppack/site | Multisite management (blog switching, context, site queries) |

### Abstraction Layer
| Component | Package Name | Description |
|-----------|-------------|------|
| Cache | wppack/cache | PSR-6/PSR-16 cache abstraction |
| RedisCache | wppack/redis-cache | Redis / Valkey cache |
| ElastiCacheAuth | wppack/elasticache-auth | ElastiCache IAM authentication |
| DynamoDbCache | wppack/dynamodb-cache | DynamoDB cache |
| MemcachedCache | wppack/memcached-cache | Memcached cache |
| ApcuCache | wppack/apcu-cache | APCu cache |
| Database | wppack/database | Type-safe wrapper for $wpdb, migrations |
| Query | wppack/query | WP_Query builder |
| Security | wppack/security | Authentication and authorization framework |
| SamlSecurity | wppack/saml-security | SAML 2.0 SP authentication bridge |
| OAuthSecurity | wppack/oauth-security | OAuth 2.0 / OpenID Connect authentication bridge |
| Sanitizer | wppack/sanitizer | Input sanitization |
| Escaper | wppack/escaper | Output escaping |
| HttpClient | wppack/http-client | HTTP client abstraction |
| HttpFoundation | wppack/http-foundation | Request/Response abstraction |
| Mailer | wppack/mailer | Email sending abstraction, TransportInterface |
| AmazonMailer | wppack/amazon-mailer | SES transport implementation |
| AzureMailer | wppack/azure-mailer | Azure Communication Services transport implementation |
| SendGridMailer | wppack/sendgrid-mailer | SendGrid transport implementation |
| Messenger | wppack/messenger | Transport-agnostic message bus |
| SqsMessenger | wppack/sqs-messenger | Amazon SQS transport |
| Serializer | wppack/serializer | Object serialization (Normalizer chain) |
| OptionsResolver | wppack/options-resolver | Option resolution (Symfony OptionsResolver extension) |
| Debug | wppack/debug | Debugging and profiling |
| Storage | wppack/storage | Object storage abstraction |
| S3Storage | wppack/s3-storage | Amazon S3 storage adapter |
| AzureStorage | wppack/azure-storage | Azure Blob Storage adapter |
| GcsStorage | wppack/gcs-storage | Google Cloud Storage adapter |

### Feature Layer
| Component | Package Name | Description |
|-----------|-------------|------|
| Admin | wppack/admin | Admin page and menu registration |
| Rest | wppack/rest | REST API endpoint definition |
| Routing | wppack/routing | URL routing |
| PostType | wppack/post-type | Custom post type and meta registration |
| Scheduler | wppack/scheduler | Trigger-based task scheduler |
| EventBridgeScheduler | wppack/eventbridge-scheduler | EventBridge scheduler |
| Console | wppack/console | WP-CLI command framework |
| Shortcode | wppack/shortcode | Shortcode registration |
| Nonce | wppack/nonce | CSRF token management |
| Asset | wppack/asset | Asset management (scripts and styles) |
| Ajax | wppack/ajax | Admin Ajax handler |
| Scim | wppack/scim | SCIM 2.0 provisioning |
| Wpress | wppack/wpress | .wpress archive format operations |

### Application Layer
| Component | Package Name | Description |
|-----------|-------------|------|
| Plugin | wppack/plugin | Plugin lifecycle management |
| Theme | wppack/theme | Theme development framework |
| Widget | wppack/widget | Widget definition |
| Setting | wppack/setting | Settings API wrapper |
| User | wppack/user | User management |
| Block | wppack/block | Block editor integration |
| Media | wppack/media | Media management |
| Comment | wppack/comment | Comment management |
| Taxonomy | wppack/taxonomy | Taxonomy definition |
| NavigationMenu | wppack/navigation-menu | Menu management |
| Feed | wppack/feed | RSS/Atom feed |
| OEmbed | wppack/oembed | oEmbed provider |
| SiteHealth | wppack/site-health | Site health checks |
| DashboardWidget | wppack/dashboard-widget | Dashboard widget |
| Translation | wppack/translation | Translation and internationalization |

### Plugin Packages
| Plugin | Package Name | Description |
|--------|-------------|------|
| EventBridgeSchedulerPlugin | wppack/eventbridge-scheduler-plugin | EventBridge scheduler plugin |
| S3StoragePlugin | wppack/s3-storage-plugin | S3 storage plugin |
| AmazonMailerPlugin | wppack/amazon-mailer-plugin | Amazon SES mailer plugin |
| DebugPlugin | wppack/debug-plugin | Debug toolbar plugin |
| RedisCachePlugin | wppack/redis-cache-plugin | Redis cache plugin |
| SamlLoginPlugin | wppack/saml-login-plugin | SAML 2.0 SSO login plugin |
| ScimPlugin | wppack/scim-plugin | SCIM 2.0 provisioning plugin |

## Key Dependencies

```
wppack/handler
    ↓ requires
wppack/http-foundation, wppack/mime
    + wppack/kernel (suggest)

wppack/eventbridge-scheduler-plugin
    ↓ requires
wppack/scheduler
    ↓ requires
wppack/messenger

wppack/messenger
    ↓ requires
wppack/serializer, wppack/site

wppack/sqs-messenger
    ↓ requires
wppack/messenger, wppack/site
    + async-aws/sqs

wppack/media
    ↓ requires
wppack/post-type, wppack/storage, wppack/site

wppack/s3-storage-plugin
    ↓ requires
wppack/storage, wppack/s3-storage, wppack/rest, wppack/site
    + wppack/media, wppack/messenger
    + async-aws/s3

wppack/s3-storage
    ↓ requires
wppack/storage
    + async-aws/s3

wppack/azure-storage
    ↓ requires
wppack/storage
    + azure-oss/storage

wppack/gcs-storage
    ↓ requires
wppack/storage
    + google/cloud-storage

wppack/elasticache-auth
    + async-aws/core

wppack/redis-cache
    ↓ requires
wppack/cache
    + ext-redis / ext-relay / predis/predis

wppack/dynamodb-cache
    ↓ requires
wppack/cache
    + async-aws/dynamo-db

wppack/memcached-cache
    ↓ requires
wppack/cache
    + ext-memcached

wppack/apcu-cache
    ↓ requires
wppack/cache
    + ext-apcu

wppack/amazon-mailer
    ↓ requires
wppack/mailer
    + async-aws/ses

wppack/azure-mailer
    ↓ requires
wppack/mailer

wppack/sendgrid-mailer
    ↓ requires
wppack/mailer

wppack/amazon-mailer-plugin
    ↓ requires
wppack/amazon-mailer, wppack/mailer, wppack/hook
    + wppack/dependency-injection, wppack/kernel, wppack/messenger

wppack/redis-cache-plugin
    ↓ requires
wppack/cache, wppack/redis-cache, wppack/elasticache-auth, wppack/hook
    + wppack/dependency-injection, wppack/kernel

wppack/security
    ↓ requires
wppack/role, wppack/http-foundation, wppack/event-dispatcher, wppack/site

wppack/admin, wppack/setting, wppack/dashboard-widget
    ↓ requires
wppack/role, wppack/http-foundation
    + wppack/security (suggest)
    + wppack/templating (suggest)

wppack/ajax, wppack/routing, wppack/rest
    ↓ requires
wppack/role, wppack/http-foundation

wppack/saml-security
    ↓ requires
wppack/security, wppack/site
    + onelogin/php-saml

wppack/oauth-security
    ↓ requires
wppack/security, wppack/site
    + firebase/php-jwt

wppack/eventbridge-scheduler
    ↓ requires
wppack/scheduler, wppack/messenger, wppack/site
    + async-aws/scheduler

wppack/twig-templating
    ↓ requires
wppack/templating
    + twig/twig

wppack/monolog-logger
    ↓ requires
wppack/logger
    + monolog/monolog

wppack/scim
    ↓ requires
wppack/rest, wppack/user, wppack/role, wppack/security
wppack/http-foundation, wppack/event-dispatcher

wppack/scim-plugin
    ↓ requires
wppack/scim, wppack/kernel, wppack/dependency-injection
    + wppack/event-dispatcher, wppack/security
```

## Development Guidelines

### Language
- Documentation: English
- Code: English (variable names, class names, comments)

### PHP Requirements
- PHP 8.2 or higher
- PSR-4 autoloading

### Coding Standards

**Follow modern PHP best practices. Do NOT use WordPress Coding Standards.**

- Comply with PER Coding Style (successor to PSR-12)
- Strict type declarations (`declare(strict_types=1)`)
- Use readonly properties
- Follow Symfony patterns
- Use constructor property promotion
- Use match expressions
- Use named arguments where appropriate

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
- **Use existing components first:** Before calling a WordPress function directly, check if a WpPack component already wraps it. Use the component API instead of the raw WordPress function.

| WordPress function | WpPack component method |
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
- Namespace: `WpPack\Component\Hook\Attribute\{ComponentName}\Action\` / `Filter\`
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
WpPack\Component\{Name}\  - Components
WpPack\Plugin\{Name}\     - Plugins
```

### Static Analysis & CI

```bash
vendor/bin/phpstan analyse                      # Static analysis
vendor/bin/php-cs-fixer fix --dry-run --diff    # Code style check
vendor/bin/phpunit                              # Run tests
```

### Testing

#### Test Configuration

Tests run in a WordPress integration test environment using wp-phpunit + MySQL. `tests/bootstrap.php` always fully loads WordPress, so MySQL must be started via Docker before running tests.

#### Running Tests Locally

```bash
docker compose up -d --wait    # Start MySQL (required)
vendor/bin/phpunit             # Run all tests
docker compose down            # Stop MySQL
```

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

When adding a new component or Bridge package, update all of the following files:

1. **Root `composer.json`** — Add to `autoload.psr-4`, `autoload-dev.psr-4`, and `replace`
2. **`codecov.yml`** — Add `component_id` / `name` / `paths` to `individual_components`
3. **`CLAUDE.md`** — Add to the component list table and key dependencies
4. **`docs/`** — Create or update documentation for the component

### Consistency Checks for Documentation & Component Updates

- **When updating documentation**: Verify that link targets in the component list table in `docs/components/README.md` actually exist. When adding new documentation, add a link to the table and ensure path format consistency with existing links (files: `./name.md`, directories: `./name/`)
- **When updating components**: Ensure that component names, package names, and descriptions are consistent across: the component list table in `CLAUDE.md`, the table in `docs/components/README.md`, `src/Component/{Name}/README.md` (package README), and the implementation under `src/` (namespaces, directory names, `composer.json`)

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

All packages are pre-release (in design phase), so backward compatibility is not a concern. API changes, parameter reordering, class renames, and deletions may be done freely.

## Status

- All packages: In design phase

## Updating This File

Update this CLAUDE.md as needed when the project changes:

- When new packages or modules are added
- When architecture or design principles change
- When coding standards are updated
- When important development rules or commands are added
- When the project status changes
