# テスト

WPPack のテスト戦略と実行方法。

## 概要

WPPack は **PHPUnit 11 + wp-phpunit + MySQL** による WordPress 統合テスト環境を採用しています。`tests/bootstrap.php` は常に WordPress をフルロードするため、すべてのテストで WordPress 関数が利用可能です。

| 項目 | 内容 |
|------|------|
| テストフレームワーク | PHPUnit 11（アトリビュートベース） |
| WordPress 統合 | wp-phpunit + roots/wordpress-no-content |
| 必須サービス | MySQL 8.0 |
| オプションサービス | Valkey, DynamoDB Local, Memcached, Valkey Cluster, Valkey Sentinel |

## テスト実行

### ローカル実行

```bash
# 1. サービス起動（MySQL は必須、他はテスト対象に応じて起動）
docker compose up -d --wait

# 2. テスト実行
vendor/bin/phpunit

# 3. サービス停止
docker compose down
```

### 特定のテストスイート・ファイルのみ実行

```bash
# スイート指定
vendor/bin/phpunit --testsuite Component

# ファイル指定
vendor/bin/phpunit src/Component/HttpClient/tests/HttpClientTest.php
```

## アーキテクチャ

### ブートストラップ

`tests/bootstrap.php` は以下の処理を行います:

1. Composer オートローダーをロード
2. `WP_PHPUNIT__TESTS_CONFIG` に `tests/wp-config.php` を設定
3. wp-phpunit の `functions.php` と `bootstrap.php` をロードし、WordPress を初期化
4. テストに必要な追加の admin includes をロード

```php
// tests/bootstrap.php（抜粋）
putenv('WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-config.php');

$_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';
require_once $_tests_dir . '/includes/functions.php';
require_once $_tests_dir . '/includes/bootstrap.php';

// Admin includes（DashboardWidget, Filesystem 等のテストに必要）
$extraIncludes = [
    ABSPATH . 'wp-admin/includes/dashboard.php',
    ABSPATH . 'wp-admin/includes/template.php',
    ABSPATH . 'wp-admin/includes/screen.php',
    ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php',
    ABSPATH . WPINC . '/class-wp-admin-bar.php',
];
```

### テスト依存パッケージ

| パッケージ | バージョン | 用途 |
|-----------|-----------|------|
| `phpunit/phpunit` | `^11.5` | テストフレームワーク |
| `wp-phpunit/wp-phpunit` | `^6.9` | WordPress テストブートストラップ |
| `yoast/phpunit-polyfills` | `^4.0` | PHPUnit バージョン互換性 |
| `roots/wordpress-no-content` | `^6.9` | WordPress コアファイル（ABSPATH） |

### データベース・サービス設定

`tests/wp-config.php` はリポジトリに直接コミットされています（`.dist` + コピー運用ではありません）。

```php
// tests/wp-config.php（抜粋）
define('ABSPATH', dirname(__DIR__) . '/web/wp/');
define('DB_NAME', 'wppack_test');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1');

// WP 6.8+: wp_is_block_theme() の _doing_it_wrong notice を回避
$GLOBALS['wp_theme_directories'] = [
    __DIR__ . '/../vendor/wp-phpunit/wp-phpunit/data/themedir1',
];
```

### Docker サービス

`docker-compose.yml` でテスト用サービスを提供します。すべて `tmpfs` マウントによりメモリ上で動作します。

| サービス | イメージ | ポート | 用途 |
|---------|---------|--------|------|
| MySQL | `mysql:8.0` | 3306 | WordPress DB（必須） |
| Valkey | `valkey/valkey:8` | 6379 | RedisCache テスト |
| DynamoDB Local | `amazon/dynamodb-local` | 8000 | DynamoDbCache テスト |
| Memcached | `memcached:1.6` | 11211 | MemcachedCache テスト |
| Valkey Cluster | `valkey/valkey:8` | 7010-7012 | RedisCache Cluster モードテスト |
| Valkey Sentinel | `valkey/valkey:8` | 6380-6381, 26379-26381 | RedisCache Sentinel モードテスト |

Valkey Cluster は 3 ノード構成（`--cluster-replicas 0`）、Valkey Sentinel は master 1 + replica 1 + sentinel 3 構成です。

## テストスイート構成

`phpunit.xml.dist` で 3 つのテストスイートを定義しています:

| スイート | ディレクトリ | 対象 |
|---------|-------------|------|
| Component | `src/Component/*/tests` | コンポーネントテスト |
| Bridge | `src/Component/*/Bridge/*/tests` | ブリッジパッケージテスト |
| Plugin | `src/Plugin/*/tests` | プラグインテスト |

```xml
<testsuites>
    <testsuite name="Component">
        <directory>src/Component/*/tests</directory>
    </testsuite>
    <testsuite name="Bridge">
        <directory>src/Component/*/Bridge/*/tests</directory>
    </testsuite>
    <testsuite name="Plugin">
        <directory>src/Plugin/*/tests</directory>
    </testsuite>
</testsuites>
```

## テストの書き方

### ファイル配置

各コンポーネントのテストは `src/Component/{Name}/tests/` に配置します:

```
src/Component/HttpClient/
├── src/
│   └── HttpClient.php
└── tests/
    └── HttpClientTest.php

src/Component/Mailer/
├── src/
│   └── ...
├── tests/
│   └── MailerTest.php
└── Bridge/
    └── Azure/
        └── tests/
            └── Transport/
                └── AzureApiTransportTest.php
```

### テストクラスの基本構成

PHPUnit 11 のアトリビュート（`#[Test]`, `#[CoversClass]`）を使用し、`PHPUnit\Framework\TestCase` を拡張します。WordPress はブートストラップでフルロードされるため、`function_exists()` ガードは不要です。

```php
<?php

declare(strict_types=1);

namespace WPPack\Component\NavigationMenu\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\NavigationMenu\MenuRegistry;

#[CoversClass(MenuRegistry::class)]
final class MenuRegistryTest extends TestCase
{
    private MenuRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new MenuRegistry();
    }

    #[Test]
    public function registerLocationAddsSingleLocation(): void
    {
        $this->registry->registerLocation('sidebar', 'Sidebar Menu');

        self::assertTrue($this->registry->hasLocation('sidebar'));
        self::assertSame('Sidebar Menu', $this->registry->all()['sidebar']);
    }
}
```

### HTTP 呼び出しのモック

WordPress の `pre_http_request` フィルターを使用して HTTP 呼び出しをモックします。

```php
final class MyTransportTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $mockResponse = null;

    private ?string $capturedBody = null;

    protected function setUp(): void
    {
        parent::setUp();
        add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
        $this->mockResponse = null;
        $this->capturedBody = null;
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    public function mockHttpResponse(mixed $preempt, array $parsedArgs, string $url): array
    {
        $this->capturedBody = $parsedArgs['body'] ?? '';

        return $this->mockResponse ?? [
            'headers' => [],
            'body' => '',
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    #[Test]
    public function sendBuildsPayload(): void
    {
        $this->mockResponse = [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['id' => 'msg-123']),
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new MyTransport();
        $transport->send($phpMailer);

        $payload = json_decode($this->capturedBody, true);
        self::assertArrayHasKey('to', $payload);
    }
}
```

**重要**: `HttpClient` を匿名クラスで拡張してモックするパターンは使用しないでください。`HttpClient` は clone ベースの immutability を採用しており、`withHeaders()` や `timeout()` などのメソッドがクローンを返します。匿名クラスでオーバーライドした `post()` のキャプチャ変数はクローン先に設定されるため、元のインスタンスでは参照できません。

### モックレスポンスの形式

`pre_http_request` フィルターから返すレスポンスは WordPress の内部形式に準拠します:

```php
// 成功レスポンス
[
    'headers'  => ['content-type' => 'application/json'],
    'body'     => '{"id": "msg-123"}',
    'response' => ['code' => 200, 'message' => 'OK'],
    'cookies'  => [],
    'filename' => null,
]

// エラーレスポンス（HTTP ステータスエラー）
[
    'headers'  => [],
    'body'     => '{"error": "Bad Request"}',
    'response' => ['code' => 400, 'message' => 'Bad Request'],
    'cookies'  => [],
    'filename' => null,
]

// 接続エラー（WP_Error）
new \WP_Error('http_request_failed', 'Could not resolve host')
```

### テストユーティリティ

| ユーティリティ | 用途 |
|--------------|------|
| `EventDispatcherTestTrait` | EventDispatcher のテスト支援（リスナー登録・イベントディスパッチの検証） |

## CI

GitHub Actions（`.github/workflows/ci.yml`）で 3 つのジョブを実行します。

### ジョブ構成

| ジョブ | 内容 | PHP バージョン |
|-------|------|---------------|
| PHPStan | 静的解析 | 8.2 |
| Code Style | php-cs-fixer チェック | 8.2 |
| Tests | PHPUnit テスト + カバレッジ | 8.2 / 8.3 / 8.4 / 8.5 |

### Tests ジョブの詳細

**サービスコンテナ:**

| サービス | イメージ |
|---------|---------|
| MySQL | `mysql:8.0` |
| Valkey | `valkey/valkey:8` |
| DynamoDB Local | `amazon/dynamodb-local:latest` |
| Memcached | `memcached:1.6-alpine` |

**PHP 拡張:** redis, relay, memcached, apcu

**追加セットアップ:**
- Valkey Cluster: 3 ノードを `docker run` で個別起動し、`valkey-cli --cluster create` でクラスタ構成
- Valkey Sentinel: master + replica を起動後、3 つの Sentinel プロセスを起動し、master 検出を待機

**カバレッジ:** Xdebug でカバレッジを取得し、Codecov OIDC（`use_oidc: true`）でアップロード。テスト結果（JUnit XML）も同様にアップロード。

## ローカル環境

`docker-compose.yml` で全テスト用サービスを提供します。すべて `tmpfs` マウントによりメモリ上で動作し、高速かつ永続化不要です。

```yaml
services:
  mysql:
    image: mysql:8.0
    platform: linux/amd64
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: wppack_test
    ports:
      - '3306:3306'
    tmpfs:
      - /var/lib/mysql

  valkey:
    image: valkey/valkey:8
    ports:
      - '6379:6379'
    tmpfs:
      - /data

  dynamodb:
    image: amazon/dynamodb-local:latest
    ports:
      - '8000:8000'
    tmpfs:
      - /home/dynamodblocal/data

  valkey-cluster:
    image: valkey/valkey:8
    ports:
      - '7010:7010'
      - '7011:7011'
      - '7012:7012'
    # 3ノード Cluster（replicas 0）

  valkey-sentinel:
    image: valkey/valkey:8
    ports:
      - '6380:6380'
      - '6381:6381'
      - '26379:26379'
      - '26380:26380'
      - '26381:26381'
    # master(6380) + replica(6381) + sentinel×3

  memcached:
    image: memcached:1.6
    ports:
      - '11211:11211'
```
