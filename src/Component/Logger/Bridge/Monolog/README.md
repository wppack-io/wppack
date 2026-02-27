# Monolog Logger

**パッケージ:** `wppack/monolog-logger`
**名前空間:** `WpPack\Component\Logger\Bridge\Monolog\`

WpPack Logger の Monolog ブリッジ。`MonologLoggerFactory` を使って Monolog を PSR-3 `LoggerInterface` として利用できます。

## インストール

```bash
composer require wppack/monolog-logger
```

## 使い方

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

## 依存関係

- `wppack/logger` ^1.0
- `monolog/monolog` ^3.0

## ドキュメント

詳細は [docs/components/logger/monolog-logger.md](../../../../docs/components/logger/monolog-logger.md) を参照してください。

## リソース

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

メインリポジトリ [wppack-io/wppack](https://github.com/wppack-io/wppack) で開発しています。
