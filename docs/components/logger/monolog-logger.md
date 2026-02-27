# MonologLogger コンポーネント

**パッケージ:** `wppack/monolog-logger`
**名前空間:** `WpPack\Component\Logger\Bridge\Monolog\`
**レイヤー:** Infrastructure

Logger コンポーネントの Monolog ブリッジ実装。`LoggerFactory` と同じインターフェースで Monolog ロガーを生成するファクトリを提供します。

## インストール

```bash
composer require wppack/monolog-logger
```

## 基本的な使い方

```php
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use Monolog\Handler\ErrorLogHandler as MonologErrorLogHandler;
use Monolog\Processor\PsrLogMessageProcessor;

$factory = new MonologLoggerFactory(
    defaultHandlers: [new MonologErrorLogHandler()],
    defaultProcessors: [new PsrLogMessageProcessor()],
);

$logger = $factory->create('app');
$logger->info('Hello {name}', ['name' => 'World']);
```

## MonologLoggerFactory

`MonologLoggerFactory` は `LoggerFactory` と同じ `create(string $name)` シグネチャを持ち、以下の機能を提供します：

- デフォルトハンドラー・プロセッサの自動適用
- チャンネル名によるロガーのキャッシュ（同名で呼び出すと同一インスタンスを返す）

### コンストラクタ

```php
public function __construct(
    array $defaultHandlers = [],
    array $defaultProcessors = [],
)
```

| パラメータ | 型 | 説明 |
|-----------|---|------|
| `$defaultHandlers` | `HandlerInterface[]` | 生成するロガーに追加するハンドラー |
| `$defaultProcessors` | `ProcessorInterface[]` | 生成するロガーに追加するプロセッサ |

### チャンネルベースロギング

```php
$factory = new MonologLoggerFactory(
    defaultHandlers: [new MonologErrorLogHandler()],
);

$appLogger = $factory->create('app');
$securityLogger = $factory->create('security');
$apiLogger = $factory->create('api');

$securityLogger->warning('Failed login attempt', ['username' => 'admin']);
$apiLogger->info('API request', ['endpoint' => '/users', 'method' => 'GET']);
```

## DI コンテナ統合

`wppack/dependency-injection` と組み合わせて、WpPack Logger からの差し替えが可能です：

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use Monolog\Handler\ErrorLogHandler as MonologErrorLogHandler;
use Psr\Log\LoggerInterface;

$builder = new ContainerBuilder();

// MonologLoggerFactory を登録
$builder->register(MonologLoggerFactory::class)
    ->addArgument([new MonologErrorLogHandler()]);

// デフォルトロガー
$builder->register(LoggerInterface::class)
    ->setFactory([new Reference(MonologLoggerFactory::class), 'create'])
    ->setArgument(0, 'app');
```

## Monolog ハンドラーの例

Monolog が提供する豊富なハンドラーを活用できます：

```php
use Monolog\Handler\ErrorLogHandler as MonologErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

// PHP error_log() に出力
$factory = new MonologLoggerFactory(
    defaultHandlers: [new MonologErrorLogHandler()],
);

// ファイルに出力
$factory = new MonologLoggerFactory(
    defaultHandlers: [new StreamHandler('/path/to/app.log')],
);

// 日次ローテーション
$factory = new MonologLoggerFactory(
    defaultHandlers: [new RotatingFileHandler('/path/to/app.log', 14)],
);

// 複数ハンドラーの組み合わせ
$factory = new MonologLoggerFactory(
    defaultHandlers: [
        new MonologErrorLogHandler(level: Level::Warning),
        new StreamHandler('/path/to/app.log', Level::Debug),
    ],
);
```

## WpPack Logger との比較

| 機能 | WpPack Logger | Monolog Logger |
|------|--------------|----------------|
| PSR-3 準拠 | Yes | Yes |
| ファクトリ | `LoggerFactory` | `MonologLoggerFactory` |
| ハンドラー | `ErrorLogHandler` | Monolog の全ハンドラー |
| プロセッサ | — | Monolog の全プロセッサ |
| フォーマッター | 固定フォーマット | カスタマイズ可能 |
| 依存関係 | `psr/log` のみ | `monolog/monolog` |

WpPack Logger は軽量でシンプル。Monolog Logger は高度なログルーティング・フォーマッティングが必要な場合に適しています。

## 主要クラス

| クラス | 説明 |
|-------|------|
| `MonologLoggerFactory` | Monolog ロガーインスタンスの生成ファクトリ |
