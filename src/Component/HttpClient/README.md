# WPPack HttpClient

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=http_client)](https://codecov.io/github/wppack-io/wppack)

A PSR-18 compliant wrapper for the WordPress HTTP API. Provides type-safe HTTP requests with a Fluent API.

## Installation

```bash
composer require wppack/http-client
```

## Usage

### Basic Request

```php
use WPPack\Component\HttpClient\HttpClient;

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

$response->throw(); // Throws on 4xx/5xx
```

### Base URI

```php
$client = $http->baseUri('https://api.example.com/v1');

$response = $client->get('/users');     // https://api.example.com/v1/users
$response = $client->get('/posts');     // https://api.example.com/v1/posts
```

### Response Helpers

```php
$response->status();        // 200
$response->headers();       // ['Content-Type' => ['application/json']]
$response->header('Content-Type'); // 'application/json'
$response->body();          // Response body string
$response->json();          // JSON decoded result
$response->successful();    // 200-299
$response->failed();        // 400-599
$response->clientError();   // 400-499
$response->serverError();   // 500-599
$response->throw();         // Throws exception on error
```

### PSR-18 Compatible

```php
use Psr\Http\Client\ClientInterface;

function fetchData(ClientInterface $client): array
{
    $factory = new \WPPack\Component\HttpClient\RequestFactory();
    $request = $factory->createRequest('GET', 'https://api.example.com/data');

    $response = $client->sendRequest($request);

    return json_decode((string) $response->getBody(), true);
}
```

### Named Hook Attributes

```php
use WPPack\Component\HttpClient\Attribute\HttpRequestArgsFilter;
use WPPack\Component\HttpClient\Attribute\HttpResponseFilter;

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
        // Response logging
        return $response;
    }
}
```

### Safe Requests (SSRF Protection)

When handling user-provided URLs (webhooks, callbacks, etc.), use SSRF protection to validate URLs before sending requests. This uses WordPress's `wp_http_validate_url()` (the same mechanism as `wp_safe_remote_request()`) to:

- **Resolve hostnames via DNS and block private/reserved IPs** — `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16` (cloud metadata endpoints)
- **Restrict ports** to 80, 443, 8080 only
- **Reject URLs with embedded credentials** — `http://user:pass@host/`
- **Validate redirect targets** — prevents indirect SSRF via redirects

Blocked requests throw `ConnectionException` with "A valid URL was not provided."

Two approaches are available:

**`safe()` fluent method** — ad-hoc use on `HttpClient`. Returns a new instance with `reject_unsafe_urls` enabled. Note: can be overridden by a subsequent `withOptions()` call.

```php
$response = $http->safe()->get($userProvidedUrl);

$response = $http
    ->safe()
    ->withHeaders(['Authorization' => 'Bearer ' . $token])
    ->timeout(10)
    ->asJson()
    ->post($userProvidedApiUrl, ['json' => $data]);
```

**`SafeHttpClient`** — tamper-proof subclass for DI injection. SSRF protection is always enabled and cannot be disabled via `withOptions()`. Use this for services that always handle user-provided URLs.

```php
use WPPack\Component\HttpClient\SafeHttpClient;

class WebhookHandler
{
    public function __construct(private SafeHttpClient $http) {}

    public function handle(string $url, array $payload): void
    {
        // reject_unsafe_urls is always enabled — cannot be disabled via withOptions()
        $this->http->asJson()->post($url, ['json' => $payload]);
    }
}
```

| | `HttpClient` | `HttpClient::safe()` | `SafeHttpClient` |
|---|---|---|---|
| URL validation | None | `wp_http_validate_url()` | `wp_http_validate_url()` |
| Private IP blocking | No | **Yes** | **Yes** |
| Port restriction (80/443/8080) | No | **Yes** | **Yes** |
| Redirect validation | No | **Yes** | **Yes** |
| Can be disabled via `withOptions()` | — | Yes | **No** (tamper-proof) |
| Type after fluent chaining | `HttpClient` | `HttpClient` | `SafeHttpClient` |

## Error Handling

```php
use WPPack\Component\HttpClient\Exception\ConnectionException;
use WPPack\Component\HttpClient\Exception\RequestException;

try {
    $response = $http->get('https://api.example.com/data');
    $response->throw();
} catch (ConnectionException $e) {
    // Network error (WP_Error)
} catch (RequestException $e) {
    // HTTP error (4xx/5xx)
    $status = $e->response->status();
}
```

## Documentation

For details, see [docs/components/http-client/](../../docs/components/http-client/).

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Developed in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).

## License

MIT
