# wppack/dsn

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=dsn)](https://codecov.io/github/wppack-io/wppack)

Data Source Name (DSN) parser. Canonical DSN parsing implementation used
across the WPPack monorepo by Database, Cache, Mailer, Storage, and
Monitoring components.

## Install

```bash
composer require wppack/dsn
```

## Usage

```php
use WPPack\Component\Dsn\Dsn;

$dsn = Dsn::fromString('mysql://user:pass@host:3306/dbname?charset=utf8mb4');

$dsn->getScheme();           // 'mysql'
$dsn->getHost();             // 'host'
$dsn->getPort();             // 3306
$dsn->getOption('charset');  // 'utf8mb4'
```

Parse failures raise `WPPack\Component\Dsn\Exception\InvalidDsnException`.

## Documentation

Grammar, full API reference, supported formats, and integration examples
live at [`docs/components/dsn.md`](../../../docs/components/dsn.md).

## License

MIT — see [LICENSE](LICENSE).
