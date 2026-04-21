# MonologLogger コンポーネント

**パッケージ:** `wppack/monolog-logger`
**名前空間:** `WPPack\Component\Logger\Bridge\Monolog\`
**Category:** Substrate (Observability)

Logger コンポーネントの Monolog ブリッジ実装。WPPack Logger のフロントエンド（`LoggerFactory` + `Logger`）はそのまま維持し、`MonologHandler`（WPPack `HandlerInterface` 実装）を通じてバックエンドだけ Monolog に差し替えます。

```
Application code → LoggerFactory::create('payment') → WPPack Logger
  → WPPack HandlerInterface chain:
      ├── MonologHandler → Monolog\Logger('payment') → StreamHandler, SlackHandler...
      └── DebugHandler → LoggerDataCollector → Toolbar（変更なし）
```

## インストール

```bash
composer require wppack/monolog-logger
```

## アーキテクチャ

### ハンドラー方式

`MonologHandler` は WPPack の `HandlerInterface` を実装し、内部で `MonologLoggerFactory` に委譲します。これにより：

- WPPack の `LoggerFactory` / `Logger` がそのまま動作する（`#[LoggerChannel]` 属性による DI 解決も維持）
- `DebugHandler` など他の WPPack ハンドラーと並列に動作する
- `ErrorHandler` のチャンネル解決パイプラインが正常に機能する

### MonologHandler

WPPack `HandlerInterface` を実装し、ログレコードを Monolog に転送します。

```php
use WPPack\Component\Logger\Bridge\Monolog\MonologHandler;
use WPPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use Monolog\Handler\StreamHandler;

$factory = new MonologLoggerFactory(
    defaultHandlers: [new StreamHandler('/path/to/app.log')],
);

$handler = new MonologHandler($factory, level: 'warning');
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|---|----------|------|
| `$factory` | `MonologLoggerFactory` | — | Monolog ロガーファクトリ |
| `$level` | `string` | `'debug'` | 最小ログレベル |

動作：
- コンテキストの `_channel` からチャンネルを読み取り、対応する `Monolog\Logger` を取得
- WPPack 内部コンテキストキー（`_channel`, `_file`, `_line`, `_type`, `_error_type`）を除去して Monolog に渡す
- レベルフィルタリングにより、指定レベル以上のログのみ処理

## MonologLoggerFactory

`MonologLoggerFactory` は Monolog ロガーインスタンスを生成・キャッシュするファクトリです。`MonologHandler` が内部で利用します。

### コンストラクタ

```php
public function __construct(
    array $defaultHandlers = [],
    array $defaultProcessors = [],
)
```

| パラメータ | 型 | 説明 |
|-----------|---|------|
| `$defaultHandlers` | `HandlerInterface[]` | 生成するロガーに追加する Monolog ハンドラー |
| `$defaultProcessors` | `ProcessorInterface[]` | 生成するロガーに追加する Monolog プロセッサ |

### チャンネルベースロギング

```php
$factory = new MonologLoggerFactory(
    defaultHandlers: [new MonologErrorLogHandler()],
);

// MonologHandler を通じてチャンネルが自動的に伝播
$appLogger = $factory->create('app');
$securityLogger = $factory->create('security');
```

## DI コンテナ統合

`MonologLoggerServiceProvider` は `LoggerServiceProvider` と併用します。`LoggerFactory` のハンドラーを `MonologHandler` に差し替えます：

```php
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WPPack\Component\Logger\Bridge\Monolog\DependencyInjection\MonologLoggerServiceProvider;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler as MonologErrorLogHandler;
use Monolog\Level;

$builder = new ContainerBuilder();

// WPPack Logger を先に登録
$builder->addServiceProvider(new LoggerServiceProvider());

// Monolog ブリッジを追加（LoggerFactory のハンドラーを差し替え）
$builder->addServiceProvider(new MonologLoggerServiceProvider(
    handlers: [
        new MonologErrorLogHandler(level: Level::Warning),
        new StreamHandler('/path/to/app.log', Level::Debug),
    ],
    level: 'debug',
));

$container = $builder->compile();

// WPPack Logger が返る（内部で MonologHandler → Monolog に委譲）
$logger = $container->get(\Psr\Log\LoggerInterface::class);
$logger->info('Hello World');
```

### ServiceProvider コンストラクタ

```php
public function __construct(
    array $handlers = [],
    array $processors = [],
    string $level = 'debug',
)
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|---|----------|------|
| `$handlers` | `HandlerInterface[]` | `[]` | Monolog ハンドラー |
| `$processors` | `ProcessorInterface[]` | `[]` | Monolog プロセッサ |
| `$level` | `string` | `'debug'` | MonologHandler の最小ログレベル |

## Monolog ハンドラーの例

Monolog が提供する豊富なハンドラーを活用できます：

```php
use Monolog\Handler\ErrorLogHandler as MonologErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

// PHP error_log() に出力
$provider = new MonologLoggerServiceProvider(
    handlers: [new MonologErrorLogHandler()],
);

// ファイルに出力
$provider = new MonologLoggerServiceProvider(
    handlers: [new StreamHandler('/path/to/app.log')],
);

// 日次ローテーション
$provider = new MonologLoggerServiceProvider(
    handlers: [new RotatingFileHandler('/path/to/app.log', 14)],
);

// 複数ハンドラーの組み合わせ
$provider = new MonologLoggerServiceProvider(
    handlers: [
        new MonologErrorLogHandler(level: Level::Warning),
        new StreamHandler('/path/to/app.log', Level::Debug),
    ],
);
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `MonologHandler` | WPPack `HandlerInterface` 実装。Monolog へのブリッジ |
| `MonologLoggerFactory` | Monolog ロガーインスタンスの生成ファクトリ |
| `MonologLoggerServiceProvider` | DI コンテナ統合用サービスプロバイダ |
