# WPPack Aurora MySQL Data API Database Driver

Aurora MySQL Data API driver for the WPPack Database component. Uses RDS Data API (HTTP) instead of native MySQL connections.

## Installation

```bash
composer require wppack/mysql-data-api-database
```

## DSN Format

```
mysql+dataapi://cluster-arn/dbname?secret_arn=arn:aws:secretsmanager:...&region=us-east-1
```

## Notes

- **Error classification** — AWS exceptions are mapped to `DriverThrottledException` / `DriverTimeoutException` / `CredentialsExpiredException` so callers can decide retry / auth-refresh / fatal. See `DataApiErrorClassificationTest`.
- **1 MB response cap** — the Data API returns at most ~1 MB per call with no paging. Bound large SELECTs with `LIMIT` / keyset pagination. Responses over 5000 rows emit a logger warning.
- **Query translation** — Aurora MySQL is MySQL-compatible, so no translator is applied (`NullQueryTranslator`). The WordPress MySQL dialect flows through unchanged.
