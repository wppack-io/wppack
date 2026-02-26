# WpPack HttpClient

WordPress HTTP API の PSR-18 準拠ラッパー。Fluent API で型安全な HTTP リクエストを実現。

## インストール

```bash
composer require wppack/http-client
```

## 使い方

### 基本的なリクエスト

```php
use WpPack\Component\HttpClient\HttpClient;

$http = new HttpClient();

$response = $http->get('https://api.example.com/users');

if ($response->successful()) {
    $data = $response->json();
}
```

### Fluent API

```php
$response = $http
    ->withHeaders(['Authorization' => 'Bearer ' . $token])
    ->timeout(30)
    ->asJson()
    ->post('https://api.example.com/users', [
        'json' => ['name' => 'John', 'email' => 'john@example.com'],
    ]);

$response->throw(); // 4xx/5xx でスロー
```

### ベース URI

```php
$client = $http->baseUri('https://api.example.com/v1');

$response = $client->get('/users');     // https://api.example.com/v1/users
$response = $client->get('/posts');     // https://api.example.com/v1/posts
```

### レスポンスヘルパー

```php
$response->status();        // 200
$response->headers();       // ['Content-Type' => ['application/json']]
$response->header('Content-Type'); // 'application/json'
$response->body();          // レスポンスボディ文字列
$response->json();          // JSON デコード結果
$response->successful();    // 200-299
$response->failed();        // 400-599
$response->clientError();   // 400-499
$response->serverError();   // 500-599
$response->throw();         // エラー時に例外スロー
```

### PSR-18 互換

```php
use Psr\Http\Client\ClientInterface;

function fetchData(ClientInterface $client): array
{
    $factory = new \WpPack\Component\HttpClient\WpPackRequestFactory();
    $request = $factory->createRequest('GET', 'https://api.example.com/data');

    $response = $client->sendRequest($request);

    return json_decode((string) $response->getBody(), true);
}
```

### Named Hook Attributes

```php
use WpPack\Component\HttpClient\Attribute\HttpRequestArgsFilter;
use WpPack\Component\HttpClient\Attribute\HttpResponseFilter;

final class HttpHooks
{
    #[HttpRequestArgsFilter]
    public function addCustomHeader(array $args, string $url): array
    {
        $args['headers']['X-Custom'] = 'value';
        return $args;
    }

    #[HttpResponseFilter(priority: 20)]
    public function logResponse(array $response, array $args, string $url): array
    {
        // レスポンスのログ処理
        return $response;
    }
}
```

## エラーハンドリング

```php
use WpPack\Component\HttpClient\Exception\ConnectionException;
use WpPack\Component\HttpClient\Exception\RequestException;

try {
    $response = $http->get('https://api.example.com/data');
    $response->throw();
} catch (ConnectionException $e) {
    // ネットワークエラー（WP_Error）
} catch (RequestException $e) {
    // HTTP エラー（4xx/5xx）
    $status = $e->response->status();
}
```

## ドキュメント

詳細は [docs/components/http-client.md](../../docs/components/http-client.md) を参照してください。

## リソース

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

メインリポジトリ [wppack-io/wppack](https://github.com/wppack-io/wppack) で開発しています。

## ライセンス

MIT
