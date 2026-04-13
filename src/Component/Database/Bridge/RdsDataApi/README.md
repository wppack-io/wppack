# WpPack RDS Data API Database

RDS Data API database driver for WpPack Database component. Enables serverless database access to Aurora Serverless clusters via HTTP.

## Installation

```bash
composer require wppack/rds-data-api-database
```

## Usage

### Via DSN

```php
use WpPack\Component\Database\Driver\Driver;
use WpPack\Component\Database\Connection;

$driver = Driver::fromDsn('rds-data://arn:aws:rds:us-east-1:123456789:cluster:my-cluster/mydb?secret_arn=arn:aws:secretsmanager:us-east-1:123456789:secret:my-secret');
$connection = new Connection($driver);

$rows = $connection->fetchAllAssociative('SELECT * FROM posts WHERE id = ?', [1]);
```

### Direct Construction

```php
use AsyncAws\RdsDataService\RdsDataServiceClient;
use WpPack\Component\Database\Bridge\RdsDataApi\RdsDataApiDriver;
use WpPack\Component\Database\Connection;

$client = new RdsDataServiceClient(['region' => 'us-east-1']);
$driver = new RdsDataApiDriver(
    client: $client,
    resourceArn: 'arn:aws:rds:us-east-1:123456789:cluster:my-cluster',
    secretArn: 'arn:aws:secretsmanager:us-east-1:123456789:secret:my-secret',
    database: 'mydb',
);
$connection = new Connection($driver);
```

## Features

- HTTP-based, stateless — no persistent connections
- Transaction support via Data API BeginTransaction/CommitTransaction
- Automatic parameter binding with type detection
- Region extracted from resource ARN

## License

MIT
