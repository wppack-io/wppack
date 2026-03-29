# Logger Component

**Package:** `wppack/logger`
**Namespace:** `WpPack\Component\Logger\`
**Layer:** Infrastructure

WordPress の `error_log()` / デバッグログを PSR-3 準拠のインターフェースでラップするコンポーネントです。`WP_DEBUG_LOG` 設定と連携し、構造化されたコンテキスト付きのロギング、チャンネルベースのログ分離、WordPress 統合を提供します。

## インストール

```bash
composer require wppack/logger
```

## 基本コンセプト

### Before（従来の WordPress）

```php
error_log('User login failed for: ' . $username);
error_log('API request failed: ' . print_r($response, true));

if (WP_DEBUG_LOG) {
    error_log('[' . date('Y-m-d H:i:s') . '] Payment processed: $' . $amount);
}
```

### After（WpPack）

```php
use Psr\Log\LoggerInterface;

final class PaymentService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function processPayment(array $data): bool
    {
        $this->logger->info('Processing payment', [
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'customer_id' => $data['customer_id'],
        ]);

        try {
            $result = $this->gateway->charge($data);

            $this->logger->info('Payment successful', [
                'transaction_id' => $result->id,
                'amount' => $data['amount'],
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Payment failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'amount' => $data['amount'],
            ]);

            return false;
        }
    }
}
```

## PSR-3 ログレベル

RFC 5424 の重大度レベルに準拠しています：

```php
use WpPack\Component\Logger\Logger;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$logger = new Logger('app');
$logger->pushHandler(new ErrorLogHandler());

$logger->emergency('System is unusable');
$logger->alert('Action must be taken immediately');
$logger->critical('Critical conditions');
$logger->error('Runtime errors');
$logger->warning('Exceptional occurrences that are not errors');
$logger->notice('Normal but significant events');
$logger->info('Interesting events');
$logger->debug('Detailed debug information');

// コンテキスト付きログ
$logger->info('User logged in', [
    'user_id' => $user->ID,
    'ip' => $_SERVER['REMOTE_ADDR'],
]);

// プレースホルダー付きログ
$logger->info('User {username} logged in from {ip}', [
    'username' => $user->user_login,
    'ip' => $_SERVER['REMOTE_ADDR'],
]);
```

## チャンネルベースロギング

ログを異なるチャンネルに整理できます。各チャンネルは WordPress のデバッグログ（`error_log()`）に出力されますが、チャンネル名がプレフィックスとして付与されるため、ログの分類・フィルタリングが容易になります：

```php
use WpPack\Component\Logger\LoggerFactory;

final class LoggingService
{
    public function __construct(
        private readonly LoggerFactory $factory,
    ) {}

    public function createLoggers(): array
    {
        return [
            'app' => $this->factory->create('app'),
            'security' => $this->factory->create('security'),
            'api' => $this->factory->create('api'),
            'performance' => $this->factory->create('performance'),
        ];
    }
}

// 異なるチャンネルの使用
$loggers['security']->warning('Failed login attempt', [
    'username' => $username,
    'ip' => $_SERVER['REMOTE_ADDR'],
]);
// 出力例: [security.WARNING] Failed login attempt {"username":"admin","ip":"192.168.1.1"}

$loggers['api']->info('API request', [
    'endpoint' => $endpoint,
    'method' => $_SERVER['REQUEST_METHOD'],
    'duration' => $duration,
]);

$loggers['performance']->debug('Slow query detected', [
    'query' => $sql,
    'duration' => $executionTime,
]);
```

## ErrorLog ハンドラー

WordPress の `error_log()` / `WP_DEBUG_LOG` に出力するハンドラーです：

```php
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$handler = new ErrorLogHandler(level: 'warning');
$logger->pushHandler($handler);
```

### 最小ログレベルの設定

ログレベルを指定して、不要なログの出力を抑制できます：

```php
use WpPack\Component\Logger\Logger;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$logger = new Logger('app');

// warning 以上のみ error_log() に出力
$logger->pushHandler(new ErrorLogHandler(level: 'warning'));

$logger->debug('This will not be logged');   // 出力されない
$logger->info('This will not be logged');    // 出力されない
$logger->warning('This will be logged');     // 出力される
$logger->error('This will be logged');       // 出力される
```

## WordPress 統合

### WP_DEBUG 設定との連携

`WP_DEBUG` および `WP_DEBUG_LOG` の設定に応じて、自動的にログ出力先と最小レベルを決定します：

```php
use WpPack\Component\Logger\Logger;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

final class WordPressLogger extends Logger
{
    public function __construct(string $name = 'wordpress')
    {
        parent::__construct($name);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // デバッグモード有効時は全レベルを出力
            $this->pushHandler(new ErrorLogHandler(level: 'debug'));
        } else {
            // 本番環境では warning 以上のみ
            $this->pushHandler(new ErrorLogHandler(level: 'warning'));
        }
    }
}
```

### 環境別の設定

```php
use WpPack\Component\Logger\Logger;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

final class EnvironmentAwareLogger extends Logger
{
    public function __construct(string $name = 'app')
    {
        parent::__construct($name);

        match (wp_get_environment_type()) {
            'local', 'development' => $this->pushHandler(
                new ErrorLogHandler(level: 'debug')
            ),
            'staging' => $this->pushHandler(
                new ErrorLogHandler(level: 'info')
            ),
            'production' => $this->pushHandler(
                new ErrorLogHandler(level: 'warning')
            ),
        };
    }
}
```

## コンテキスト付きロギング

ログエントリに永続的なコンテキストを追加します：

```php
use WpPack\Component\Logger\Context\LoggerContext;

final class OrderService
{
    public function processOrder(Order $order): void
    {
        $context = new LoggerContext([
            'order_id' => $order->id,
            'customer_id' => $order->customerId,
            'order_total' => $order->total,
        ]);

        $this->logger->withContext($context);

        // 以降のすべてのログにオーダーコンテキストが含まれる
        $this->logger->info('Starting order processing');
        $this->validateOrder($order);
        $this->chargePayment($order);
        $this->logger->info('Order processing completed');
    }
}
```

## 条件付きロギング

```php
// 環境ベースのログレベル選択
if (wp_get_environment_type() === 'production') {
    $logger->pushHandler(new ErrorLogHandler(level: 'error'));
} else {
    $logger->pushHandler(new ErrorLogHandler(level: 'debug'));
}
```

## テスト

```php
use PHPUnit\Framework\TestCase;
use WpPack\Component\Logger\Logger;
use WpPack\Component\Logger\Test\TestHandler;

class PaymentServiceTest extends TestCase
{
    private TestHandler $testHandler;
    private PaymentService $service;

    protected function setUp(): void
    {
        $logger = new Logger('test');
        $this->testHandler = new TestHandler();
        $logger->pushHandler($this->testHandler);

        $this->service = new PaymentService($logger);
    }

    public function testLogsPaymentSuccess(): void
    {
        $this->service->processPayment([
            'amount' => 100,
            'currency' => 'USD',
        ]);

        $this->assertTrue($this->testHandler->hasInfo('Payment successful'));
        $this->assertTrue($this->testHandler->hasInfoThatContains(
            'Payment successful',
            ['amount' => 100],
        ));
    }

    public function testLogsPaymentError(): void
    {
        $this->service->processPayment([
            'amount' => -1,
            'currency' => 'USD',
        ]);

        $this->assertTrue($this->testHandler->hasError('Payment failed'));
    }
}
```

## DI コンテナ統合

`wppack/dependency-injection` と組み合わせて、チャンネルベースのロガー注入を自動化できます。

### 基本設定

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Logger\LoggerFactory;
use WpPack\Component\Logger\Handler\ErrorLogHandler;
use WpPack\Component\Logger\DependencyInjection\RegisterLoggerPass;
use Psr\Log\LoggerInterface;

$builder = new ContainerBuilder();

// LoggerFactory をデフォルトハンドラー付きで登録
$builder->register(LoggerFactory::class)
    ->addArgument([new ErrorLogHandler()]);

// デフォルトロガー（channel: 'app'）
$builder->register(LoggerInterface::class)
    ->setFactory([new Reference(LoggerFactory::class), 'create'])
    ->setArgument(0, 'app');

// #[LoggerChannel] アトリビュートの自動解決
$builder->addCompilerPass(new RegisterLoggerPass());
```

### チャンネル注入

`#[LoggerChannel]` アトリビュートをコンストラクタの `LoggerInterface` パラメータに付与すると、`RegisterLoggerPass` が自動的にチャンネル別ロガーを注入します：

```php
use Psr\Log\LoggerInterface;
use WpPack\Component\Logger\Attribute\LoggerChannel;

final class PaymentService
{
    public function __construct(
        #[LoggerChannel('payment')]
        private readonly LoggerInterface $logger,
    ) {}
}

final class SecurityService
{
    public function __construct(
        #[LoggerChannel('security')]
        private readonly LoggerInterface $logger,
    ) {}
}

// PaymentService には channel='payment' のロガーが注入される
// SecurityService には channel='security' のロガーが注入される
```

### Monolog への差し替え

`wppack/monolog-logger` パッケージの `MonologHandler`（WpPack `HandlerInterface` 実装）を `LoggerFactory` のハンドラーとして追加することで、バックエンドだけ Monolog に差し替えられます。WpPack の `LoggerFactory` / `Logger` はそのまま維持されるため、`#[LoggerChannel]` 等の DI 統合も引き続き利用可能です：

```bash
composer require wppack/monolog-logger
```

```php
use WpPack\Component\Logger\Bridge\Monolog\MonologHandler;
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use WpPack\Component\Logger\LoggerFactory;
use Monolog\Handler\StreamHandler;

$monologFactory = new MonologLoggerFactory(
    defaultHandlers: [new StreamHandler('/path/to/app.log')],
);

$handler = new MonologHandler($monologFactory);
$loggerFactory = new LoggerFactory(defaultHandlers: [$handler]);

$logger = $loggerFactory->create('app');
$logger->info('Monolog is working');
```

#### DI コンテナとの統合

`LoggerServiceProvider` と `MonologLoggerServiceProvider` を併用します。`MonologLoggerServiceProvider` が `LoggerFactory` のハンドラーを `MonologHandler` に差し替えます：

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WpPack\Component\Logger\Bridge\Monolog\DependencyInjection\MonologLoggerServiceProvider;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler as MonologErrorLogHandler;
use Monolog\Level;

$builder = new ContainerBuilder();

// WpPack Logger を先に登録
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

// WpPack Logger が返る（内部で MonologHandler → Monolog に委譲）
$logger = $container->get(\Psr\Log\LoggerInterface::class);
$logger->info('Hello World');
```

## PHP エラーキャプチャ（ErrorHandler）

`ErrorHandler` は PHP の `set_error_handler()` を使って警告・非推奨・通知などを PSR-3 ログに変換します。

```php
use WpPack\Component\Logger\ErrorHandler;
use WpPack\Component\Logger\LoggerFactory;
use WpPack\Component\Logger\ChannelResolver\DefaultChannelResolver;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$factory = new LoggerFactory([new ErrorLogHandler()]);
$resolver = new DefaultChannelResolver();

$errorHandler = new ErrorHandler($factory, $resolver, captureAllErrors: true);
$errorHandler->register();
// 以降の PHP エラーは Logger 経由で処理される（PHP 標準ハンドラーは停止）
// E_WARNING      → warning
// E_NOTICE       → notice
// E_DEPRECATED   → notice + context['_type' => 'deprecation']
// E_RECOVERABLE_ERROR → error
```

### 特徴

- **`captureAllErrors` モード** — `true`（デフォルト）で全 PHP エラーをキャプチャ。`error_reporting()` を無視し、`@` 抑制も含めて Logger に送る。Monolog の `handleOnlyReportedErrors: false` と同等
- **PHP 標準ハンドラーの停止** — `return true` で PHP 組み込みのエラー処理を停止。二重出力を防ぎ、Logger が唯一のロギングパイプラインとして動作
- **再入防止** — `$handling` フラグでログ出力中のエラーが無限ループを起こさない
- **前のハンドラーチェーン** — `register()` 前に設定されていたハンドラーも呼び出す
- **冪等性** — `register()` / `restore()` は複数回呼んでも安全

### errno → PSR-3 マッピング

| PHP エラー定数 | PSR-3 レベル | `_type` コンテキスト |
|---------------|-------------|---------------------|
| `E_DEPRECATED`, `E_USER_DEPRECATED` | `notice` | `deprecation` |
| `E_NOTICE`, `E_USER_NOTICE` | `notice` | — |
| `E_WARNING`, `E_USER_WARNING` | `warning` | — |
| `E_RECOVERABLE_ERROR` | `error` | — |

### コンテキスト

ErrorHandler が生成するログエントリには以下のコンテキストが含まれます:

| キー | 説明 |
|------|------|
| `_file` | エラー発生ファイルパス |
| `_line` | エラー発生行番号 |
| `_error_type` | PHP エラー定数名（`E_WARNING` 等） |
| `_type` | `deprecation`（非推奨エラーのみ） |

## WordPress でのログ収集

### WordPress の PHP エラー設定の仕組み

WordPress core（`wp-settings.php`）は `WP_DEBUG` の値に応じて PHP のエラー関連設定を変更します:

```php
if ( WP_DEBUG ) {
    error_reporting( E_ALL );
    if ( WP_DEBUG_DISPLAY )
        ini_set( 'display_errors', 1 );
    elseif ( null !== WP_DEBUG_DISPLAY )
        ini_set( 'display_errors', 0 );
    if ( WP_DEBUG_LOG ) {
        ini_set( 'log_errors', 1 );
        ini_set( 'error_log', is_string( WP_DEBUG_LOG )
            ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log' );
    }
} else {
    error_reporting( E_ALL & ~E_DEPRECATED & ~E_STRICT );
}
```

#### 設定の依存関係

| 定数 | デフォルト | WP_DEBUG=false 時 | WP_DEBUG=true 時 |
|------|----------|------------------|-----------------|
| `error_reporting` | — | `E_ALL & ~E_DEPRECATED & ~E_STRICT` | `E_ALL` |
| `WP_DEBUG_LOG` | `false` | **無効**（if ブロック外） | `true`: `debug.log`、文字列: 指定パス |
| `WP_DEBUG_DISPLAY` | `true` | **無効**（if ブロック外） | `display_errors` を制御 |

ポイント:

- `WP_DEBUG_LOG` と `WP_DEBUG_DISPLAY` は `WP_DEBUG=true` の **if ブロック内** でのみ処理される
- `WP_DEBUG=false` 時はこれらの定数を設定しても PHP の動作は変わらない
- `display_errors` は WordPress が `WP_DEBUG=true` 時のみ設定。`WP_DEBUG=false` 時は php.ini のデフォルト

#### `error_log()` の出力先決定

| 優先順位 | 条件 | 出力先 |
|---------|------|--------|
| 1 | `WP_DEBUG=true` + `WP_DEBUG_LOG` に文字列パス | 指定パス |
| 2 | `WP_DEBUG=true` + `WP_DEBUG_LOG=true` | `wp-content/debug.log` |
| 3 | php.ini の `error_log` ディレクティブ | 指定パス |
| 4 | いずれも未設定 | SAPI デフォルト（Apache error log, syslog 等） |

### モダンアプローチ: 全エラーを Logger へ、記録は Handler が決定

WordPress の設定（`WP_DEBUG` / `error_reporting`）は PHP 組み込みのエラー表示・記録を制御します。Logger の `ErrorHandler` は `return true` で PHP 標準ハンドラーを停止し、Logger が唯一のロギングパイプラインとして動作します:

```
PHP Error
  └─→ ErrorHandler (return true → PHP 標準停止)
       └─→ Logger → Handler chain
            ├── ErrorLogHandler(level: 'warning')  → error_log()
            ├── MonologHandler(level: 'debug')     → ファイル/Slack/CloudWatch
            └── DebugHandler                       → ツールバー
```

従来の WordPress の仕組み（PHP 標準ハンドラー）との比較:

```
PHP Error
  ├── PHP 標準: error_reporting() でフィルタ → error_log() / display_errors
  │   └── WP_DEBUG=false → E_DEPRECATED は記録されない
  │   └── フィルタリングは error_reporting 単位（環境別の出力先制御は不可）
  │
  └── Logger ErrorHandler: 全エラーをキャプチャ → Handler chain
      └── 各 Handler が isHandling(level) で独立に判断
      └── production は warning+ のみ error_log、development は全レベル記録
      └── 複数の出力先を Handler で独立制御
```

### ErrorHandler の `captureAllErrors` パラメータ

| パラメータ | デフォルト | 説明 |
|-----------|----------|------|
| `captureAllErrors` | `true` | 全 PHP エラーをキャプチャ（`error_reporting()` を無視） |

**`captureAllErrors: true`（デフォルト・推奨）:**

- 全 PHP エラーが Logger に到達する（`WP_DEBUG` の設定に依存しない）
- `@` 抑制演算子も無視される（PHP 8.0+ ではハンドラー内で `@` と global `error_reporting()` を区別できないため。Monolog の `handleOnlyReportedErrors: false` と同じ設計）
- 何を記録するかは Handler の `level` パラメータで制御

**`captureAllErrors: false`:**

- `error_reporting()` マスクを尊重する従来動作
- `return true` のため、`error_reporting()` で除外されたエラーは Logger にも PHP にも記録されず消失する点に注意

### `error_log` — 関数と INI 設定の区別

`error_log` には PHP 関数と INI 設定の2つがあり、混同しやすいため整理します:

| | `error_log()` 関数 | `error_log` INI 設定 |
|---|---|---|
| 役割 | メッセージをログに書き込む | `error_log()` 関数の出力先を指定 |
| 設定場所 | — | php.ini / `ini_set()` |
| デフォルト | — | SAPI デフォルト（Apache error log, syslog 等） |

```
error_log('Something happened')  ← 関数呼び出し
    ↓
error_log INI 設定を参照          ← どこに書くか
    ↓
/var/log/php-errors.log に出力    ← 実際の書き込み先
```

WordPress は `WP_DEBUG_LOG=true` 時に `ini_set('error_log', WP_CONTENT_DIR . '/debug.log')` を実行して INI 設定を変更します。これにより `error_log()` 関数の出力先が `debug.log` になります。

ErrorLogHandler は `error_log()` **関数** を呼びます。出力先は `error_log` **INI 設定** に従います:

```php
// ErrorLogHandler::handle()
error_log(sprintf('[%s.%s] %s', $channel, strtoupper($level), $message));
// → error_log INI 設定で指定されたファイルに出力
```

### `wp_trigger_error()` — WordPress 6.4+ のエラー発生関数

```php
wp_trigger_error(
    string $function_name,
    string $message,
    int $error_level = E_USER_NOTICE
): void
```

`trigger_error()` の WordPress ラッパーです。`WP_DEBUG=true` の場合のみ `trigger_error()` を呼びます:

| | `trigger_error()` | `wp_trigger_error()` |
|---|---|---|
| `WP_DEBUG=false` 時 | エラー発生する | **何もしない** |
| `WP_DEBUG=true` 時 | エラー発生する | `trigger_error()` を呼ぶ |
| エラーレベル | 任意 | `E_USER_*` のみ |

**重要**: `wp_trigger_error()` は `WP_DEBUG=false` 時に `trigger_error()` 自体を呼ばないため、ErrorHandler の `captureAllErrors: true` でもキャプチャできません。これは `error_reporting()` のフィルタリングとは異なる問題です:

| エラー種別 | WP_DEBUG=false + captureAllErrors: true |
|-----------|---------------------------------------|
| PHP ネイティブ `E_DEPRECATED` | **キャプチャされる**（PHP が発生 → ErrorHandler が捕捉） |
| `trigger_error('...', E_USER_DEPRECATED)` | **キャプチャされる**（PHP が発生 → ErrorHandler が捕捉） |
| `wp_trigger_error('...', '...', E_USER_DEPRECATED)` | **キャプチャされない**（`trigger_error()` 自体が呼ばれない） |
| WordPress `_deprecated_function()` 等 | `wp_trigger_error()` 経由 → **キャプチャされない** |

WordPress の非推奨警告（`_deprecated_function()` 等）は `wp_trigger_error()` 経由で発生するため、`WP_DEBUG=false` の production 環境では ErrorHandler ではキャプチャできません。代わりに Debug コンポーネントの `LoggerDataCollector` が WordPress の deprecation hooks（`deprecated_function_run` 等）を直接リッスンしてキャプチャします。

### 自動キャプチャ vs 手動ログ

| 方式 | 対象 | WP_DEBUG=false 時 | チャンネル |
|------|------|------------------|----------|
| ErrorHandler（自動） | PHP ネイティブエラー: `E_WARNING`, `E_DEPRECATED` 等 | `captureAllErrors: true` でキャプチャ可 | `WordPressChannelResolver` で自動解決 |
| ErrorHandler（自動） | `trigger_error()` によるユーザーエラー | `captureAllErrors: true` でキャプチャ可 | 同上 |
| ErrorHandler（自動） | `wp_trigger_error()` によるエラー | **キャプチャ不可**（関数が発火しない） | — |
| LoggerDataCollector（自動） | WordPress deprecation hooks（`deprecated_function_run` 等） | **キャプチャ可**（hooks は WP_DEBUG 不問で発火） | `wordpress` |
| 手動ログ | アプリケーションイベント | 常に可 | `$logger->info(...)` で任意 |

### 環境別推奨設定

```php
// === Development ===
new LoggerServiceProvider(
    level: 'debug',              // ErrorLogHandler: 全レベルを error_log()
    captureAllErrors: true,      // 全 PHP エラーをキャプチャ
);
// wp-config.php: WP_DEBUG=true, WP_DEBUG_LOG=true, WP_DEBUG_DISPLAY=false

// === Staging ===
new LoggerServiceProvider(
    level: 'info',               // ErrorLogHandler: info 以上を error_log()
    captureAllErrors: true,      // 全 PHP エラーをキャプチャ
);

// === Production ===
new LoggerServiceProvider(
    level: 'warning',            // ErrorLogHandler: warning 以上のみ error_log()
    captureAllErrors: true,      // 全 PHP エラーをキャプチャ（Handler が notice 以下を除外）
);
// E_DEPRECATED は notice レベルで Logger に到達するが、
// ErrorLogHandler(level: 'warning') がフィルタ → error_log には出力されない
// Monolog 連携時は StreamHandler(level: 'debug') で全レベルをファイルに記録可能
```

### WordPress 設定 vs Logger 設定の対照表

| 目的 | WordPress 設定 | WpPack Logger |
|------|---------------|---------------|
| エラー画面表示 | `WP_DEBUG_DISPLAY` (`WP_DEBUG=true` 時のみ有効) | — (Logger は表示しない) |
| PHP 組み込みファイルログ | `WP_DEBUG_LOG` (`WP_DEBUG=true` 時のみ有効) | — |
| Logger のファイルログ | — | `ErrorLogHandler` / `MonologHandler` |
| 記録レベル制御 | `error_reporting()` (エラー種別の表示制御) | Handler の `level` パラメータ |
| E_DEPRECATED の記録 | `WP_DEBUG=true` 必須 | `captureAllErrors: true` で WP_DEBUG 不問 |
| production ログ | `WP_DEBUG=false` → PHP 組み込みは warning+ のみ | `ErrorLogHandler(level: 'warning')` で明示制御 |

### Logger なしの WordPress と Logger ありの比較

**Logger なし（`WP_DEBUG` のみ）:**

- `WP_DEBUG=false` → `E_DEPRECATED` は記録されない、error/warning は php.ini 次第
- `WP_DEBUG=true` + `WP_DEBUG_LOG=true` → 全レベルが `debug.log` に出力（フィルタリング不可）
- テキスト形式の非構造化出力

**Logger あり:**

- `captureAllErrors: true` → `WP_DEBUG` に関係なく全 PHP エラーが Logger に到達
- Handler の `level` で環境ごとに記録レベルを制御
- チャンネルベースの分類（`plugin:akismet`, `theme:mytheme` 等）
- 構造化コンテキスト（JSON）
- 複数の出力先を Handler で独立制御（error_log, ファイル, Slack, CloudWatch 等）
- Debug ツールバーとの統合

## チャンネル自動解決（ChannelResolver）

`ChannelResolverInterface` は、ファイルパスからログチャンネル名を解決するインターフェースです。

```php
namespace WpPack\Component\Logger\ChannelResolver;

interface ChannelResolverInterface
{
    public function resolve(string $filePath): string;
}
```

### DefaultChannelResolver

Logger コンポーネントに含まれるデフォルト実装。常に固定のチャンネル名（デフォルト: `php`）を返します。

```php
use WpPack\Component\Logger\ChannelResolver\DefaultChannelResolver;

$resolver = new DefaultChannelResolver();
$resolver->resolve('/any/path');  // 'php'

$resolver = new DefaultChannelResolver('custom');
$resolver->resolve('/any/path');  // 'custom'
```

### WordPressChannelResolver

WordPress 環境対応の実装。エラー発生元のファイルパスからプラグイン/テーマ名を自動解決します。WordPress 定数が未定義の環境でも安全に動作し、フォールバック値 `php` を返します。

```php
use WpPack\Component\Logger\ChannelResolver\WordPressChannelResolver;

$resolver = new WordPressChannelResolver();
$resolver->resolve(WP_PLUGIN_DIR . '/akismet/akismet.php');  // 'plugin:akismet'
```

| パス | 解決結果 |
|------|---------|
| `WP_PLUGIN_DIR/akismet/...` | `plugin:akismet` |
| `WPMU_PLUGIN_DIR/foo/...` | `plugin:foo` |
| `ABSPATH/wp-content/themes/twentytwentyfour/...` | `theme:twentytwentyfour` |
| `ABSPATH/wp-includes/...` or `wp-admin/...` | `wordpress` |
| その他 | `php` |

`LoggerServiceProvider` はデフォルトで `WordPressChannelResolver` を `ChannelResolverInterface` として登録します。

## error_log() キャプチャ（ErrorLogInterceptor）

WordPress コアやサードパーティプラグインが `error_log()` を直接呼び出す場合、Logger パイプラインを経由しないため通常は Debug ツールバーに表示されません。`ErrorLogInterceptor` はこの問題を解決します。

### 仕組み

1. `register()` — リクエスト開始時に仮ファイルを作成し、`ini_set('error_log', $tempFile)` で PHP の出力先を差し替え
2. リクエスト処理中 — すべての `error_log()` 呼び出しが仮ファイルに書き込まれる
3. `collect()` — 仮ファイルを読み取り、エントリをパースしてリスナーに通知
4. `restore()` — 元の `error_log` 設定に復元し、仮ファイルを削除

リクエストごとに独立した仮ファイルを使用するため、同時リクエスト間でのログ混在が発生しません。

### シングルトンパターン

`ErrorLogInterceptor` はシングルトンとして設計されています。drop-in（early boot）と DI（late boot）の両方から同一インスタンスにアクセスするためです:

```php
use WpPack\Component\Logger\ErrorLogInterceptor;

$interceptor = ErrorLogInterceptor::create(); // singleton を取得または作成
$interceptor->register();

// リスナーの追加
$interceptor->addListener(function (string $level, string $message): void {
    // $level: 'debug', 'notice', 'warning', 'critical'
});

$interceptor->collect(); // エントリを読み取り、リスナーに通知
```

### ログレベルの自動判定

PHP エラーフォーマット（`[timestamp] PHP Warning: ...`）を自動的にパースし、適切なログレベルに変換します:

| PHP エラー種別 | ログレベル |
|---------------|----------|
| `Fatal error`, `Parse error` | `critical` |
| `Warning` | `warning` |
| `Notice`, `Deprecated`, `Strict Standards` | `notice` |
| その他（タイムスタンプ付きメッセージ） | `debug` |
| タイムスタンプなし | `debug` |

マルチラインエントリ（スタックトレース含む）も正しくパースされます。

### WP_DEBUG_LOG との関係

WordPress は `WP_DEBUG_LOG = true` の場合、`wp_debug_mode()` で `ini_set('error_log', 'wp-content/debug.log')` を実行します。これは `ErrorLogInterceptor` の `ini_set` を上書きするため、**`WP_DEBUG_LOG = false` を推奨**します:

```php
// wp-config.php（推奨設定）
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', false);    // ErrorLogInterceptor が error_log を管理
define('WP_DEBUG_DISPLAY', false);
```

`WP_DEBUG_LOG = false` でも `ErrorLogHandler` が `error_log()` 関数経由で PHP の error_log INI 設定先に出力するため、Logger パイプラインのログは失われません。

### drop-in との連携

Debug コンポーネントの `fatal-error-handler.php` drop-in は、WordPress のブートプロセスの最初期に `ErrorLogInterceptor` を登録します。さらに `muplugins_loaded` フックで再登録し、`wp_debug_mode()` による `ini_set` の上書きに対応します:

```
fatal-error-handler.php → ErrorLogInterceptor::register()
  → wp_debug_mode() が ini_set('error_log') を上書き（WP_DEBUG_LOG=true の場合）
    → muplugins_loaded → ErrorLogInterceptor::register()（再適用）
      → 通常プラグイン読み込み → error_log() は仮ファイルへ
```

drop-in が未インストールの場合は、`DebugServiceProvider` が DI ブート時（`init` フック）にフォールバック登録します。

## LoggerFactory::pushHandler()

`LoggerFactory` にハンドラーを後から追加できます。既に `create()` 済みのロガーインスタンスにも自動的に push されます:

```php
$factory->pushHandler(new CustomHandler());
// 既存の全ロガー + 今後作成されるロガーに CustomHandler が追加される
```

Debug コンポーネントはこのメソッドを使って `DebugHandler` を DI 経由で注入します。

## Debug コンポーネント統合

Logger と Debug の両方がインストールされている場合、3つのソースからログがツールバーに集約されます:

1. **アプリケーションコード**（`$logger->info(...)`）→ `DebugHandler` → `LoggerDataCollector` → ツールバー
2. **PHP エラー**（`E_WARNING` 等）→ `ErrorHandler` → Logger → `DebugHandler` → ツールバー
3. **`error_log()` 呼び出し** → 仮ファイル → `ErrorLogInterceptor::collect()` → `LoggerDataCollector` → ツールバー
4. **WordPress deprecation** フック → `LoggerDataCollector` が直接キャプチャ → ツールバー

```
$logger->info()     → Logger::log() → DebugHandler → LoggerDataCollector
PHP Error           → ErrorHandler  → Logger::log() → DebugHandler → LoggerDataCollector
error_log('...')    → 仮ファイル    → ErrorLogInterceptor::collect() → LoggerDataCollector
WP deprecation hook →                                                  LoggerDataCollector
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Logger` | PSR-3 準拠ロガー |
| `LoggerFactory` | 名前付きロガーインスタンスの生成 |
| `ErrorHandler` | PHP エラー → PSR-3 ログ変換 |
| `ChannelResolver\ChannelResolverInterface` | ファイルパス → チャンネル名解決 |
| `ChannelResolver\DefaultChannelResolver` | 固定チャンネルリゾルバー |
| `ChannelResolver\WordPressChannelResolver` | WordPress パス解決リゾルバー |
| `Handler\HandlerInterface` | カスタムハンドラー用インターフェース |
| `Handler\ErrorLogHandler` | PHP `error_log()` ハンドラー |
| `Context\LoggerContext` | 永続的なロギングコンテキスト |
| `ErrorLogInterceptor` | `error_log()` 出力キャプチャ（仮ファイル方式、シングルトン） |
| `Test\TestHandler` | テスト用ハンドラー |
| `Attribute\LoggerChannel` | DI チャンネル指定アトリビュート |
| `DependencyInjection\RegisterLoggerPass` | チャンネルロガー自動登録コンパイラーパス |
