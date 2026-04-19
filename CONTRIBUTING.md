# Contributing to WPPack

Thanks for considering a contribution. This guide covers the contribution
process — how to report issues, propose features, and submit pull requests.
For code-level conventions (style, commit format, testing, adding packages)
see [coding-standards.md](coding-standards.md).

## Current Status

WPPack is in active design phase on the `1.x` branch. No stable release has
been tagged. Backward compatibility is not preserved across commits — API
changes, parameter reordering, and renames may happen at any time. Target
`1.x` for pull requests.

## Ways to Contribute

- **Report a bug** — open a GitHub Issue
- **Suggest a feature** — open a GitHub Discussion or Issue
- **Improve documentation** — docs live in `docs/` and `src/Component/**/README.md`
- **Submit a code change** — fix a bug, implement a feature, add tests
- **Review pull requests** — feedback from other contributors is valuable

## Before You Start

- **Search first.** Check existing Issues, Discussions, and open PRs to see
  if your topic is already covered.
- **Discuss significant changes.** For anything non-trivial — new
  components, API renames, architectural shifts, new Bridge packages — open
  a Discussion or draft Issue before writing code. A single maintainer
  reviews all PRs; aligning early saves rework.
- **Small fixes go straight to PR.** Typo fixes, obvious bugs, docs
  improvements don't need pre-discussion.

## Reporting a Bug

Open a [GitHub Issue](https://github.com/wppack-io/wppack/issues) and
include:

- **Expected vs actual behaviour**
- **Environment** — PHP version, WordPress version, database engine (mysql /
  sqlite / postgresql / aurora-dsql), component version
- **Minimal reproduction** — the smallest code snippet that triggers it
- **Stack trace / log output** if any
- **What you've already tried**

## Requesting a Feature

Open a Discussion (preferred) or Issue describing:

- **The use case** — what you're trying to accomplish (not how)
- **Which component(s) would be affected**
- **Alternatives considered**
- **Whether you'd be willing to submit a PR**

### Scope

WPPack delivers two kinds of packages:

- **Components** — type-safe wrappers around WordPress-provided APIs
  (Options, Transient, Hook, REST, HTTP, Mailer, Cache, Database, Form,
  etc.) plus integrations with related OSS libraries (Monolog, Twig,
  lightsaml, web-auth, etc.).
- **Plugins** — production-ready implementations of functionality tied to
  WordPress's basic operation (SSO login, Object Cache, Media Storage,
  Mail Transport, SCIM provisioning, Scheduler, etc.).

End-user application features — e-commerce, page builders, domain-specific
business logic — are out of scope. Build those on top of WPPack in your
own packages.

Proposals within scope get faster review. Out-of-scope proposals need a
Discussion alignment first.

## Development Setup

```bash
git clone https://github.com/wppack-io/wppack.git
cd wppack
composer install
docker compose up -d --wait
```

`docker compose up -d --wait` starts MySQL (dev + test), PostgreSQL, Redis /
Valkey, Memcached, DynamoDB Local, and Keycloak for integration tests. The
test MySQL instance uses a tmpfs volume and is reset on container restart
(credentials: `root` / `password`, port `3307`).

## Pull Request Process

1. **Branch from `1.x`.** All work happens on `1.x` until a stable release
   is cut.
2. **Make atomic commits** using Conventional Commits format — see
   [coding-standards.md § Commit Messages](coding-standards.md#commit-messages).
3. **Run checks locally before pushing:**
   ```bash
   vendor/bin/phpstan analyse
   vendor/bin/php-cs-fixer fix
   vendor/bin/phpunit
   ```
   See [coding-standards.md § Static Analysis and Lint](coding-standards.md#static-analysis-and-lint)
   and [§ Testing](coding-standards.md#testing) for the full matrix.
4. **Push your branch** and open a PR targeting `1.x`.
5. **CI must pass.** The 16-job matrix (PHP 8.2 / 8.3 / 8.4 / 8.5 × mysql /
   sqlite / postgresql / wpdb) runs on every push. Red CI is a blocker.
6. **Expect review turnaround in days, not hours.** This is a single-
   maintainer project; please be patient.
7. **Squash-merge by default.** Exceptions only when the atomic-commit
   history is intentionally useful.

### What reviewers look for

- Follows [coding-standards.md](coding-standards.md)
- Tests accompany new behaviour or bug fixes
- Docs updated where behaviour changes
- No unrelated changes bundled into the PR
- Commit messages explain *why*, not just *what*

## Code of Conduct

Be respectful and constructive. A formal `CODE_OF_CONDUCT.md` will be added
before the stable release; in the meantime, treat every interaction as if
one were already in place.

## License

By submitting a pull request, you agree that your contribution will be
licensed under the [MIT License](LICENSE).
