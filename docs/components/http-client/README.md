# HttpClient コンポーネント

**パッケージ:** `wppack/http-client`
**名前空間:** `WpPack\Component\HttpClient\`
**レイヤー:** Abstraction

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

### After（WpPack）

```php
use WpPack\Component\HttpClient\HttpClient;

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

| PSR | インターフェース | WpPack 実装 |
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
use WpPack\Component\HttpClient\HttpClient;

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

// マルチパートリクエスト（ファイルアップロード）
$response = $http->asMultipart()
    ->attach('avatar', fopen('/path/to/image.jpg', 'r'))
    ->post('/api/upload');
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
use WpPack\Component\HttpClient\Exception\RequestException;
use WpPack\Component\HttpClient\Exception\ConnectionException;
use Psr\Http\Client\ClientExceptionInterface;

try {
    $response = $http
        ->timeout(10)
        ->get('https://api.example.com/data')
        ->throw();

    return $response->json();
} catch (ConnectionException $e) {
    // ネットワークエラー（wp_remote_* が WP_Error を返した場合）
    error_log('API connection failed: ' . $e->getMessage());
    return null;
} catch (RequestException $e) {
    // HTTP エラー（4xx / 5xx）
    $status = $e->response->status();
    error_log('API request failed with status: ' . $status);
    return null;
}
```

## クイックスタート

### API クライアントクラス

```php
use WpPack\Component\HttpClient\HttpClient;

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

// WpPack の HttpClient を PSR-18 クライアントとして注入
// 内部的に WordPress の wp_remote_request() が使われる
$thirdPartyService = new SomeExternalSdk(
    client: $container->get(ClientInterface::class),
);
```

## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/HttpClient/Subscriber/`

### HTTP リクエストフック

```php
#[PreHttpRequestFilter(priority: 10)]              // リクエストのショートサーキット
#[HttpRequestArgsFilter(priority: 10)]              // リクエスト引数の変更
#[HttpRequestTimeoutFilter(priority: 10)]           // リクエストタイムアウトの設定
#[HttpRequestRedirectCountFilter(priority: 10)]     // リダイレクト回数の制限
```

### HTTP レスポンスフック

```php
#[HttpResponseFilter(priority: 10)]                 // レスポンスの処理
#[HttpApiDebugAction(priority: 10)]                  // HTTP トランザクションのデバッグ
```

### トランスポートフック

```php
#[HttpApiCurlFilter(priority: 10)]                   // cURL オプションの設定
```

### SSL/セキュリティ

```php
#[HttpRequestHostIsExternalFilter(priority: 10)]     // 外部ホストの判定
#[HttpsSslVerifyFilter(priority: 10)]                // SSL 検証
#[HttpsLocalSslVerifyFilter(priority: 10)]           // ローカル SSL 検証
```

### 使用例：リクエストインターセプター

```php
use WpPack\Component\HttpClient\Attribute\Filter\PreHttpRequestFilter;
use WpPack\Component\HttpClient\Attribute\Filter\HttpResponseFilter;

class HttpRequestHandler
{
    #[PreHttpRequestFilter(priority: 10)]
    public function handleRequest($preempt, array $parsedArgs, string $url)
    {
        // キャッシュの確認
        if ($this->shouldCache($url, $parsedArgs)) {
            $cached = $this->cache->get($url, $parsedArgs);
            if ($cached !== false) {
                return $cached;
            }
        }

        return $preempt;
    }

    #[HttpResponseFilter(priority: 10)]
    public function processResponse(array $response, array $parsedArgs, string $url): array
    {
        // 成功レスポンスをキャッシュ
        if (!is_wp_error($response) && $response['response']['code'] === 200) {
            if ($this->isCacheable($parsedArgs, $response)) {
                $this->cacheResponse($url, $parsedArgs, $response);
            }
        }

        return $response;
    }
}
```

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
     ->asMultipart()
     ->query(['param' => 'value'])
     ->attach('file', $resource, 'filename.txt');
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
- ファイルアップロードを含む HTTP 操作

**代替を検討すべきケース:**
- 単発のシンプルな HTTP リクエスト（`wp_remote_get()` で十分な場合）

## 依存関係

### 必須
- `psr/http-client` — PSR-18 インターフェース
- `psr/http-message` — PSR-7 インターフェース
- `psr/http-factory` — PSR-17 インターフェース

### 推奨
- **Cache コンポーネント** — レスポンスキャッシュ
