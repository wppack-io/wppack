# WPPack Aurora DSQL Database

Aurora DSQL database driver for WPPack Database component. Uses PostgreSQL wire protocol with IAM-based token authentication.

## Installation

```bash
composer require wppack/aurora-dsql-database
```

For automatic IAM token generation:

```bash
composer require async-aws/core
```

## Usage

### Via DSN

Region is extracted from the endpoint hostname:

```php
use WPPack\Component\Database\Driver\Driver;
use WPPack\Component\Database\Connection;

// Token in DSN password field
$driver = Driver::fromDsn('dsql://admin:mytoken@abc123.dsql.us-east-1.on.aws/mydb');
$connection = new Connection($driver);

$rows = $connection->fetchAllAssociative('SELECT * FROM posts');
```

### Direct Construction

```php
use WPPack\Component\Database\Bridge\AuroraDSQL\AuroraDSQLDriver;
use WPPack\Component\Database\Connection;

$driver = new AuroraDSQLDriver(
    endpoint: 'abc123.dsql.us-east-1.on.aws',
    region: 'us-east-1',
    database: 'mydb',
    token: 'pre-generated-iam-token',
);
$connection = new Connection($driver);
```

## Features

- **IAM token auth** — SigV4 presigned URL, auto-generated via `async-aws/core`. `admin` uses the `DbConnectAdmin` action; other users use `DbConnect`.
- **Token refresh** — default 900s (15 min) lifetime; the driver rotates 120s before expiry to cover SigV4 clock skew, async-aws credential-fetch latency, and the `pg_connect()` round trip. `ensureTokenFresh()` is a no-op during a transaction — closing the connection mid-tx would silently abort it, so the refresh happens at the `transaction()` retry boundary instead.
- **OCC retry** — SQLSTATE `40001` / `OC000` / `OC001` trigger exponential backoff + decorrelated jitter (`random_int(waitMs/2, waitMs)`, 100ms → 2× → 5s cap). `occMaxRetries` defaults to 3. `transaction()` retries the entire callback on conflict; individual statements inside a transaction are not retried.
- **SSL verify-full** — always enforced; `sslnegotiation=direct` is added on libpq 17+.
- **search_path / schema** — inherited from `PostgreSQLDriver`. Pass via `?search_path=tenant_42,public` or `?schema=myschema` on the DSN, or the `searchPath:` constructor argument. The DSQL `doConnect()` override explicitly calls `applySearchPath()` after its bespoke IAM-token connection string build, so reconnects after token rotation keep the schema.

## License

MIT
