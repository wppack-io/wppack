# Coding Standards

Canonical reference for how code in this repository is structured, styled, and
committed. Applies to every component, bridge, and plugin under `src/`.

## PHP Version and Language Features

- PHP 8.2 or higher
- `declare(strict_types=1)` at the top of every PHP file
- PHP 8.2+ idioms: constructor property promotion, `readonly`, `enum`, `match`,
  named arguments

## Code Style

- Follow PER Coding Style (successor to PSR-12) — `php-cs-fixer` enforces it
- One class per file, PSR-4 autoloading
- Do **not** follow WordPress Coding Standards

## `final` Keyword Policy

Default is **no** `final`. Add `final` only when extension makes no sense.

| Class type | `final` | Reason |
|---|---|---|
| Value objects / DTOs | `final readonly` | Immutable, no subclassing needed |
| Configuration classes | `final readonly` | Immutable |
| All others | No | Preserve testability and extensibility |

## `#[\SensitiveParameter]` Usage

Apply to parameters that receive **secrets**: passwords, API keys, access
keys, encryption keys, authentication tokens (JWT, IAM tokens, etc.). This
replaces the parameter value with `SensitiveParameterValue` in exception
stack traces, preventing leakage to logs and screens.

```php
public function __construct(
    #[\SensitiveParameter]
    private readonly ?string $password,
) {}
```

Do **not** apply to:

- Public information (`$clientId`, `$issuer`, JWKS public keys)
- CSRF nonces
- Object types (only type names appear in stack traces)
- Entire DSN strings (scheme / host would be hidden — protect just the
  `$password` parameter)
- Entire `$options` arrays (harms debugging — wrap the individual secret
  parameters)

## `function_exists()` Guards

WordPress is always loaded in WPPack's runtime. Guards for WordPress **core**
functions are unnecessary. Guards ARE needed for:

- Multisite-only functions: `get_sites`, `switch_to_blog`, etc.
- PHP extensions: `apcu_enabled`, `bzcompress`, `finfo_open`, etc.
- `wp-admin` functions requiring `require_once`: `dbDelta`, `wp_delete_user`

## Wrapping WordPress Functions

WordPress global functions should **not** be called directly from application
code. Wrap them in a dedicated class and inject via DI. This enables type-safe
usage, testability, and a single point of change.

### Naming

- **No suffix (plain noun)** — thin wrapper / façade over a small set of
  related functions: `AuthenticationSession`, `TokenStorage`, `PasswordHasher`
- **`*Manager` suffix** — orchestrates multiple sub-components or manages CRUD
  lifecycle of a resource: `AuthenticationManager`, `AccessDecisionManager`,
  `SamlSessionManager`

### Example

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
private function establishAuthSession(TokenInterface $token): void
{
    wp_clear_auth_cookie();
    wp_set_auth_cookie($token->getUser()->ID, false, is_ssl());
}
```

### Check existing WPPack components first

Before adding a new wrapper, check if an existing component already covers
what you need:

| WordPress function | WPPack component method |
|---|---|
| `wp_logout()`, `wp_set_auth_cookie()`, `get_current_user_id()` | `AuthenticationSession::logout()` / `login()` / `getCurrentUserId()` |
| `flush_rewrite_rules()`, `add_rewrite_rule()` | `RouteRegistry::flush()` / `addRoute()` |
| `wp_insert_post()`, `get_post()` | `PostType` component |
| `switch_to_blog()`, `restore_current_blog()` | `BlogContext` component |

### Prefer DI over `new`

Always inject dependencies via the constructor. Never use `new ClassName()`
as a default parameter value — it hides the dependency graph.

### Scope

- **Wrap** functions used across multiple classes or that affect external
  state (auth cookies, sessions, user switching, rewrite rules)
- **Do not wrap** functions called only inside a single, self-contained
  utility (e.g. `home_url()` in a URL builder) or inside hooks registered
  before the DI container boots

## Hook vs EventDispatcher

Prefer **EventDispatcher** (`#[AsEventListener]`) for new code. The Hook
component (`wppack/hook`) is kept for backward compatibility only.

EventDispatcher uses WordPress's `$wp_filter` as its backend, so WordPress
hooks (actions / filters) can also be handled in a type-safe manner via
`WordPressEvent` / extended event classes.

| Case | Recommendation |
|---|---|
| Hooks before DI container boot (`plugins_loaded`, etc.) | Direct `add_action()` / `add_filter()` |
| WordPress hooks after `init` | **EventDispatcher** (`WordPressEvent` / `#[AsEventListener]`) |
| Application-specific domain events | **EventDispatcher** |
| Loose coupling between components | **EventDispatcher** |

## WordPress Version Compatibility

When a hook or function is renamed between WordPress releases, detect the
version with `version_compare(get_bloginfo('version'), '...')` and select
one path — **not both**, to avoid double-firing.

```php
// setted_transient → set_transient in WP 6.8
$useNewHooks = version_compare(get_bloginfo('version'), '6.8', '>=');
$setHook = $useNewHooks ? 'set_transient' : 'setted_transient';
add_action($setHook, [$this, 'onTransientSet'], 10, 3);
```

- Do not register both old and new hooks simultaneously (causes double-firing)
- Do not continue using deprecated hooks / functions (causes deprecation
  warnings)
- Document the supported version range in comments
  (e.g. `// WP 6.8+: ... / WP < 6.8: ...`)

## Namespaces and Directory Layout

```
WPPack\Component\{Name}\  – Components  → src/Component/{Name}/
WPPack\Plugin\{Name}\     – Plugins     → src/Plugin/{Name}/
```

All packages are managed by the monorepo root `composer.json` and published
as individual repositories via `splitsh-lite`.

## Component Documentation Layout

- `src/Component/{Name}/README.md` — concise package overview only: a
  one-paragraph description, install command, one or two minimal usage
  snippets, and a pointer to the full docs. Keep under ~60 lines.
- `docs/components/{name}.md` (or `docs/components/{name}/`) —
  authoritative reference: scope, grammar / API detail, corner cases,
  integration examples, design notes.

The package README is the first thing a user sees on Packagist. Keep it
scannable. Everything that someone might want to look up twice belongs in
`docs/`.

## Parsing DSN Strings

WPPack uses **`WPPack\Component\Dsn\Dsn`** as the canonical DSN parser.
Anywhere you need to turn a DSN-like string (database / cache / storage /
mailer / messenger / monitoring connection string) into structured fields,
use this class.

```php
use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Dsn\Exception\InvalidDsnException;

try {
    $dsn = Dsn::fromString($dsnString);
} catch (InvalidDsnException) {
    // handle invalid input
}

$dsn->getScheme();
$dsn->getHost();
$dsn->getOption('region');
```

**Do not** use `parse_url($dsn)`, `parse_str(...)`, or ad-hoc regex /
string splitting on DSN values. The canonical parser handles IPv6,
URL-encoded credentials, `scheme:?query` forms, Unix socket paths
(`scheme:///path`), and repeated array parameters uniformly — rolling
your own will drift.

Every package whose `src/` calls the canonical parser must declare the
dependency in its `composer.json`:

```json
"require": {
  "wppack/dsn": "^1.0"
}
```

## Commit Messages

Based on [Conventional Commits](https://www.conventionalcommits.org/).

```
<type>(<scope>): <summary>

<body>
```

### Summary line

- **72 characters or fewer** (fits in `git log --oneline`)
- Type prefix + optional scope, imperative mood (`Add`, `Fix`, `Refactor`)

| type | Purpose |
|---|---|
| `feat` | New feature |
| `fix` | Bug fix |
| `refactor` | No behaviour change |
| `docs` | Documentation-only |
| `test` | Test additions / modifications only |
| `chore` | Build, CI, dependency maintenance |

### Body

- Separate from the summary with a blank line
- `-` bullet points
- Explain the **why**, not only the what

### Commit granularity

One commit = one logical unit. If `git revert` on that commit would undo a
meaningful self-contained change, the granularity is right. Combine feature
code with its tests and directly related docs; split unrelated refactors.

### Example

```
feat(Admin,DashboardWidget,Setting): add render() shortcut

- Add render() method to AbstractAdminPage, AbstractDashboardWidget,
  AbstractSettingsPage that delegates to TemplateRendererInterface
- Registry classes accept optional TemplateRendererInterface in the
  constructor and inject via setter during register()
- Setting uses $templateRenderer to avoid collision with $renderer
  (SettingsRenderer)
```

## Static Analysis and Lint

Run these before every commit. CI fails if they do not pass.

```bash
vendor/bin/phpstan analyse                      # level 8 across all components
vendor/bin/php-cs-fixer fix --dry-run --diff    # check only
vendor/bin/php-cs-fixer fix                     # apply fixes
```

## Testing

Tests are PHPUnit-based and run in a real WordPress integration environment
via `wp-phpunit`. `tests/bootstrap.php` always loads WordPress, so Docker
services must be up.

```bash
# Default: MySQL via legacy wpdb
vendor/bin/phpunit

# Target a specific database engine
DATABASE_DSN='sqlite:///tmp/wppack-test.db' vendor/bin/phpunit
DATABASE_DSN='mysql://root:password@127.0.0.1:3307/wppack_test' vendor/bin/phpunit
DATABASE_DSN='pgsql://wppack:wppack@127.0.0.1:5433/wppack_test' vendor/bin/phpunit
```

Tests for each component live in `src/Component/{Name}/tests/`.

### Mocking WordPress Functions

Mock HTTP calls using the `pre_http_request` filter. Do **not** extend
`HttpClient` with anonymous classes — it breaks the clone-based immutability.

```php
protected function setUp(): void
{
    add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
}

protected function tearDown(): void
{
    remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
}
```

## Adding a Component

Create `src/Component/{Name}/` with:

- `src/` — source code
- `tests/` — tests
- `composer.json` — package definition
- `README.md` — package README (English)
- `LICENSE` — MIT license
- `.gitignore` — `vendor/`, `composer.lock`, `phpunit.xml`
- `phpunit.xml.dist` — PHPUnit configuration
- `.github/PULL_REQUEST_TEMPLATE.md` — subtree-split PR template
- `.github/workflows/close-pull-request.yml` — auto-close PRs on the
  read-only split repo

If the package's `src/` calls WordPress functions, constants, or classes
directly, declare the dependency in its `composer.json`:

```json
"require": {
  "wordpress/core-implementation": "^6.3"
}
```

Pure-PHP packages (value objects, SDK adapters, AST translators) do not
need it.

Register the new package in:

- Root `composer.json` — `autoload.psr-4`, `autoload-dev.psr-4`, `replace`
- `codecov.yml` — `individual_components` entry
- `docs/components/README.md` — catalogue table under the correct layer
- `docs/components/{name}/` — component documentation (if introducing one)

## Adding a Plugin

Create `src/Plugin/{Name}/` with:

- `wppack-{slug}.php` — bootstrap (`Kernel::registerPlugin(...)`)
- `src/`, `tests/`, `composer.json`, `README.md`, `LICENSE`, `.gitignore`
- `.github/` — PR template and workflows (copy from an existing plugin)

Symlink into WordPress plugin discovery and commit the symlink:

```bash
cd web/wp-content/plugins && ln -s ../../../src/Plugin/{Name} wppack-{slug}
```

Use a **relative** path.

Register the new plugin in:

- Root `composer.json` — `autoload.psr-4`, `autoload-dev.psr-4`, `replace`
- `codecov.yml`
- `docs/plugins/README.md`
