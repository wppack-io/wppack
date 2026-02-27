# WpPack Logger

PSR-3 準拠のロギングコンポーネント。WordPress の `error_log()` を構造化されたインターフェースでラップします。

## インストール

```bash
composer require wppack/logger
```

## 使い方

```php
use WpPack\Component\Logger\Logger;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$logger = new Logger('app');
$logger->pushHandler(new ErrorLogHandler());

$logger->info('User {username} logged in', [
    'username' => 'john',
    'ip' => '127.0.0.1',
]);
// 出力: [app.INFO] User john logged in {"ip":"127.0.0.1"}
```

## チャンネルベースロギング

```php
use WpPack\Component\Logger\LoggerFactory;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$factory = new LoggerFactory([new ErrorLogHandler()]);

$appLogger = $factory->create('app');
$securityLogger = $factory->create('security');

$securityLogger->warning('Failed login attempt', ['username' => 'admin']);
// 出力: [security.WARNING] Failed login attempt {"username":"admin"}
```

## テスト

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

## ドキュメント

詳細は [docs/components/logger/](../../../docs/components/logger/) を参照してください。

## ライセンス

MIT
