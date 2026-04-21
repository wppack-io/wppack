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
| `wp-phpunit/wp-phpunit` | `>=6.7 <8.0` | WordPress テストブートストラップ |
| `yoast/phpunit-polyfills` | `^4.0` | PHPUnit バージョン互換性 |
| `roots/wordpress-full` | `>=6.7 <8.0` | WordPress コアファイル（ABSPATH）。`-full` variant を使うことで beta/RC タグ（7.0-RC2 など）の解決が可能 |

### データベース・サービス設定

`tests/wp-config.php` はリポジトリに直接コミットされています（`.dist` + コピー運用ではありません）。

```php
// tests/wp-config.php（抜粋）
define('ABSPATH', dirname(__DIR__) . '/web/wp/');
define('DB_NAME', 'wppack_test');
define('DB_USER', 'root');
define('DB_PASSWORD', 'password');
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

## カバレッジ方針

### 目標値

- **全体**: 85% 以上を維持（現在 約 86%）
- **新規コード**: 触れたファイル単位で 90% 以上
- **Component 層のコア**: インターフェース / サービス / ユースケースは 95% 以上
- **Bridge 層**: エンジン固有実装。unit で 80%、integration で互換性を担保

Codecov には per-component のフラグで coverage が記録されます
（`codecov.yml` の `individual_components` 参照）。

### 意図的に低カバレッジな領域

以下は**実行時環境／外部サービスに依存するため unit テストの価値が
低い**領域で、意図的にカバレッジを低く保っています。代替として
integration / smoke / 手動検証でカバーします。

| 領域 | 典型的な未カバー比率 | 理由と代替手段 |
|------|-------------------|--------------|
| **AWS Bridge のライブコール** (`S3StorageAdapter`, `CloudWatchMetricProvider`, `DataApiDriver`, `SesApiTransport`) | 60-80% | async-aws クライアントの HTTP レスポンスは mock できるが、SigV4 署名・リージョン解決・retry backoff の実装詳細は mock と実挙動の乖離が大きい。AWS SDK 側のテストを信用し、WPPack 側は「呼び出しパラメータの構築」「エラー分類」「retry loop」など mockable な境界のみカバー |
| **WebAuthn crypto flow** (`Security/Bridge/Passkey/Ceremony/*`) | 50-70% | WebAuthn の ECDSA / RSA 検証は `web-auth/webauthn-lib` 側で担保。WPPack 側はユーザ解決・セッション管理・RP ID マッピング等の business logic のみテスト |
| **PHP 拡張依存アダプタ** (`ApcuAdapter`, `RelayAdapter`) | 30-50% | `ext-apcu`, `ext-relay` がホストに入っていないと `skipIfExtensionMissing()` で skip。CI では Valkey 系のみ必ず走らせる |
| **マルチサイト専用分岐** (`Site`, `Security/Bridge/SAML/Multisite`) | single-site CI は 60% 程度 | `tests/phpunit/multisite.xml` で別途走る。メインの matrix は single-site のみ |
| **`exit()` / `wp_die()` を含む handler** (`WpDieHandler`, SAML `EntryPoint`) | 例外到達テストで fully cover 不可 | 到達直前までをテスト。`@runInSeparateProcess` は `exit` を PHPUnit が捕捉できないため使わない |
| **DB Driver の低レベル I/O** (`MySQLDriver::doConnect`, `PostgreSQLDriver::gone-away 判定`) | 70-80% | `mysqli_connect` / `pg_connect` のモックは unit では困難。`WpdbIntegrationTestTrait` による各エンジン実接続の integration test でカバー |
| **Drop-in / bootstrap スクリプト** (`db.php`, `object-cache.php`, `fatal-error-handler.php`) | N/A | PHP の require 順・定数解決が `tests/bootstrap.php` とは異なるため従来型 unit test で扱えない。動作確認は smoke / 手動 |

### カバレッジ測定方法

ローカルで unit coverage を見るには Xdebug を入れて:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage
open var/coverage/index.html
```

Component 単位の生の Clover も出力可能:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit \
    src/Component/Database/tests/ \
    --coverage-clover var/coverage-database.xml
```

CI が Codecov にアップロードするのは `coverage.xml`（全体 Clover）と
`junit.xml`（test results）です。PR 上の Codecov コメントで差分ベースの
coverage change が確認できます。

### 新規テストを書く指針

- **ユースケース（public API）優先**: インターフェース実装の contract を
  テスト。private / protected は public 経由で自然にカバーされる
- **Framework の dependency は mock**: `$wpdb`, `WP_Query`,
  `WP_Filesystem_Base` などは DI で注入して mock に差し替え可能な形で
  利用する（該当 Subscriber / Repository のテストを参照）
- **Integration は sparingly**: 可能な限り unit。どうしても現実のエンジン
  挙動が必要な場合のみ `WpdbIntegrationTestTrait` / `wpdb://` DSN で本物を
  叩く
- **低カバレッジ許容の領域**: 上表の領域を触る場合は、新規関数でも unit
  カバレッジは必須ではない。代わりに統合 / smoke / 手動確認を PR で示す

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
