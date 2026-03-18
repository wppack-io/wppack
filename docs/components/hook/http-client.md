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
use WpPack\Component\Hook\Attribute\HttpClient\Filter\PreHttpRequestFilter;
use WpPack\Component\Hook\Attribute\HttpClient\Filter\HttpResponseFilter;

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
