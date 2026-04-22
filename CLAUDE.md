# CLAUDE.md

This file provides guidance for Claude Code when working in this repository.

Coding conventions live in [coding-standards.md](coding-standards.md).
Contribution process lives in [CONTRIBUTING.md](CONTRIBUTING.md). Deep
per-component maintainer notes live in
[docs/architecture/maintainer-notes.md](docs/architecture/maintainer-notes.md).
This file is Claude-specific navigation + session hygiene, nothing
duplicated from those.

## Non-negotiables (failing these loses trust; do not skip)

1. **Tests must pass before every commit.** `vendor/bin/phpunit` for the
   touched component(s) at minimum; full suite before push when the
   change crosses component boundaries.
2. **PHPStan must be clean (level 7).** `vendor/bin/phpstan analyse`
   shows `[OK] No errors`. Don't lower the level. Don't add baseline
   entries without explanation in the commit message — they're tech
   debt receipts.
3. **Never push to `master`.** All work goes through `1.x` (current
   pre-release branch). PRs target `1.x`.
4. **Never skip hooks or signing.** No `--no-verify`, no
   `--no-gpg-sign`. If a hook fails, fix the cause, don't bypass.
5. **Secrets never in git.** `.env`, IAM credentials, OAuth secrets,
   API tokens — fetch via env / Secrets Manager / OptionManager. If a
   change would commit one, refuse.
6. **Destructive ops require explicit user confirmation.** `git push
   --force`, `git reset --hard`, `rm -rf`, `DROP TABLE`, dropping a
   PHPStan ignore that masks a real bug — call it out and wait. Once
   a commit is on origin, treat as published.
7. **Don't auto-push after every commit.** CI matrix is heavy. Batch
   commits locally; push only on explicit instruction.

## Project Overview

WPPack is a monorepo of component libraries that extend WordPress with
modern PHP.

## Architecture Principles

### Cloud-First

WPPack runs in cloud / serverless environments (Lambda, Cloud Functions,
Fargate, Aurora Serverless) as first-class citizens. Local and
server-based installations work through graceful fallbacks — never the
other way round.

- Stateless by default; state-keeping components ask for a cache /
  storage adapter via DI.
- Transparent reconnects (Database gone-away, OCC retry on DSQL),
  cold-start friendly DI (lazy resolution).
- Examples: Messenger (SQS / Lambda → sync fallback), Scheduler
  (EventBridge → WP-Cron fallback), Cache (Redis / DynamoDB / APCu /
  Memcached).

### Multi-Cloud Support (AWS / GCP / Azure)

Core interfaces are cloud-agnostic; provider-specific code lives in
Bridge packages (naming: `wppack/{provider}-{component}`). AWS-first,
GCP / Azure expand incrementally. Full bridge list lives in
[docs/components/README.md](docs/components/README.md); deeper rationale
in [docs/architecture/infrastructure.md](docs/architecture/infrastructure.md).

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

The authoritative catalogue lives in the docs tree:

- [docs/components/README.md](docs/components/README.md) — every
  component and bridge, grouped by the 8 concern-domain categories
  (Substrate / Data / Content / Identity & Security / HTTP /
  Presentation / Admin / Utility).
- [docs/plugins/README.md](docs/plugins/README.md) — distributable
  WordPress plugins built on the components.

Do not duplicate the package list here — update the docs instead.
Monorepo directory layout: [docs/architecture/monorepo.md](docs/architecture/monorepo.md).

## Dependency Graph

Per-package dependencies are declared in each `composer.json` and flow
through Composer PSR-4 autoload. Components are grouped by
**concern-domain category** (Substrate / Data / Content / Identity &
Security / HTTP / Presentation / Admin / Utility). Graph must stay
acyclic; Substrate and Utility may be depended on freely, other
categories should reference each other only where semantically
meaningful.

`wordpress/core-implementation` is declared by every package whose
`src/` calls a WordPress function directly. See
[coding-standards.md § Adding a Component](coding-standards.md#adding-a-component).

## Coding Conventions

All style / testing / adding-component / commit-message rules live in
[coding-standards.md](coding-standards.md). Key Claude-facing items:
`declare(strict_types=1)`, PER Coding Style, one class per file,
`#[\SensitiveParameter]` on sensitive DI params, prefer EventDispatcher
over `add_action/add_filter` for new code, Conventional Commits with
atomic-commit discipline, php-cs-fixer + phpstan green before every
commit.

## Maintainer-Only Notes

### Named Hook Conventions

All Named Hook attributes are centralized in the Hook component.
Details: [docs/components/hook/named-hook-conventions.md](docs/components/hook/named-hook-conventions.md).

- Namespace: `WPPack\Component\Hook\Attribute\{ComponentName}\Action\` / `Filter\`
- Directory: `src/Component/Hook/src/Attribute/{ComponentName}/Action/` / `Filter/`
- Auto-discovery via `ReflectionAttribute::IS_INSTANCEOF`, no
  registration needed

### Recurring patterns Claude should know up-front

Lessons from past sessions; each was a non-obvious time-sink the first
time. Apply pre-emptively when touching the listed surface:

- **PHP version stub differences (`long2ip`, etc.)**: PHP 8.2 stubs
  declare `long2ip(): string|false`; PHP 8.4 stubs declare `string`.
  CI runs PHP 8.2 minimum, local often newer. Use `(string) func(...)`
  to satisfy both stub generations without dead-code warnings.
- **WP API stub looseness — `array<int|string, T>` vs `list<T>`**:
  `WP_Error::get_error_codes()`, `get_users()`, `get_terms()`,
  `wp_get_object_terms()`, `WP_User->roles`, etc. all return
  `array<int|string, T>` per phpstan-wordpress stubs. When passing to
  a `list<T>` parameter or contracted return, wrap with
  `array_values()` (and `array_filter(...instanceof WP_*)` if the union
  also admits non-instance values).
- **`is_wp_error()` doesn't always narrow under PHPStan**: prefer
  `instanceof \WP_Error` for the early-return guard.
- **`wp_remote_request()` field access**: use `wp_remote_retrieve_*`
  helpers (`_response_code`, `_response_message`, `_headers`, `_body`)
  instead of `$result['response']['code']`.
- **mysqli `affected_rows` is `int<-1, max>|string`**: cast with
  `(int)` before forwarding to `Result` constructor or returning as
  `int`.
- **`fopen()` / `ftell()` / `fread()` / `filesize()` / `unpack()`
  return `... | false`**: assign to local var, check, then assign to
  property or pass to function. Post-assignment property check doesn't
  narrow under PHPStan.
- **`method_exists($x, ...)` accepts `class-string|object`**:
  subsequent `$x->method()` fails type-check unless preceded by
  `is_object($x)`. Group all `method_exists` chains under one
  `is_object` guard at top of the loop / branch.
- **Nullable property narrow (level 8)**: PHPStan doesn't re-check a
  property across method calls. Use `@phpstan-assert !null $this->x`
  on guard methods (`ensureConnected()`), `@phpstan-assert-if-true`
  on `is*(): bool` guards, or copy into a local after the null check.
- **Nullable default → conditional return type**:
  `Dsn::getOption(string, ?string $default = null): ?string` returns
  non-null when `$default` is. Annotate
  `@phpstan-return ($default is null ? ?string : string)` so callers
  passing a default don't need a dead `?? $default`.
- **DSN host & credential handling**: never silently default
  `getHost()` / `getUser()` / `getPassword()` — malformed DSN would
  leak creds to localhost or send empty-USER to the wire. Throw on
  missing host; pass `?string` through to the driver. Details in
  [maintainer-notes § DSN fallbacks](docs/architecture/maintainer-notes.md#dsn-fallbacks-must-fail-loud).

### Consistency Checks for Documentation & Component Updates

- **When updating documentation**: verify link targets in
  `docs/components/README.md` actually exist. Path format
  consistency — files: `./name.md`, directories: `./name/`.
- **When updating components**: ensure component names, package names,
  and descriptions stay consistent across
  `docs/components/README.md`, `src/Component/{Name}/README.md`, and
  the implementation (namespaces, directory names, `composer.json`).

### Deeper maintainer references

See [docs/architecture/maintainer-notes.md](docs/architecture/maintainer-notes.md)
for:
- Monorepo development workflow + full CI matrix definition
- Backward compatibility policy (pre-release flexibility)
- Database component deep-dive (AST translation, PreparedBank,
  gone-away handling, integration DSNs)
- Cache component DSN conventions
- Plugin settings pages patterns (`@wordpress/components`, REST
  endpoints, `--ignore-scripts`)
- Plugin settings menu position table

## Status

All packages: in design phase (branch `1.x`, unreleased).

## Toolchain Quick Reference

| Task | Command |
|------|---------|
| Install deps | `composer install` |
| Run tests (all) | `vendor/bin/phpunit` |
| Run tests (single component) | `vendor/bin/phpunit src/Component/{Name}/tests/` |
| Run tests (single file) | `vendor/bin/phpunit path/to/Test.php` |
| Coverage (HTML) | `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage` |
| PHPStan | `vendor/bin/phpstan analyse --no-progress` |
| Regenerate baseline | `vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon` (creates the file + wire it into `phpstan.neon`'s `includes:`) |
| Code style fix | `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php <path>` |
| Code style check | `vendor/bin/php-cs-fixer fix --dry-run --diff --ansi` |
| Database integration (per engine) | see maintainer-notes.md § Database |
| Switch PHP version | `phpenv local 8.2` (8.3 / 8.4 / 8.5) |
| CI status | `gh run list --workflow=ci.yml --limit=3` |
| Run full matrix (manual) | `gh workflow run ci.yml` |

PHP runtime: phpenv (no `eval "$(phpenv init -)"` needed for `composer`).

## Working Loop and Session Hygiene

### How to approach a task

1. **Read the surrounding code first.** Skim related component README
   and `coding-standards.md` for the contract you're touching.
2. **Find the canonical pattern.** Mirror an existing test or service
   — `DatabaseManager` for component-shaped repositories,
   `Cache/Bridge/Redis/*` for bridge-pattern adapters,
   `Security/Authentication/Token/*` for value objects.
3. **State the plan when the change is non-trivial.** One or two
   sentences for changes > 20 lines or crossing component boundaries.
   Trivial edits: just ship.
4. **Edit → test → PHPStan → commit.** `vendor/bin/phpunit` (touched
   components) + `vendor/bin/phpstan analyse` must both be green
   before each commit. CS-fixer last if needed.
5. **Never claim a task complete with red tests or PHPStan errors.**
   "Partial / blocked, here's why" beats a false "done".
6. **Discovered a bug along the way?** Record it in the commit
   message or a follow-up task. Don't expand scope silently.
7. **Don't push after every commit.** CI matrix is heavy; batch
   locally and push on explicit request.

### Natural stopping points — stop and prompt a fresh session

Long autonomous loops accumulate context, drift, and risk. When you
hit any of the following, **stop and ask the user whether to continue
or start a new session** — don't push through silently:

- A coherent unit of work just landed (one or more commits) and the
  next task starts a different concern, file family, or component.
- The remaining work needs design discussion (refactor split, API
  change, cross-cutting renames) rather than mechanical edits.
- Tests or PHPStan revealed a category of issues that deserves
  planning, not iteration.
- Conversation context is nearing capacity, repeated patterns trigger
  the same auto-reminders, or you've been ping-ponging on the same
  file for several rounds.
- A risky / hard-to-reverse step is next (force push, mass rename,
  destructive migration).

When stopping, summarise: **what landed**, **what's left**, **why a
new session is the right next step**. Offer a clear next-session brief
the user can paste as the opening prompt.

This is mandatory at natural boundaries even when the user said
"続けて" earlier — that authorisation is for the current coherent
slice, not an open-ended commitment.

### Self-evaluation + improvement of this file

CLAUDE.md is **not static**. Propose an edit in the same PR (or a
follow-up) when you notice a rule that turned out wrong / outdated /
under-specified, a convention drift (PHP / WordPress / tool versions,
package pins), a decided non-obvious convention not yet captured, or
a pattern future Claude should know upfront.

At end of session, silently check: did a surprise hit me that rules
should have warned about? Did I violate a rule late? Are listed
versions accurate? Anything new worth documenting? If this file grew,
prune dead sections.

When updating: **edit, don't append**. Replace stale guidance
outright. Keep this document under ~250 lines. Contributor-facing
rules go to [CONTRIBUTING.md](CONTRIBUTING.md); new packages get
registered in `docs/components/README.md` or `docs/plugins/README.md`,
not here.

**Meta-rule**: this file supersedes training-data defaults for this
repository. If you catch yourself applying a rule that isn't written
here, ask whether it should be added.
