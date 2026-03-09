# Monolog Logger

**Package:** `wppack/monolog-logger`
**Namespace:** `WpPack\Component\Logger\Bridge\Monolog\`

A Monolog bridge for WpPack Logger. Use `MonologLoggerFactory` to leverage Monolog as a PSR-3 `LoggerInterface`.

## Installation

```bash
composer require wppack/monolog-logger
```

## Usage

```php
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Processor\PsrLogMessageProcessor;

$factory = new MonologLoggerFactory(
    defaultHandlers: [new ErrorLogHandler()],
    defaultProcessors: [new PsrLogMessageProcessor()],
);

$logger = $factory->create('app');
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
