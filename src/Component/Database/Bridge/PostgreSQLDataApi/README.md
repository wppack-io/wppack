# WPPack Aurora PostgreSQL Data API Database Driver

Aurora PostgreSQL Data API driver for the WPPack Database component. Uses RDS Data API (HTTP) instead of native PostgreSQL connections.

## Installation

```bash
composer require wppack/postgresql-data-api-database
```

## DSN Format

```
pgsql+dataapi://cluster-arn/dbname?secret_arn=arn:aws:secretsmanager:...&region=us-east-1
```

## Notes

- **Error classification** — AWS-side exceptions (Throttling / Timeout / ExpiredToken / InvalidSignature) are mapped to `DriverThrottledException` / `DriverTimeoutException` / `CredentialsExpiredException`. `DataApiErrorClassificationTest` pins the mapping.
- **1 MB response cap** — the Data API returns at most ~1 MB per call with no paging. Large SELECTs must be bounded by `LIMIT` / keyset pagination on the caller side. Responses over 5000 rows emit a logger warning.
- **No `search_path` support** — unlike `PostgreSQLDriver` / `AuroraDSQLDriver`, the Data API is HTTP-stateless, so a `SET search_path` does not persist across calls. If schema scoping is required, issue `SET LOCAL search_path TO ...` inside an explicit `transactionId` scope.
- **`lastInsertId()`** — `SELECT lastval()` is undefined between HTTP calls; this driver suppresses the failure and returns 0.
