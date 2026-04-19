# Monolog Logger

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=monolog_logger)](https://codecov.io/github/wppack-io/wppack)

**Package:** `wppack/monolog-logger`
**Namespace:** `WPPack\Component\Logger\Bridge\Monolog\`

A Monolog bridge for WPPack Logger. Keeps the WPPack Logger frontend (`LoggerFactory` + `Logger`) intact and routes log records to Monolog via `MonologHandler` (a WPPack `HandlerInterface` implementation).

## Installation

```bash
composer require wppack/monolog-logger
```

## Usage

```php
use WPPack\Component\Logger\Bridge\Monolog\MonologHandler;
use WPPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use WPPack\Component\Logger\LoggerFactory;
use Monolog\Handler\StreamHandler;

$monologFactory = new MonologLoggerFactory(
    defaultHandlers: [new StreamHandler('/path/to/app.log')],
);

$handler = new MonologHandler($monologFactory);
$loggerFactory = new LoggerFactory(defaultHandlers: [$handler]);

$logger = $loggerFactory->create('app');
$logger->info('Hello {name}', ['name' => 'World']);
```

## Dependencies

- `wppack/logger` ^1.0
- `monolog/monolog` ^3.0

## Documentation

See [docs/components/logger/monolog-logger.md](../../../../docs/components/logger/monolog-logger.md) for details.

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Developed in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).
