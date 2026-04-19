# Database Component

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=database)](https://codecov.io/github/wppack-io/wppack)

Type-safe WordPress `$wpdb` replacement with exception-based error handling, native prepared statements across every supported engine, multi-engine query translation, PSR logger + PSR-14 event integration, and `dbDelta()`-based schema management.

## Highlights

- **WPPackWpdb** — drop-in `$wpdb` replacement used through the `db.php` drop-in. Overrides `prepare()` to use per-request PreparedBank markers, so parameters are never spliced into SQL text.
- **Native prepared statements everywhere** — `mysqli::prepare()` on MySQL/MariaDB, `PDO::prepare()` on SQLite, `pg_prepare()` / `pg_query_params()` on PostgreSQL, RDS Data API parameter binding on Aurora Data API + DSQL.
- **Multi-engine query translation** — AST-guided MySQL → SQLite / PostgreSQL / Aurora DSQL rewrite covering 50+ functions (date, string, JSON, network, …), INSERT IGNORE, REPLACE INTO, ON DUPLICATE KEY UPDATE, DELETE JOIN, UPDATE/DELETE LIMIT, CREATE TABLE DDL. Unsupported features (FULLTEXT, spatial) surface as explicit `UnsupportedFeatureException` rather than silent pass-through.
- **Reader/Writer affinity** — `DATABASE_READER_DSN` routes SELECT-like queries to the reader driver; any write pins subsequent reads to the writer for read-your-own-writes semantics across replication lag.
- **Connection resilience** — both MysqlDriver and PgsqlDriver detect gone-away / fatal disconnect errors, drop the stale handle, and reopen transparently on the next call. Modern mysqli exception mode is honoured. See `MysqlGoneAwayTest` / `PgsqlGoneAwayTest` for the guaranteed error-code coverage.
- **Aurora DSQL** — IAM token SigV4 presign, auto-refresh 120s before expiry, OCC retry with exponential backoff + decorrelated jitter, `occMaxRetries` tunable. `OccRetryTest` locks down the retry / rollback / token-refresh boundary.
- **RDS Data API** — AWS-side exception classification into `DriverThrottledException` / `DriverTimeoutException` / `CredentialsExpiredException` so callers can make intelligent retry decisions. Documents the 1 MB response cap. `DataApiErrorClassificationTest` pins the mapping.
- **PostgreSQL schema isolation** — PgsqlDriver accepts a `searchPath` list (or the `?search_path=` / `?schema=` DSN options) and emits `SET search_path TO ...` on connect. Re-applied after transparent reconnect. Aurora DSQL inherits this via `applySearchPath()`. `PgsqlSearchPathTest` covers the full matrix.
- **Observability** — PSR-3 logger (params are redacted to type+length summaries by default; `WPPACK_DB_LOG_VALUES=1` opts raw values in for local debugging), `WPPACK_DB_SLOW_QUERY_MS` threshold, PSR-14 `DatabaseQueryCompletedEvent` / `DatabaseQueryFailedEvent` for APM listeners.
- **Transaction depth** — warns on nested `BEGIN` (MySQL footgun), correctly preserves depth across `SAVEPOINT` / `ROLLBACK TO SAVEPOINT` / `RELEASE SAVEPOINT`.
- **Persistent connections** — `persistent: true` constructor flag on both MysqlDriver and PgsqlDriver for `p:` / `pg_pconnect` in WP-CLI workers.

## Installation

```bash
composer require wppack/database
```

## Basic Usage

```php
use WPPack\Component\Database\DatabaseManager;

$db = new DatabaseManager();

// Doctrine DBAL-style fetch API (array parameters)
$rows = $db->fetchAllAssociative(
    "SELECT * FROM {$db->prefix()}analytics WHERE status = %s",
    ['active'],
);

$row = $db->fetchAssociative(
    "SELECT * FROM {$db->prefix()}analytics WHERE id = %d",
    [$id],
);

$count = $db->fetchOne(
    "SELECT COUNT(*) FROM {$db->prefix()}analytics WHERE status = %s",
    ['active'],
);

// Table operations (automatic prefix applied)
$db->insert('analytics', ['name' => 'test', 'status' => 'active']);
$db->update('analytics', ['status' => 'inactive'], ['id' => 1]);
$db->delete('analytics', ['id' => 1]);

// Transactions
$db->beginTransaction();
try {
    $db->insert('analytics', ['name' => 'tx_test']);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

## Bridge Packages

| Package | Purpose |
|--------|---------|
| `wppack/sqlite-database` | SQLite driver (ext-pdo_sqlite) |
| `wppack/pgsql-database` | PostgreSQL driver (ext-pgsql) |
| `wppack/mysql-data-api-database` | Aurora MySQL Data API (async-aws/rds-data-service) |
| `wppack/pgsql-data-api-database` | Aurora PostgreSQL Data API |
| `wppack/aurora-dsql-database` | Aurora DSQL with IAM + OCC retry (async-aws/core) |

## Documentation

- [docs/components/database/README.md](../../docs/components/database/README.md) — full reference: DatabaseManager API, WPPackWpdb internals, production operations (observability / env vars / reconnect / transactions), exception hierarchy, bridge configuration.
- [docs/components/database/query-translation.md](../../docs/components/database/query-translation.md) — MySQL→SQLite / PostgreSQL function + statement + DDL translation reference.
- [docs/components/database/plugin-comparison.md](../../docs/components/database/plugin-comparison.md) — comparison with SQLite Database Integration, PG4WP.
