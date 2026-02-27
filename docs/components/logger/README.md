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

### 従来の WordPress と WpPack の比較

```php
// 従来の WordPress - 非構造化な error_log
error_log('User login failed for: ' . $username);
error_log('API request failed: ' . print_r($response, true));

if (WP_DEBUG_LOG) {
    error_log('[' . date('Y-m-d H:i:s') . '] Payment processed: $' . $amount);
}

// WpPack Logger - PSR-3 準拠、構造化ロギング
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

$logger = new Logger('app');

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

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Logger` | PSR-3 準拠ロガー |
| `LoggerFactory` | 名前付きロガーインスタンスの生成 |
| `Handler\ErrorLogHandler` | PHP `error_log()` ハンドラー |
| `Context\LoggerContext` | 永続的なロギングコンテキスト |
| `Test\TestHandler` | テスト用ハンドラー |
