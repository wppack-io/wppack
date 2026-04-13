# WpPack Aurora DSQL Database

Aurora DSQL database driver for WpPack Database component. Uses PostgreSQL wire protocol with IAM-based token authentication.

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
use WpPack\Component\Database\Driver\Driver;
use WpPack\Component\Database\Connection;

// Token in DSN password field
$driver = Driver::fromDsn('dsql://admin:mytoken@abc123.dsql.us-east-1.on.aws/mydb');
$connection = new Connection($driver);

$rows = $connection->fetchAllAssociative('SELECT * FROM posts');
```

### Direct Construction

```php
use WpPack\Component\Database\Bridge\AuroraDsql\AuroraDsqlDriver;
use WpPack\Component\Database\Connection;

$driver = new AuroraDsqlDriver(
    endpoint: 'abc123.dsql.us-east-1.on.aws',
    region: 'us-east-1',
    database: 'mydb',
    token: 'pre-generated-iam-token',
);
$connection = new Connection($driver);
```

## License

MIT
