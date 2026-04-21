# HttpClient コンポーネント

**パッケージ:** `wppack/http-client`
**名前空間:** `WPPack\Component\HttpClient\`
**Category:** HTTP

WordPress の HTTP API（`wp_remote_get()` / `wp_remote_post()`）を PSR-18 準拠の HTTP クライアントとしてラップするコンポーネントです。Fluent インターフェースによる型安全な API と、PSR-7 / PSR-18 互換のインターフェースを提供します。

## インストール

```bash
composer require wppack/http-client
```

## 基本コンセプト

### Before（従来の WordPress）

```php
$response = wp_remote_get('https://api.example.com/users', [
    'timeout' => 30,
    'headers' => [
        'Authorization' => 'Bearer ' . get_option('api_token'),
        'Accept' => 'application/json',
    ],
]);

if (is_wp_error($response)) {
    error_log('API request failed: ' . $response->get_error_message());
    return false;
}

$status_code = wp_remote_retrieve_response_code($response);
if ($status_code !== 200) {
    error_log('API returned status: ' . $status_code);
    return false;
}

$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);
```

### After（WPPack）

```php
use WPPack\Component\HttpClient\HttpClient;

$http = $container->get(HttpClient::class);

$response = $http
    ->withHeaders([
        'Authorization' => 'Bearer ' . get_option('api_token'),
    ])
    ->timeout(30)
    ->get('https://api.example.com/users');

if ($response->successful()) {
    $data = $response->json();
}
```

## PSR-18 準拠

HttpClient は PSR-18 (`Psr\Http\Client\ClientInterface`) を実装し、PSR-7 (`Psr\Http\Message\RequestInterface` / `ResponseInterface`) に対応します。内部的には WordPress の `wp_remote_request()` をトランスポートとして使用します。

```php
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class ExternalApiService
{
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
    ) {}

    public function fetchData(): array
    {
        $request = $this->requestFactory
            ->createRequest('GET', 'https://api.example.com/data')
            ->withHeader('Accept', 'application/json');

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('API error');
        }

        return json_decode($response->getBody()->getContents(), true);
    }
}
```

### PSR インターフェースの実装

| PSR | インターフェース | WPPack 実装 |
|-----|----------------|------------|
| PSR-7 | `RequestInterface` | `Request` |
| PSR-7 | `ResponseInterface` | `Response` |
| PSR-7 | `UriInterface` | `Uri` |
| PSR-17 | `RequestFactoryInterface` | `RequestFactory` |
| PSR-18 | `ClientInterface` | `HttpClient` |

内部トランスポートは `wp_remote_request()` を使用するため、WordPress のフィルターフック（`pre_http_request` 等）がそのまま利用できます。

## Fluent API

PSR-18 に加えて、より簡潔な Fluent API も提供します。

### リクエストの送信

```php
use WPPack\Component\HttpClient\HttpClient;

// GET リクエスト
$response = $http
    ->withHeaders(['Accept' => 'application/json'])
    ->timeout(30)
    ->get('https://api.example.com/users', [
        'query' => ['page' => 1, 'limit' => 20],
    ]);

// JSON POST リクエスト
$response = $http->asJson()->post('/api/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// フォーム POST リクエスト
$response = $http->asForm()->post('/api/login', [
    'username' => 'john',
    'password' => 'secret',
]);
```

### レスポンスの処理

```php
$response = $http->get('https://api.example.com/data');

// ステータスチェック
if ($response->successful()) {
    $data = $response->json();
} elseif ($response->clientError()) {
    $error = $response->json()['message'];
} elseif ($response->serverError()) {
    // サーバーエラーの処理
}

// データ取得メソッド
$status = $response->status();          // int
$headers = $response->headers();        // array
$body = $response->body();              // string
$json = $response->json();              // array

// PSR-7 互換メソッド
$statusCode = $response->getStatusCode();
$body = $response->getBody()->getContents();
$contentType = $response->getHeaderLine('Content-Type');
```

### エラーハンドリング

```php
use WPPack\Component\HttpClient\Exception\RequestException;
use WPPack\Component\HttpClient\Exception\ConnectionException;
use Psr\Http\Client\ClientExceptionInterface;

try {
    $response = $http
        ->timeout(10)
        ->get('https://api.example.com/data')
        ->throw();

    return $response->json();
} catch (ConnectionException $e) {
    // ネットワークエラー（wp_remote_* が WP_Error を返した場合）
    $logger->error('API connection failed', ['exception' => $e]);
    return null;
} catch (RequestException $e) {
    // HTTP エラー（4xx / 5xx）
    $logger->error('API request failed', ['status' => $e->response->status(), 'exception' => $e]);
    return null;
}
```

## クイックスタート

### API クライアントクラス

```php
use WPPack\Component\HttpClient\HttpClient;

class BlogApiClient
{
    public function __construct(private HttpClient $http)
    {
        $this->http = $this->http->withOptions([
            'base_uri' => 'https://jsonplaceholder.typicode.com',
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getPosts(int $page = 1, int $limit = 10): array
    {
        $response = $this->http->get('/posts', [
            'query' => [
                '_page' => $page,
                '_limit' => $limit,
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch posts');
        }

        return $response->json();
    }

    public function getPost(int $id): array
    {
        $response = $this->http->get("/posts/{$id}");

        if ($response->status() === 404) {
            throw new \RuntimeException('Post not found');
        }

        return $response->json();
    }

    public function createPost(array $data): array
    {
        return $this->http
            ->asJson()
            ->post('/posts', $data)
            ->throw()
            ->json();
    }

    public function deletePost(int $id): bool
    {
        $response = $this->http->delete("/posts/{$id}");
        return $response->successful();
    }
}
```

### 認証付き API クライアント

```php
class AuthenticatedApiClient
{
    public function __construct(
        private HttpClient $http,
        private string $apiToken,
    ) {
        $this->http = $this->http->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
        ]);
    }

    public function getProfile(): array
    {
        return $this->http
            ->get('https://api.example.com/user/profile')
            ->throw()
            ->json();
    }
}

// Basic 認証
$response = $http
    ->withBasicAuth('username', 'password')
    ->get('https://api.example.com/protected');
```

### PSR-18 を使ったサードパーティライブラリ統合

PSR-18 準拠により、PSR-18 対応のサードパーティライブラリとシームレスに統合できます。

```php
use Psr\Http\Client\ClientInterface;

// WPPack の HttpClient を PSR-18 クライアントとして注入
// 内部的に WordPress の wp_remote_request() が使われる
$thirdPartyService = new SomeExternalSdk(
    client: $container->get(ClientInterface::class),
);
```

## 安全なリクエスト（SSRF 防止）

ユーザーが提供した URL（Webhook コールバック、外部 API エンドポイント等）にリクエストを送る場合、SSRF（Server-Side Request Forgery）防止が必要です。

SSRF とは、攻撃者が URL パラメータを操作して、サーバーから内部ネットワークやローカルサービスにアクセスさせる攻撃です。例えば、Webhook URL に `http://169.254.169.254/latest/meta-data/` を指定することで、EC2 インスタンスメタデータ（IAM ロールの一時クレデンシャル等）を読み取られる可能性があります。

### `reject_unsafe_urls` の挙動

`HttpClient` はデフォルトでは URL のバリデーションを行わず、`wp_remote_request()` にそのまま渡します。`safe()` メソッドまたは `SafeHttpClient` を使用すると、WordPress の `wp_http_validate_url()` によるバリデーションが有効になります（WordPress の `wp_safe_remote_request()` と同じ仕組み）。

バリデーションはリクエスト送信前に実行され、**URL のホスト名を DNS 解決してから IP アドレスを検査**します。これにより、`http://evil.example.com/`（内部 IP に解決されるドメイン）のような DNS リバインディング攻撃も防止できます。

#### ブロックされるリクエスト

| カテゴリ | 例 | 理由 |
|---------|-----|------|
| ループバック | `http://127.0.0.1/`, `http://localhost/` | ローカルサービスへのアクセス防止 |
| プライベートネットワーク | `http://10.0.1.5/`, `http://192.168.1.1/` | 内部ネットワークへのアクセス防止 |
| リンクローカル | `http://169.254.169.254/` | クラウドメタデータエンドポイント（AWS IMDSv1、GCP 等）の保護 |
| 非標準ポート | `http://example.com:3306/` | データベース等の内部サービスポートへの接続防止 |
| 認証情報付き URL | `http://user:pass@example.com/` | URL に埋め込まれた認証情報の漏洩防止 |
| DNS 解決不能 | `http://nonexistent.internal/` | 存在しないホストの拒否 |

#### 許可されるリクエスト

| カテゴリ | 例 |
|---------|-----|
| 外部ドメイン（パブリック IP） | `https://api.example.com/webhook` |
| 標準ポート（80, 443, 8080） | `https://api.example.com:443/` |
| 自サイトの URL | `https://mysite.example.com/wp-json/...`（ポート制限なし） |

#### ブロック時の挙動

バリデーションに失敗した場合、WordPress は `WP_Error('http_request_failed', 'A valid URL was not provided.')` を返します。HttpClient はこれを `ConnectionException` に変換してスローします。

```php
use WPPack\Component\HttpClient\Exception\ConnectionException;

try {
    $response = $http->safe()->get('http://169.254.169.254/latest/meta-data/');
} catch (ConnectionException $e) {
    // "A valid URL was not provided."
    $e->getMessage();
}
```

リダイレクト先も同様にバリデーションされます。例えば、`https://evil.com/redirect`（→ `http://127.0.0.1/admin`）のような間接的な SSRF もブロックされます。

### 2 つの方法

SSRF 防止には 2 つの方法があります。

#### `safe()` fluent メソッド

`HttpClient` の fluent メソッドで、アドホックに SSRF 防止を有効化します。`asJson()` や `asForm()` と同じイミュータブルなパターンで、新しいインスタンスを返します。

```php
$http = new HttpClient();

// ユーザーが入力した URL に対して安全にリクエスト
$response = $http->safe()->get($userProvidedUrl);

// 他の fluent メソッドとの組み合わせ
$response = $http
    ->safe()
    ->withHeaders(['Authorization' => 'Bearer ' . $token])
    ->timeout(10)
    ->asJson()
    ->post($userProvidedApiUrl, ['json' => $data]);
```

`safe()` は `HttpClient` のインスタンスを返すため、後から `withOptions(['reject_unsafe_urls' => false])` で無効化できる点に注意してください。これはアドホック利用（1 回のリクエストで safe にしたい場合）に適しています。

#### `SafeHttpClient` クラス

`HttpClient` を継承した専用サブクラスです。コンストラクタで `reject_unsafe_urls => true` を設定し、`withOptions()` をオーバーライドして無効化を防止します。

```php
use WPPack\Component\HttpClient\SafeHttpClient;

$client = new SafeHttpClient();

// reject_unsafe_urls は常に有効
$response = $client->get($userProvidedUrl);

// withOptions() で false にしても、内部で true に強制される（tamper-proof）
$client2 = $client->withOptions(['reject_unsafe_urls' => false]);
// → reject_unsafe_urls は true のまま

// fluent メソッドチェーンでも SafeHttpClient 型が維持される
$configured = $client->timeout(30)->asJson();
// → $configured は SafeHttpClient のインスタンス
```

### `HttpClient` との違い

| | `HttpClient` | `HttpClient::safe()` | `SafeHttpClient` |
|---|---|---|---|
| URL バリデーション | なし | `wp_http_validate_url()` で検証 | `wp_http_validate_url()` で検証 |
| プライベート IP への接続 | 許可 | **ブロック** | **ブロック** |
| ポート制限（80/443/8080 のみ） | なし | **あり** | **あり** |
| リダイレクト先の検証 | なし | **あり** | **あり** |
| `withOptions()` で無効化 | — | 可能 | **不可能**（tamper-proof） |
| fluent チェーン後の型 | `HttpClient` | `HttpClient` | `SafeHttpClient` |
| DI での型レベル強制 | — | — | `SafeHttpClient` 型で注入可能 |
| WordPress 対応 | `wp_remote_request()` | `wp_safe_remote_request()` 相当 | `wp_safe_remote_request()` 相当 |

### いつどちらを使うべきか

| ケース | 推奨 | 理由 |
|--------|------|------|
| Webhook ハンドラー | `SafeHttpClient` | URL が常にユーザー提供。DI で型レベルの安全性を強制 |
| OAuth コールバック検証 | `SafeHttpClient` | リダイレクト先が外部から制御可能 |
| 管理画面での URL プレビュー | `SafeHttpClient` | 管理者入力でも内部ネットワークへのアクセスを防止 |
| ユーザー入力 URL の一時的な検証 | `safe()` | 単発リクエストでアドホックに使用 |
| 自社 API との連携 | `HttpClient` | URL がコード内にハードコードされており信頼できる |
| サードパーティ API（固定 URL） | `HttpClient` | エンドポイントが既知で固定。SSRF リスクなし |

### DI で SafeHttpClient を注入

`SafeHttpClient` は DI コンテナに登録済みです。コンストラクタの型ヒントで注入するだけで、そのサービスが扱う URL が安全に処理されることが型レベルで保証されます。

```php
use WPPack\Component\HttpClient\SafeHttpClient;

class WebhookHandler
{
    public function __construct(private SafeHttpClient $http) {}

    public function handle(string $url, array $payload): void
    {
        // reject_unsafe_urls が常に有効 — 開発者が誤って無効化することもできない
        $this->http->asJson()->post($url, ['json' => $payload]);
    }
}
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/http-client.md) を参照してください。

## クイックリファレンス

### リクエストメソッド

```php
$http->get($url, $options);
$http->post($url, $options);
$http->put($url, $options);
$http->patch($url, $options);
$http->delete($url, $options);
$http->head($url, $options);
```

### リクエストオプション

```php
$http->withHeaders(['X-Custom' => 'value'])
     ->withBasicAuth($user, $pass)
     ->timeout(30)
     ->asJson()
     ->asForm()
     ->query(['param' => 'value']);
```

### レスポンスメソッド

```php
// Fluent API
$response->body();          // 生のレスポンス文字列
$response->json();          // デコード済み JSON
$response->status();        // ステータスコード
$response->headers();       // 全ヘッダー
$response->header('name');  // 単一ヘッダー
$response->successful();    // 2xx かどうか
$response->failed();        // 4xx または 5xx かどうか
$response->throw();         // エラー時に例外をスロー

// PSR-7 互換
$response->getStatusCode();
$response->getBody()->getContents();
$response->getHeaderLine('Content-Type');
$response->getHeaders();
```

### 主要クラス

| クラス | 説明 |
|-------|------|
| `HttpClient` | PSR-18 準拠の HTTP クライアント（`ClientInterface` 実装） |
| `SafeHttpClient` | SSRF 防止を強制する HttpClient サブクラス |
| `Request` | PSR-7 `RequestInterface` 実装 |
| `Response` | PSR-7 `ResponseInterface` 実装 + Fluent ヘルパー |
| `RequestFactory` | PSR-17 `RequestFactoryInterface` 実装 |
| `RequestException` | HTTP エラー例外 |
| `ConnectionException` | ネットワークエラー例外 |

## 利用シーン

**最適なケース:**
- 外部 API との連携が必要なプラグイン・テーマ
- PSR-18 対応のサードパーティライブラリとの統合
- 認証付き API リクエストの実装

**代替を検討すべきケース:**
- 単発のシンプルな HTTP リクエスト（`wp_remote_get()` で十分な場合）

## 依存関係

### 必須
- `psr/http-client` — PSR-18 インターフェース
- `psr/http-message` — PSR-7 インターフェース
- `psr/http-factory` — PSR-17 インターフェース

### 推奨
- **Cache コンポーネント** — レスポンスキャッシュ
