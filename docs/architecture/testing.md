# テスト

WpPack のテスト戦略と実行方法。

## 概要

WpPack は **wp-phpunit** を使用した WordPress 統合テスト環境を採用しています。テストは2つのモードで動作し、`tests/wp-config.php` の有無により自動的に切り替わります。

| モード | 条件 | WordPress 関数 | スキップ |
|--------|------|----------------|----------|
| 統合テスト | `tests/wp-config.php` あり | 利用可能 | なし |
| ユニットテスト | `tests/wp-config.php` なし | 利用不可 | WordPress 依存テストをスキップ |

## テスト実行

### ユニットテスト（DB 不要）

```bash
composer test
```

WordPress に依存しないテストのみ実行されます。WordPress 関数を必要とするテストは `markTestSkipped` でスキップされます。

### 統合テスト（WordPress + MySQL）

```bash
# 1. MySQL 起動
docker compose up -d --wait

# 2. 設定ファイル配置
cp tests/wp-config.php.dist tests/wp-config.php

# 3. テスト実行（全テスト）
composer test

# 4. MySQL 停止
docker compose down
```

## アーキテクチャ

### ブートストラップ

`tests/bootstrap.php` が WordPress の有無を検出し、環境を切り替えます:

```
tests/wp-config.php あり
  → WP_PHPUNIT__TESTS_CONFIG 設定
  → wp-phpunit の bootstrap.php ロード
  → WordPress 全関数が利用可能

tests/wp-config.php なし
  → PHPMailer のみロード（roots/wordpress-no-content から）
  → WordPress 関数は利用不可
```

### テスト依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| `wp-phpunit/wp-phpunit` | WordPress テストフレームワーク |
| `yoast/phpunit-polyfills` | PHPUnit バージョン互換性 |
| `roots/wordpress-no-content` | WordPress コアファイル（ABSPATH） |

### データベース設定

`tests/wp-config.php.dist` がテンプレートです。ローカル環境では `tests/wp-config.php` にコピーして使用します。このファイルは `.gitignore` で除外されています。

CI 環境では MySQL サービスコンテナを使用し、`wp-config.php.dist` をそのままコピーして利用します。

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

### WordPress 関数の可用性ガード

WordPress に依存するテストメソッドには `function_exists` ガードを付けます:

```php
#[Test]
public function sendRequestReturnsResponse(): void
{
    if (!function_exists('wp_remote_request')) {
        self::markTestSkipped('WordPress functions are not available.');
    }

    // WordPress 関数を使うテストコード
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

        if (function_exists('add_filter')) {
            add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
        }
    }

    protected function tearDown(): void
    {
        if (function_exists('remove_filter')) {
            remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
        }
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
        // テストごとにモックレスポンスを設定
        $this->mockResponse = [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['id' => 'msg-123']),
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        // トランスポートを実行
        $transport = new MyTransport();
        $transport->send($phpMailer);

        // キャプチャしたリクエストボディを検証
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

## CI

GitHub Actions でテストを実行します。MySQL サービスコンテナが自動的に起動され、全テストが実行されます。

```yaml
tests:
  services:
    mysql:
      image: mysql:8.0
      env:
        MYSQL_ROOT_PASSWORD: root
        MYSQL_DATABASE: wppack_test
      ports:
        - 3306:3306
  steps:
    - uses: actions/checkout@v4
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
    - run: composer install --no-interaction --prefer-dist
    - run: cp tests/wp-config.php.dist tests/wp-config.php
    - run: composer test
```

## ローカル環境

`docker-compose.yml` でローカル用 MySQL を提供します。`tmpfs` マウントによりメモリ上で動作し、高速かつ永続化不要です。

```yaml
services:
  mysql:
    image: mysql:8.0
    platform: linux/amd64
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wppack_test
    ports:
      - '3306:3306'
    tmpfs:
      - /var/lib/mysql
```
