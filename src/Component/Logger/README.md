# WpPack Logger

A PSR-3 compliant logging component. Wraps WordPress's `error_log()` with a structured interface.

## Installation

```bash
composer require wppack/logger
```

## Usage

```php
use WpPack\Component\Logger\Logger;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$logger = new Logger('app');
$logger->pushHandler(new ErrorLogHandler());

$logger->info('User {username} logged in', [
    'username' => 'john',
    'ip' => '127.0.0.1',
]);
// Output: [app.INFO] User john logged in {"ip":"127.0.0.1"}
```

## Channel-Based Logging

```php
use WpPack\Component\Logger\LoggerFactory;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$factory = new LoggerFactory([new ErrorLogHandler()]);

$appLogger = $factory->create('app');
$securityLogger = $factory->create('security');

$securityLogger->warning('Failed login attempt', ['username' => 'admin']);
// Output: [security.WARNING] Failed login attempt {"username":"admin"}
```

## Testing

```php
use WpPack\Component\Logger\Logger;
use WpPack\Component\Logger\Test\TestHandler;

$logger = new Logger('test');
$handler = new TestHandler();
$logger->pushHandler($handler);

$logger->info('Payment successful', ['amount' => 100]);

$handler->hasInfo('Payment successful');           // true
$handler->hasInfoThatContains('Payment', ['amount' => 100]); // true
```

## Documentation

For details, see [docs/components/logger/](../../../docs/components/logger/).

## License

MIT
