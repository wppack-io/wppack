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

PSR-3 準拠のため、`LoggerInterface` のタイプヒントを変更せずに Monolog に差し替えられます。`wppack/monolog-logger` パッケージをインストールし、`MonologLoggerFactory` を使用します：

```bash
composer require wppack/monolog-logger
```

```php
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use Monolog\Handler\ErrorLogHandler as MonologErrorLogHandler;
use Monolog\Processor\PsrLogMessageProcessor;

$factory = new MonologLoggerFactory(
    defaultHandlers: [new MonologErrorLogHandler()],
    defaultProcessors: [new PsrLogMessageProcessor()],
);

// LoggerFactory と同じ create() インターフェース
$logger = $factory->create('app');
$logger->info('Monolog is working');
```

#### DI コンテナとの統合

`MonologLoggerFactory` は `LoggerFactory` と同じ `create(string $name)` シグネチャを持つため、DI コンテナでそのまま差し替えられます：

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use Monolog\Handler\ErrorLogHandler as MonologErrorLogHandler;
use Psr\Log\LoggerInterface;

$builder = new ContainerBuilder();

$builder->register(MonologLoggerFactory::class)
    ->addArgument([new MonologErrorLogHandler()]);

$builder->register(LoggerInterface::class)
    ->setFactory([new Reference(MonologLoggerFactory::class), 'create'])
    ->setArgument(0, 'app');
```

Monolog を使用する場合、`#[LoggerChannel]` によるチャンネル自動注入は利用できません。チャンネル別ロガーが必要な場合は、各チャンネルを個別にサービス登録してください。

## PHP エラーキャプチャ（ErrorHandler）

`ErrorHandler` は PHP の `set_error_handler()` を使って警告・非推奨・通知などを PSR-3 ログに変換します。

```php
use WpPack\Component\Logger\ErrorHandler;
use WpPack\Component\Logger\LoggerFactory;
use WpPack\Component\Logger\ChannelResolver\DefaultChannelResolver;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

$factory = new LoggerFactory([new ErrorLogHandler()]);
$resolver = new DefaultChannelResolver();

$errorHandler = new ErrorHandler($factory, $resolver);
$errorHandler->register();

// 以降の PHP エラーは Logger 経由で処理される
// E_WARNING      → warning
// E_NOTICE       → notice
// E_DEPRECATED   → warning + context['_type' => 'deprecation']
// E_RECOVERABLE_ERROR → error
```

### 特徴

- **`@` 抑制演算子の尊重** — `error_reporting()` でフィルタ
- **再入防止** — `$handling` フラグでログ出力中のエラーが無限ループを起こさない
- **前のハンドラーチェーン** — `register()` 前に設定されていたハンドラーも呼び出す
- **冪等性** — `register()` / `restore()` は複数回呼んでも安全

### errno → PSR-3 マッピング

| PHP エラー定数 | PSR-3 レベル | `_type` コンテキスト |
|---------------|-------------|---------------------|
| `E_DEPRECATED`, `E_USER_DEPRECATED` | `warning` | `deprecation` |
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

## Debug コンポーネント統合

Logger と Debug の両方がインストールされている場合:

1. **ErrorHandler** が PHP エラーをキャプチャ → Logger 経由で `DebugHandler` → ツールバーに表示
2. **WordPress deprecation** フック → `LoggerDataCollector` が Logger 経由でログ → ツールバーに表示
3. **アプリケーションコード** の `$logger->info(...)` → 同じパイプラインでツールバーに表示

```
PHP Error → ErrorHandler → WordPressChannelResolver → LoggerFactory
                                                          ↓
                                                    Logger::log()
                                                    ↓           ↓
                                            ErrorLogHandler  DebugHandler
                                            (error_log())    (ツールバー)
```

Logger 未インストール時は `LoggerDataCollector` が従来どおり直接ログを管理します（フォールバック動作）。

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
| `Test\TestHandler` | テスト用ハンドラー |
| `Attribute\LoggerChannel` | DI チャンネル指定アトリビュート |
| `DependencyInjection\RegisterLoggerPass` | チャンネルロガー自動登録コンパイラーパス |
