# WPPack DSN

Shared Data Source Name (DSN) parser for WPPack components.

## Features

- Standard URI parsing: `scheme://[user:pass@]host[:port][/path][?query]`
- Unix socket paths: `scheme:///path/to/socket`
- No-host URIs: `scheme:?query`
- Array query parameters: `key[]=value1&key[]=value2`
- URL-encoded credentials
- Sensitive parameter protection via `#[\SensitiveParameter]`

## Installation

```bash
composer require wppack/dsn
```

## Usage

```php
use WPPack\Component\Dsn\Dsn;

$dsn = Dsn::fromString('mysql://user:pass@host:3306/dbname?charset=utf8mb4');

$dsn->getScheme();   // 'mysql'
$dsn->getHost();     // 'host'
$dsn->getUser();     // 'user'
$dsn->getPassword(); // 'pass'
$dsn->getPort();     // 3306
$dsn->getPath();     // '/dbname'
$dsn->getOption('charset'); // 'utf8mb4'
```

## Supported Formats

```
mysql://user:pass@host:3306/dbname
sqlite:///path/to/database.db
redis://localhost:6379?dbindex=2
redis:?host[]=node1:6379&host[]=node2:6379
ses+https://default
s3://bucket?region=us-east-1
```

## License

MIT
