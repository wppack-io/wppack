# WpPack Logger

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=logger)](https://codecov.io/github/wppack-io/wppack)

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

## PHP Error Capture

Capture PHP errors (warnings, deprecations, notices) as PSR-3 logs:

```php
use WpPack\Component\Logger\ErrorHandler;
use WpPack\Component\Logger\LoggerFactory;
use WpPack\Component\Logger\ChannelResolver\DefaultChannelResolver;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$factory = new LoggerFactory([new ErrorLogHandler()]);
$resolver = new DefaultChannelResolver();

$handler = new ErrorHandler($factory, $resolver);
$handler->register();

// PHP errors are now captured as PSR-3 logs:
// E_WARNING       → warning
// E_DEPRECATED    → notice + context['_type' => 'deprecation']
// E_NOTICE        → notice
// E_RECOVERABLE   → error
```

## Channel Resolver

Resolve file paths to channel names via `ChannelResolverInterface`:

```php
use WpPack\Component\Logger\ChannelResolver\DefaultChannelResolver;

$resolver = new DefaultChannelResolver(); // always returns 'php'
$resolver->resolve('/any/path'); // 'php'
```

The default `WordPressChannelResolver` resolves `plugin:slug`, `theme:slug`, `wordpress`, or `php` from file paths using WordPress constants (`WP_PLUGIN_DIR`, `WPMU_PLUGIN_DIR`, `ABSPATH`).

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
